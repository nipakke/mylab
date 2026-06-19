<!-- Context: development/backend/php-standards | Priority: high | Version: 1.0 | Updated: 2026-06-19 -->

# PHP Language Standards — Dash

> How to write modern PHP in this codebase. Type-safe, async-aware, idiomatic. PHP 8.2 baseline with forward-looking guidance.

## Quick Reference

- **Baseline**: PHP 8.2 (`declare(strict_types=1)` in every file)
- **Type coverage**: All properties, parameters, and return types explicitly declared
- **State**: `readonly` classes for DTOs, immutable by default
- **Flow**: `match` over `switch`, named arguments for clarity, enums for fixed sets
- **Never**: Blocking I/O in the ReactPHP event loop

---

## Type System

### Mandatory Strict Types

Every PHP file starts with:

```php
<?php

declare(strict_types=1);
```

No exceptions. This catches type coercion bugs at the call site rather than silently corrupting data.

### Return Type Coverage

Every method and function declares a return type:

```php
// ✅ Always declare return types
public function listContainers(): PromiseInterface
public function findByName(string $name): ?Container
public function toArray(): array

// ❌ Never omit return types
public function listContainers()
```

### Nullable Types

Prefer `null` over sentinel values. Use union types for explicit nullability:

```php
// ✅ Explicit nullable
public function find(string $id): ?Container
public function resolve(?string $host): string

// ❌ Avoid sentinel values
public function find(string $id): Container|false
```

---

## Language Features

### Constructor Property Promotion (PHP 8.0)

Eliminate boilerplate property declarations. The canonical form in this project:

```php
// ✅ Single-line promotion for DTOs
final readonly class Container
{
    public function __construct(
        public string $id,
        public string $name,
        public string $status,
        public int $memoryUsageBytes,
        public array $labels = [],
    ) {}
}

// ✅ For service classes with dependencies
final class DockerService implements DockerServiceInterface
{
    public function __construct(
        private readonly Client $client,
    ) {}
}
```

**Rule**: Promote everything unless you need a property hook or asymmetric visibility (PHP 8.4+).

### Named Arguments (PHP 8.0)

Use when calling functions with multiple optional/default parameters. Renders intent at the call site:

```php
// ✅ Self-documenting intent
$container = new Container(
    id: 'abc123',
    name: 'web-server',
    status: 'running',
    memoryUsageBytes: 128 * 1024 * 1024,
);

// ✅ Skip defaults explicitly
$response = new HtmlResponse(
    body: $html,
    statusCode: 200,
    // headers defaults to [] - intentionally omitted
);
```

**Rule**: Use named arguments when a constructor or function has 3+ parameters, or when multiple parameters share the same type.

### Match Expression (PHP 8.0)

Replace `switch` with `match` for exhaustive, expression-oriented branching:

```php
// ✅ Match — exhaustive, returns value, no break needed
function statusClass(string $status): string
{
    return match ($status) {
        'running' => 'badge-success',
        'exited' => 'badge-error',
        'paused' => 'badge-warning',
        default => 'badge-ghost',
    };
}

// ✅ Multi-condition match
function isActive(string $status): bool
{
    return match ($status) {
        'running', 'paused' => true,
        'exited', 'dead', 'removing' => false,
        default => false,
    };
}

// ❌ Avoid switch — no return value, needs break, can fall through silently
```

**Rule**: Always use `match` over `switch`. Include a `default` arm unless the match is provably exhaustive.

### Enums (PHP 8.1)

Use backed enums for any fixed set of values that cross a boundary (template rendering, API response, persistence):

```php
enum ContainerState: string
{
    case Running = 'running';
    case Exited = 'exited';
    case Paused = 'paused';
    case Dead = 'dead';
    case Created = 'created';
    case Restarting = 'restarting';
    case Removing = 'removing';

    public function isActive(): bool
    {
        return match ($this) {
            self::Running, self::Paused, self::Restarting => true,
            default => false,
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Running => 'badge-success',
            self::Exited, self::Dead => 'badge-error',
            self::Paused => 'badge-warning',
            self::Restarting => 'badge-info',
            self::Created, self::Removing => 'badge-ghost',
        };
    }
}
```

**Rule**: Use an enum whenever you find yourself writing `if ($status === 'running')` in more than one place. Enums are first-class types — function parameters and return types become self-documenting.

### Readonly Classes (PHP 8.2)

Make DTOs immutable. This project's `Domain\` namespace already uses this:

```php
// ✅ Readonly class = immutable value object
final readonly class Container
{
    public function __construct(
        public string $id,
        public string $name,
        public ContainerState $state,
        public int $memoryBytes,
    ) {}
}
```

