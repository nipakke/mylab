<?php

declare(strict_types=1);

namespace Shipped\Backend\Action;

use React\Promise\PromiseInterface;
use Shipped\Backend\Service\ContainerStore;
use Twig\Environment;

use function React\Promise\resolve;

/**
 * Orchestrates the full page render for GET /.
 *
 * Reads the latest container list from the in-memory ContainerStore
 * (populated by the periodic CollectContainers background job) and
 * renders the home page via Twig.  Returns a resolved PromiseInterface
 * so the handler chain remains async-compatible.
 */
final class ListContainers
{
    public function __construct(
        private readonly ContainerStore $store,
        private readonly Environment $twig,
    ) {}

    /**
     * Execute the read → render pipeline.
     *
     * @return PromiseInterface<string> Resolves to the rendered HTML string
     */
    public function handle(): PromiseInterface
    {
        return resolve($this->twig->render('home.twig', [
            'title' => 'Core Command Panel',
            'containers' => $this->store->getAll(),
        ]));
    }
}
