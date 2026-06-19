<?php
declare(strict_types=1);

namespace Shipped\Backend\Workflow;

use React\Promise\PromiseInterface;
use Shipped\Backend\Service\DockerServiceInterface;
use Twig\Environment;

/**
 * Orchestrates the full page render for GET /.
 *
 * Pipeline: fetch containers from Docker → hydrate to DTOs → render via Twig.
 * Fully async — returns a PromiseInterface that integrates with the ReactPHP event loop.
 */
final class ListContainersWorkflow
{
    public function __construct(
        private readonly DockerServiceInterface $docker,
        private readonly Environment $twig,
    ) {}

    /**
     * Execute the fetch → render pipeline.
     *
     * @return PromiseInterface<string> Resolves to the rendered HTML string
     */
    public function handle(): PromiseInterface
    {
        return $this->docker->listAll()->then(
            function (array $containers): string {
                return $this->twig->render('home.twig', [
                    'title'      => 'Core Command Panel',
                    'containers' => $containers,
                ]);
            },
            fn(\Throwable $error): string => $this->twig->render('home.twig', [
                'title'      => 'Core Command Panel',
                'containers' => [],
            ]),
        );
    }
}