**Rule**: Every class in `src/Domain/` should be `final readonly`. Never add setters to domain objects. Create a new instance if transformation is needed.

### First-Class Callables (PHP 8.1)

Use the `$object->method(...)` syntax for cleaner callback wiring:

```php
// ✅ First-class callable syntax
$promise = $client->get('/containers/json')
    ->then($this->parseResponse(...))
    ->then($this->hydrateContainers(...));

// ❌ Avoid closure wrapping
$promise = $client->get('/containers/json')
    ->then(fn ($response) => $this->parseResponse($response))
    ->then(fn ($data) => $this->hydrateContainers($data));
```

### Nullsafe Operator (PHP 8.0)

Chain property/method access without intermediate null checks:

```php
// ✅ Nullsafe chain
$label = $container->labels?->get('com.docker.compose.service') ?? 'unknown';

// ❌ Avoid nested ternaries
$label = $container->labels !== null
    ? ($container->labels->get('com.docker.compose.service') ?? 'unknown')
    : 'unknown';
```

---

## Future Features (PHP 8.4 / 8.5)

These are noted for awareness but NOT usable until the project's minimum PHP version is raised.

### Property Hooks (PHP 8.4) — Future

Will replace explicit getters/setters when available:

```php
// Future: PHP 8.4+
class Container
{
    public string $name {
        get => $this->name;
        set(string $value) {
            if ($value === '') {
                throw new \InvalidArgumentException('Container name cannot be empty');
            }
            $this->name = trim($value);
        }
    }
}
```

### Asymmetric Visibility (PHP 8.4) — Future

Will allow public read with restricted write, replacing the need for separate getter methods:

```php
// Future: PHP 8.4+
class ContainerStore
{
    public private(set) array $containers = [];
}
```

### Pipe Operator (PHP 8.5) — Future

Will enable left-to-right function composition, replacing nested calls:

```php
// Future: PHP 8.5+
$result = $containers
    |> array_filter(?, $this->isRunning(...))
    |> array_map(?, $this->toSummary(...))
    |> array_values(?);
```

---

## Naming Conventions

| Construct | Convention | Example |
|-----------|------------|---------|
| Classes | PascalCase | `ListContainers`, `ContainerStore` |
| Methods/functions | camelCase | `listContainers()`, `findByName()` |
| Variables | camelCase | `$containerId`, `$memoryBytes` |
| Constants (class) | PascalCase | `State::Running` |
| Constants (global) | UPPER_SNAKE_CASE | `APP_ENV` |
| Interfaces | `*Interface` suffix | `DockerServiceInterface` |
| Abstract classes | `Abstract*` prefix | (none currently — prefer interfaces) |
| DTOs | Noun, no suffix | `Container`, `ContainerSummary` |
| Actions | VerbPhrase | `ListContainers`, `RefreshContainers` |
| Handlers | `*Handler` suffix | `HomeHandler`, `ContainerRefreshHandler` |
| Services | `*Service` suffix | `DockerService`, `ContainerStore` |
| Test files | `*Test.php` suffix | `ListContainersTest.php` |

---

## File Organization

```
src/
├── Action/           # One class per use-case, verb-named
├── Domain/           # final readonly DTOs, enums, pure functions
├── Handler/          # Thin HTTP adapters, one per route
├── Job/              # Background periodic tasks
└── Service/          # Infrastructure — Docker client, in-memory store
```

**Rule**: Each directory maps to one architectural layer. Never mix concerns — a Handler never calls Docker directly, a Domain class never touches HTTP.

---

## Anti-Patterns

- ❌ **Missing strict types** — Every file needs `declare(strict_types=1)`
- ❌ **Blocking I/O** — `file_get_contents()`, `PDO::query()`, `sleep()` stall the entire event loop
- ❌ **Mutable DTOs** — Domain objects must be `readonly`; create new instances for transformations
- ❌ **Switch statements** — Use `match` for exhaustiveness and return values
- ❌ **Stringly-typed statuses** — Use enums instead of raw strings for Docker states
- ❌ **Closure-wrapped callbacks** — Use first-class callable syntax (`$this->method(...)`)
- ❌ **Silent null access** — Use nullsafe operator (`$obj?->prop`) over nested ternaries
- ❌ **Untyped closures** — Closure parameters should be typed when the signature is known

---

## Onboarding Checklist

- [ ] Know the PHP baseline version and required strict types
- [ ] Understand when to use each PHP 8.x feature
- [ ] Know which features are future-only (8.4/8.5)
- [ ] Understand naming conventions per architectural layer
- [ ] Know the file organization rules and anti-patterns to avoid
