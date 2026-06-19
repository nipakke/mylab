<?php
declare(strict_types=1);

use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Shipped\Backend\DTO\Container;

beforeEach(function () {
    // Instantiate isolated mock rendering parameters before each run block
    $this->loader = new FilesystemLoader(__DIR__ . '/../src/views');
    $this->twig = new Environment($this->loader, [
        'cache' => false,
        'strict_variables' => true // Assures properties match exact DTO schema models
    ]);
});

test('the system model maps properties into types successfully', function () {
    $container = new Container('id-999', 'worker-node', 'running', 104857600);
    
    expect($container->id)->toBeString()
        ->and($container->name)->toBe('worker-node')
        ->and($container->getFormattedMemory())->toBe('100 MB');
});

test('the partial template loop crashes explicitly if strict type variables are missing', function () {
    // Intentionally omitting 'containers' structure from array variables collection mapping
    expect(fn() => $this->twig->render('partials/container-rows.twig', []))
        ->toThrow(\Twig\Error\RuntimeError::class);
});

test('the layout components parse strictly validated elements into safe output blocks', function () {
    $container = new Container('sha-test', 'load-balancer', 'stopped', 0);

    $html = $this->twig->render('partials/container-rows.twig', [
        'containers' => [$container]
    ]);

    expect($html)->toContain('load-balancer')
        ->and($html)->toContain('badge-error')
        ->and($html)->not->toContain('No active nodes detected.');
});
