<?php

declare(strict_types=1);

namespace OrderHub\Interface\Web\Controller;

use OrderHub\Application\Bus\CommandBus;
use OrderHub\Application\Bus\QueryBus;
use OrderHub\Application\Command\RebuildProjection\RebuildProjectionCommand;
use OrderHub\Application\Command\RetryDeadLetterQueue\RetryDeadLetterQueueCommand;
use OrderHub\Application\Query\ListProjectionNames\ListProjectionNamesQuery;
use OrderHub\Application\Query\ReplayOrder\ReplayOrderQuery;
use OrderHub\Domain\Shared\Exceptions\AggregateNotFoundException;
use OrderHub\Interface\Web\Http\Session;
use OrderHub\Interface\Web\Http\WebRequest;
use OrderHub\Interface\Web\Http\WebResponse;
use Twig\Environment;

/**
 * Web home for `order:replay`, `queue:retry-dlq` and `projection:rebuild` —
 * deliberately CLI-only per Seção 19 ("ops tools for whoever operates the
 * infrastructure, not the store owner"; retry-dlq and rebuild act across every
 * tenant on the instance, not just the caller's store). Exposed here anyway at
 * explicit user request. Every action still requires a logged-in session
 * (there is no separate admin role in this app), and the two instance-wide
 * actions require an extra typed confirmation in the template on top of the
 * usual browser confirm().
 */
final class OpsController
{
    use CurrentUser;
    use RendersTemplates;

    public function __construct(
        private readonly CommandBus $commandBus,
        private readonly QueryBus $queryBus,
        private readonly Session $session,
        private readonly Environment $twig,
    ) {
    }

    public function index(WebRequest $request): WebResponse
    {
        return $this->render($this->twig, $this->session, 'ops/index.html.twig', [
            'projectionNames' => $this->queryBus->ask(new ListProjectionNamesQuery()),
            'replayResult' => null,
            'replayError' => null,
            'replayOrderId' => '',
        ]);
    }

    public function replay(WebRequest $request): WebResponse
    {
        $tenantId = $this->currentUser($request)->tenantId();
        $orderId = trim((string) $request->input('orderId', ''));

        $replayResult = null;
        $replayError = null;

        if ($orderId === '') {
            $replayError = 'Informe o ID do pedido.';
        } else {
            try {
                $replayResult = $this->queryBus->ask(new ReplayOrderQuery($tenantId, $orderId));
            } catch (AggregateNotFoundException) {
                // Tenant-scoped, same as everywhere else: an order from another
                // tenant (or an unknown id) is simply "not found" here too.
                $replayError = 'Pedido não encontrado nesta loja.';
            }
        }

        return $this->render($this->twig, $this->session, 'ops/index.html.twig', [
            'projectionNames' => $this->queryBus->ask(new ListProjectionNamesQuery()),
            'replayResult' => $replayResult,
            'replayError' => $replayError,
            'replayOrderId' => $orderId,
        ], $replayError !== null ? 422 : 200);
    }

    public function retryDeadLetterQueue(WebRequest $request): WebResponse
    {
        $count = $this->commandBus->dispatch(new RetryDeadLetterQueueCommand());

        $this->session->flash('success', \sprintf('%d job(s) reenfileirados da dead-letter queue.', $count));

        return WebResponse::redirect('/app/ops');
    }

    public function rebuildProjection(WebRequest $request): WebResponse
    {
        $name = trim((string) $request->input('name', ''));
        /** @var list<string> $known */
        $known = $this->queryBus->ask(new ListProjectionNamesQuery());

        if ($name !== 'all' && !\in_array($name, $known, true)) {
            $this->session->flash('error', 'Projeção desconhecida.');

            return WebResponse::redirect('/app/ops');
        }

        $rebuilt = $this->commandBus->dispatch(new RebuildProjectionCommand($name));

        $this->session->flash('success', \sprintf('Projeções reconstruídas: %s.', implode(', ', $rebuilt)));

        return WebResponse::redirect('/app/ops');
    }
}
