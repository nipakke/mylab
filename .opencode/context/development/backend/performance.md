<!-- Context: development/backend/performance | Priority: medium | Version: 1.0 | Updated: 2026-06-19 -->

# PHP Performance — Dash Production

> Runtime optimization for the PHP 8.3 + ReactPHP production stack. OPcache, JIT, preloading, and async-specific tuning.

## Quick Reference

| Concern | Mechanism | When |
|---------|-----------|------|
| Bytecode caching | OPcache | Always in production |
| Just-in-time compilation | JIT (PHP 8.0+) | CPU-bound workloads |
| Class preloading | `opcache.preload` | Long-running ReactPHP process |
| Template caching | Twig compile cache | `APP_ENV=prod` |
| Event loop health | Non-blocking I/O | Always |

---

## OPcache Configuration

OPcache stores compiled PHP bytecode in shared memory, eliminating parse/compile overhead on every request. For a long-running ReactPHP process, this is essential.

```
; Production php.ini — apply to the PHP container
opcache.enable=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=20000
opcache.validate_timestamps=0
opcache.revalidate_freq=0
opcache.save_comments=1
opcache.enable_file_override=0
```

### Setting Rationale

| Setting | Value | Why |
|---------|-------|-----|
| `memory_consumption` | 256 MB | Dash is a small codebase, but 256 MB is the safe default for most deployments |
| `interned_strings_buffer` | 16 MB | Reduces memory by deduplicating repeated strings (class names, method names) |
| `max_accelerated_files` | 20000 | Covers all files from the codebase + vendor dependencies (composer autoloader) |
| `validate_timestamps` | 0 | Production only — skip stat checks on every request. File changes require a restart |
| `revalidate_freq` | 0 | Paired with `validate_timestamps=0` — no periodic revalidation |
| `save_comments` | 1 | Required for annotation-based libraries and docblock parsing |

**Dev override**: In local development, set `validate_timestamps=1` so file changes are picked up without restart.

---

## JIT Configuration

PHP 8.0+ JIT compiles hot code paths to native machine code. For Dash's workload (Docker API calls, Twig rendering), the benefit is moderate but meaningful:

```
; Production JIT — function-level tracing
opcache.jit_buffer_size=128M
opcache.jit=1255
```

### JIT Mode Reference

| Mode | Value | Description |
|------|-------|-------------|
| `tracing` | 1255 | Function-level JIT (recommended) — traces hot code at function boundaries |
| `function` | 1205 | Simpler function-level JIT — less overhead, slightly fewer optimizations |
| `disabled` | 0 | No JIT — use for debugging |
| `minimal` | 1204 | Minimal function JIT — lowest overhead |

**Recommendation**: Use `1255` (tracing) for production. This is the most aggressive and well-tested mode. If memory is constrained, drop `jit_buffer_size` to `64M`.

### Verifying JIT

```bash
# Inside the container
php -r 'var_dump(opcache_get_status()["jit"]);'
```

Expected output includes `"on": true` and non-zero `buffer_size`.

---

## Preloading

Preloading loads PHP classes into shared memory at server startup, so they're available to all worker processes without disk I/O:

```php
<?php
// preload.php — loaded once at PHP process startup

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

// Preload the entire application namespace
$appDir = __DIR__ . '/src';
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($appDir),
);

foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        require_once $file->getPathname();
    }
}

// Preload key vendor classes (ReactPHP, FastRoute, Twig)
$vendorClasses = [
    React\Http\Server::class,
    React\EventLoop\Loop::class,
    FastRoute\Dispatcher::class,
    Twig\Environment::class,
    Twig\Loader\FilesystemLoader::class,
];

foreach ($vendorClasses as $class) {
    class_exists($class); // Triggers autoloader, caches in OPcache
}
```

**Configuration**:

```
; php.ini
opcache.preload=/var/www/app/preload.php
opcache.preload_user=www-data
```

**Caveats**:
- Preloaded classes persist in shared memory. If you modify source files, you must restart the PHP process.
- Conditionally preload only in production: wrap `preload.php` in an environment check.
- Large vendor directories (like `guzzle`, `psr`) don't need preloading — focus on your own `src/` and framework entry points.

