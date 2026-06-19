<!-- Context: development/backend/patterns | Priority: high | Version: 1.0 | Updated: 2026-06-19 -->

# Design Patterns — Dash Backend

> Layered async architecture patterns. Every pattern is driven by a single constraint: no blocking I/O in the ReactPHP event loop.

## Quick Reference

- **Layers**: Handler (HTTP) → Action (use-case) → Service (infrastructure)
- **Direction**: Top-down only. Lower layers never know about upper layers.
- **State**: Immutable DTOs carry data between layers. No mutation after construction.
- **Async**: All I/O returns `React\Promise\PromiseInterface`. Compose with `->then()`.
- **Testing**: Each layer tested in isolation. Dependencies are interfaces, injected via constructor.

---

## Core Pattern: Handler → Action → Service

This is the backbone of every request in the system:

```
HTTP Request
    │
    ▼
Handler         Parse request, delegate to Action, build Response
    │
    ▼
Action          Execute use-case, orchestrate Services, return Promise<Domain>
    │
    ▼
Service         Talk to Docker API, manage in-memory store
    │
    ▼
Domain          Immutable DTOs carrying data back up
```

### Layer Rules

| Layer | Knows About | Never Does |
|-------|-------------|------------|
| **Handler** | HTTP (Request, Response), Action interface | Business logic, Docker calls, template rendering decisions |
| **Action** | Service interfaces, Domain objects, Promises | HTTP parsing, Response formatting, raw Docker API |
| **Service** | External APIs (Docker), in-memory state | HTTP concerns, Domain logic, use-case decisions |
| **Domain** | Nothing — pure data | I/O, logic, transformation |

### Example: Container Refresh Flow

```php
// Handler — thin HTTP adapter
final class ContainerRefreshHandler
{
    public function __construct(
        private readonly RefreshContainers $action,
    ) {}

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        return $this->action->execute()->then(
            fn (array $containers) => new HtmlResponse(
                body: $this->renderer->render('partials/container-rows.twig', [
                    'containers' => $containers,
                ]),
            ),
            fn (\Throwable $e) => new HtmlResponse(
                body: $this->renderer->render('partials/error.twig', [
                    'message' => 'Unable to refresh containers',
                ]),
                statusCode: 500,
            ),
        );
    }
}

// Action — use-case, pure orchestration
final class RefreshContainers
{
    public function __construct(
        private readonly DockerServiceInterface $docker,
        private readonly ContainerStore $store,
    ) {}

    /** @return PromiseInterface<Container[]> */
    public function execute(): PromiseInterface
    {
        return $this->docker->listContainers()
            ->then(function (array $raw) {
                $containers = array_map(
                    Container::fromDockerResponse(...),
                    $raw,
                );
                $this->store->replace($containers);
                return $this->store->all();
            });
    }
}
```

**Key insight**: The Handler never touches Docker, the Action never touches HTTP, and both are trivially testable because dependencies are interfaces.

---

## Repository Pattern (In-Memory)

Unlike traditional ORM repositories, our stores are in-memory collections. The interface is the same but the implementation is drastically simpler:

```php
interface ContainerStoreInterface
{
    /** @return Container[] */
    public function all(): array;

    public function find(string $id): ?Container;

    /** @param Container[] $containers */
    public function replace(array $containers): void;

    public function count(): int;
}

final class ContainerStore implements ContainerStoreInterface
{
    /** @var array<string, Container> */
    private array $containers = [];

    public function all(): array
    {
        return array_values($this->containers);
    }

    public function find(string $id): ?Container
    {
        return $this->containers[$id] ?? null;
    }

    public function replace(array $containers): void
    {
        $this->containers = [];
        foreach ($containers as $container) {
            $this->containers[$container->id] = $container;
        }
    }

    public function count(): int
    {
        return count($this->containers);
    }
}
```

**Rule**: Every Service that holds state should have an interface. This makes Actions testable with a mock store and opens the door to swapping implementations (e.g., Redis-backed store later).

---

## DTO Pattern (Data Transfer Objects)

DTOs are the spine of the domain layer. They carry typed data across boundaries without behavior:

```php
final readonly class Container
{
    public function __construct(
        public string $id,
        public string $name,
        public ContainerState $state,
        public string $status,
        public int $memoryUsageBytes,
        public array $labels = [],
        public \DateTimeImmutable $createdAt,
    ) {}

    /** Parse raw Docker API response into typed domain object */
    public static function fromDockerResponse(object $raw): self
    {
        return new self(
            id: $raw->Id,
            name: ltrim(implode(',', $raw->Names ?? []), '/'),
            state: ContainerState::from($raw->State),
            status: $raw->Status,
            memoryUsageBytes: (int) ($raw->HostConfig->Memory ?? 0),
            labels: (array) ($raw->Labels ?? []),
            createdAt: new \DateTimeImmutable('@' . $raw->Created),
        );
    }

    /** Convert to template-safe array */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'state' => $this->state,
            'status' => $this->status,
            'memory' => $this->formatMemory($this->memoryUsageBytes),
            'created' => $this->createdAt->format('Y-m-d H:i'),
        ];
    }
}
```

