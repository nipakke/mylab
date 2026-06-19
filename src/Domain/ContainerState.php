<?php

declare(strict_types=1);

namespace Shipped\Backend\Domain;

enum ContainerState: string
{
    case Running = 'running';
    case Exited = 'exited';
    case Paused = 'paused';
    case Dead = 'dead';
    case Created = 'created';
    case Restarting = 'restarting';
    case Removing = 'removing';

    /**
     * Return a daisyUI badge CSS class for the container state.
     *
     * Used by templates to render state-appropriate badge styling
     * without hardcoding CSS class strings in Twig.
     */
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

    /**
     * Return a Tailwind background colour class for the container state dot.
     *
     * Uses daisyUI semantic colour utilities (bg-success, bg-error, etc.)
     * which are simpler and more reliable than the daisyUI status component.
     */
    public function statusDotClass(): string
    {
        return match ($this) {
            self::Running => 'bg-success',
            self::Exited, self::Dead => 'bg-error',
            self::Paused => 'bg-warning',
            self::Restarting => 'bg-info',
            self::Created, self::Removing => 'bg-neutral',
        };
    }

    /**
     * Resolve the status dot class from a state string and optional
     * Docker health-check string.
     *
     * If the Docker Status field contains "(unhealthy)", the dot
     * shows warning regardless of the container state — a running
     * container with a failing health check is effectively degraded.
     *
     * @param  string  $state         Docker State field (running, exited, …)
     * @param  string  $dockerStatus  Docker Status field (Up 2 hours (healthy), …)
     */
    public static function resolveDotClass(string $state, string $dockerStatus): string
    {
        $stateEnum = self::tryFrom($state);

        if ($stateEnum === self::Running && str_contains($dockerStatus, '(unhealthy)')) {
            return 'bg-warning';
        }

        return $stateEnum?->statusDotClass() ?? 'bg-neutral';
    }
}