---

## Twig Template Caching

Twig compiles templates to PHP classes. In production, these are cached to disk:

```php
// ServerKernel.php — Twig setup
$twig = new Environment($loader, [
    'cache' => $appEnv === 'prod'
        ? __DIR__ . '/../cache/twig'
        : false,
    'auto_reload' => $appEnv !== 'prod',
    'strict_variables' => true,  // Fail loudly on undefined vars
]);
```

**Performance impact**: Without caching, every template is parsed and compiled on every render. With caching, it's a `require_once` of a pre-compiled PHP class.

**Cache directory**: Ensure `cache/twig/` is writable by the PHP process user (`www-data`) and persisted across container restarts (mount it as a volume if using ephemeral containers).

---

## ReactPHP Event Loop — The Golden Rule

**Never block the event loop.** The entire server runs on a single thread. A single blocking call stalls all concurrent requests.

### Blocking (Never Do This)

```php
// ❌ Blocks the event loop for seconds
$data = file_get_contents('https://api.example.com/data.json');

// ❌ Blocks the event loop
sleep(5);

// ❌ Blocks the event loop (PDO synchronous I/O)
$rows = $pdo->query('SELECT * FROM containers')->fetchAll();

// ❌ Blocks the event loop
$result = shell_exec('docker ps');
```

### Non-Blocking (Always Do This)

```php
// ✅ Non-blocking HTTP via ReactPHP HTTP client
$browser->get('https://api.example.com/data.json')
    ->then(fn (ResponseInterface $response) => json_decode((string) $response->getBody()));

// ✅ Non-blocking delay via event loop timer
Loop::addTimer(5.0, fn () => $this->collect());

// ✅ Non-blocking process execution via ReactPHP ChildProcess
$process = new Process('docker ps');
$process->start($loop);
$process->stdout->on('data', fn ($chunk) => /* ... */);

// ✅ Async Docker API via clue/docker-react (already non-blocking)
$this->client->get('/containers/json')
    ->then(fn ($response) => /* ... */);
```

### Detecting Blocking Code

If a request hangs for several seconds and blocks all other requests, you have blocking I/O. Common culprits:
- `file_get_contents()` with remote URLs
- Synchronous DNS resolution (`gethostbyname()`)
- `PDO` or `mysqli` synchronous queries
- `exec()`, `shell_exec()`, `system()`, `passthru()`

**Tooling**: Use `strace` on the PHP process to see what syscall it's blocked on:

```bash
strace -p $(pgrep -f 'bin/server') -e trace=read,write,connect
```

---

## Container Configuration (Dockerfile)

The production Dockerfile should apply these optimizations:

```dockerfile
# Multi-stage: build tools in first stage, runtime in second
FROM php:8.3-cli-alpine AS builder
# ... install dependencies, run composer install --no-dev ...

FROM php:8.3-cli-alpine AS runtime
# Copy OPcache and JIT config
COPY docker/php.ini /usr/local/etc/php/conf.d/99-performance.ini
# Copy preload script
COPY docker/preload.php /var/www/app/preload.php

# Run ReactPHP directly (long-running async process)
CMD ["php", "bin/server"]
```

---

## Performance Checklist

- [ ] OPcache enabled with `validate_timestamps=0` in production
- [ ] JIT enabled with tracing mode (`1255`)
- [ ] Preload script loads `src/` and key vendor classes
- [ ] Twig cache directory writable and persisted
- [ ] No blocking I/O anywhere in the codebase
- [ ] `composer install --no-dev --optimize-autoloader` in production build
- [ ] Long-running ReactPHP process (not CGI/FPM)
- [ ] OPcache status verified via `opcache_get_status()`

---

## Onboarding Checklist

- [ ] Understand OPcache's role in eliminating per-request compile overhead
- [ ] Know the JIT modes and which to use in production
- [ ] Understand preloading for long-running ReactPHP processes
- [ ] Know the golden rule: never block the event loop
- [ ] Know how to detect and fix blocking I/O
