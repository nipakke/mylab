<?php

declare(strict_types=1);

namespace MyLab\Service;

/**
 * In-memory token-bucket rate limiter — fits ReactPHP's single-process model.
 *
 * Each IP address gets a token bucket that refills at a constant rate.
 * A burst of up to RATE_LIMIT requests is allowed immediately; after that
 * requests are throttled to RATE_LIMIT per RATE_LIMIT_WINDOW seconds.
 *
 * Configured via env vars:
 *  - RATE_LIMIT        : max requests per window (default: 60)
 *  - RATE_LIMIT_WINDOW : window duration in seconds (default: 60)
 *
 * Stale buckets are pruned periodically to prevent memory growth.
 */
final class RateLimiter
{
    private readonly int $maxTokens;

    private readonly float $refillRate;

    private readonly int $windowSeconds;

    /** @var array<string, array{tokens: float, lastRefill: float}> */
    private array $buckets = [];

    private float $lastPrune = 0.0;

    /**
     * Create a new rate limiter.
     *
     * Defaults to 60 requests per 60 seconds (1 req/s sustained, 60 burst).
     */
    public function __construct()
    {
        $this->maxTokens = max(1, (int) (getenv('RATE_LIMIT') ?: 60));
        $this->windowSeconds = max(1, (int) (getenv('RATE_LIMIT_WINDOW') ?: 60));
        $this->refillRate = $this->maxTokens / $this->windowSeconds;
    }

    /**
     * Check whether the given IP is allowed to make a request.
     *
     * Returns true if allowed (and consumes a token); false if rate-limited.
     */
    public function isAllowed(string $ip): bool
    {
        $this->pruneIfNeeded();

        if (! isset($this->buckets[$ip])) {
            // First request from this IP — initialize bucket full
            $this->buckets[$ip] = [
                'tokens' => (float) ($this->maxTokens - 1), // Consume 1 token
                'lastRefill' => $this->now(),
            ];

            return true;
        }

        $bucket = &$this->buckets[$ip];
        $now = $this->now();

        // Refill tokens based on elapsed time
        $elapsed = $now - $bucket['lastRefill'];
        $newTokens = min(
            (float) $this->maxTokens,
            $bucket['tokens'] + ($elapsed * $this->refillRate),
        );

        if ($newTokens < 1.0) {
            // Not enough tokens — rate limited
            $bucket['tokens'] = $newTokens;

            return false;
        }

        // Consume a token and allow
        $bucket['tokens'] = $newTokens - 1.0;
        $bucket['lastRefill'] = $now;

        return true;
    }

    /**
     * Get the number of seconds until this IP can make another request.
     *
     * Returns 0 if the IP is not currently being tracked or is already allowed.
     */
    public function getRetryAfterSeconds(string $ip): int
    {
        if (! isset($this->buckets[$ip])) {
            return 0;
        }

        $bucket = $this->buckets[$ip];
        $tokens = $bucket['tokens'];

        if ($tokens >= 1.0) {
            return 0;
        }

        // Time needed to accumulate 1 full token
        $tokensNeeded = 1.0 - $tokens;
        $seconds = (int) ceil($tokensNeeded / $this->refillRate);

        return max(1, $seconds);
    }

    /**
     * Get the current timestamp in seconds with microsecond precision.
     */
    private function now(): float
    {
        return microtime(true);
    }

    /**
     * Prune stale buckets older than 2× the window to prevent memory leaks.
     *
     * Runs at most once per window duration.
     */
    private function pruneIfNeeded(): void
    {
        $now = $this->now();

        // Only prune once per window
        if ($now - $this->lastPrune < $this->windowSeconds) {
            return;
        }

        $this->lastPrune = $now;
        $cutoff = $now - ($this->windowSeconds * 2);

        foreach ($this->buckets as $ip => $bucket) {
            if ($bucket['lastRefill'] < $cutoff) {
                unset($this->buckets[$ip]);
            }
        }
    }
}