**DTO rules**:
- `final readonly` — immutable after construction
- Named constructor for each data source (`fromDockerResponse`, `fromArray`)
- `toArray()` for template rendering — keeps Twig templates free of object method calls
- Zero logic beyond formatting and validation
- No service dependencies, no I/O, no side effects

---

## Promise Composition (Async)

ReactPHP uses promises (PSR-7-ish) for async operations. Understanding how to compose them is critical:

### Chaining

```php
// Sequential: each step waits for the previous
$result = $this->docker->listContainers()
    ->then(fn (array $raw) => $this->hydrate($raw))
    ->then(fn (array $containers) => $this->enrich($containers))
    ->then(fn (array $enriched) => $this->store->replace($enriched));
```

### Error Propagation

```php
// Catch errors at the boundary (usually the Handler)
$promise = $this->action->execute()
    ->then(
        fn ($result) => new HtmlResponse(body: $this->render('success.twig', $result)),
        fn (\Throwable $e) => new HtmlResponse(
            body: $this->render('error.twig', ['message' => $e->getMessage()]),
            statusCode: 500,
        ),
    );
```

### Promise-Aware Service Interface

```php
interface DockerServiceInterface
{
    /** @return PromiseInterface<object[]> */
    public function listContainers(): PromiseInterface;

    /** @return PromiseInterface<object> */
    public function inspectContainer(string $id): PromiseInterface;
}
```

**Rule**: Every method that performs I/O returns `PromiseInterface`. Never call a blocking function from within a promise chain — the event loop is shared.

---

## Pure Functions for Domain Logic

Extract pure functions from domain objects. They're trivially testable and composable:

```php
// Instead of a method on Container, make it a free function:
function parseContainerName(array $names): string
{
    if (empty($names)) {
        return 'unknown';
    }

    $firstName = reset($names);

    return ltrim($firstName, '/');
}

function formatBytes(int $bytes, int $precision = 1): string
{
    if ($bytes === 0) {
        return '0 B';
    }

    $units = ['B', 'KB', 'MB', 'GB'];
    $power = (int) floor(log($bytes, 1024));

    return round($bytes / (1024 ** $power), $precision) . ' ' . $units[$power];
}

function isProjectContainer(array $labels, string $project): bool
{
    $label = $labels['com.docker.compose.project'] ?? null;

    return $label === $project;
}
```

**Rule**: When logic doesn't need `$this`, make it a pure function. Organize related functions in `src/Domain/` as `*-parser.php`, `*-formatter.php`, etc.

---

## Interface-Driven Design

Every Service dependency in an Action should be an interface, not a concrete class:

```php
// ❌ Tightly coupled — Action knows the concrete DockerService
final class ListContainers
{
    public function __construct(
        private readonly DockerService $docker,  // concrete!
    ) {}
}

// ✅ Loosely coupled — Action only knows the contract
final class ListContainers
{
    public function __construct(
        private readonly DockerServiceInterface $docker,  // interface!
    ) {}
}
```

This means:
- Tests can inject a mock implementation
- The Docker client can be swapped (e.g., from `clue/docker-react` to a `docker.sock` curl adapter)
- The interface documents the contract without revealing implementation

---

## Pattern Selection Guide

| When You Need To... | Use This Pattern |
|---------------------|-----------------|
| Parse an HTTP request and return HTML | Handler (thin, delegates to Action) |
| Execute a single business use-case | Action (orchestrates Services, returns Promise) |
| Talk to Docker API | Service (implements interface, async I/O) |
| Hold in-memory state | Service (implements interface, sync methods OK) |
| Carry typed data between layers | Domain DTO (`final readonly` class) |
| Represent a fixed set of values | Enum (backed, with helper methods) |
| Transform data without I/O | Pure function in `src/Domain/` |
| Compose async operations | Promise chaining with `->then()` |

---

## Anti-Patterns

- ❌ **Handler doing business logic** — Handlers parse HTTP and delegate. Nothing more.
- ❌ **Service returning HTML** — Services return domain objects or Promises. Rendering is the Handler's job.
- ❌ **Action injecting concrete services** — Depend on interfaces, not concrete classes.
- ❌ **DTOs with logic** — DTOs are data carriers. If you need transformation, use a pure function or a named constructor.
- ❌ **Mixed sync/async** — If a method touches I/O, it returns a Promise. If it doesn't, it returns a value. Never have a method that sometimes returns a Promise and sometimes doesn't.
- ❌ **Promise in constructor** — Constructors are synchronous. Defer async work to an `execute()` or `run()` method.

---

## Onboarding Checklist

- [ ] Understand the Handler → Action → Service layering
- [ ] Know the rules for each layer (what it knows, what it never does)
- [ ] Understand DTO immutability and named constructors
- [ ] Know how to compose and chain Promises
- [ ] Know when to extract pure functions vs. keep logic in classes
- [ ] Understand interface-driven design and why it matters for testing
