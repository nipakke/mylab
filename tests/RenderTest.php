<?php

declare(strict_types=1);

use MyLab\Domain\Container;
use Twig\Environment;
use Twig\Error\RuntimeError;
use Twig\Loader\FilesystemLoader;

beforeEach(function () {
    // Instantiate isolated mock rendering parameters before each run block
    $this->loader = new FilesystemLoader(__DIR__.'/../views');
    $this->twig = new Environment($this->loader, [
        'cache' => false,
        'strict_variables' => true, // Assures properties match exact DTO schema models
    ]);
});

test('the system model maps properties into types successfully', function () {
    $container = new Container('id-999', 'worker-node', 'running');

    expect($container->id)->toBeString()
        ->and($container->name)->toBe('worker-node');
});

test('the partial template loop crashes explicitly if strict type variables are missing', function () {
    // Intentionally omitting 'containers' structure from array variables collection mapping
    expect(fn () => $this->twig->render('partials/container-rows.twig', []))
        ->toThrow(RuntimeError::class);
});

test('the layout components parse strictly validated elements into safe output blocks', function () {
    $container = new Container('sha-test', 'load-balancer', 'exited');

    $html = $this->twig->render('partials/container-rows.twig', [
        'containers' => [$container],
    ]);

    expect($html)->toContain('load-balancer')
        ->and($html)->toContain('badge-error')
        ->and($html)->not->toContain('No active nodes detected.');
});
