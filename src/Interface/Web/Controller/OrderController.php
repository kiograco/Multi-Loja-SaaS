<?php

declare(strict_types=1);

namespace OrderHub\Interface\Web\Controller;

use OrderHub\Application\Bus\CommandBus;
use OrderHub\Application\Bus\QueryBus;
use OrderHub\Application\Command\CancelOrder\CancelOrderCommand;
use OrderHub\Application\Command\DeliverOrder\DeliverOrderCommand;
use OrderHub\Application\Command\PayOrder\PayOrderCommand;
use OrderHub\Application\Command\ShipOrder\ShipOrderCommand;
use OrderHub\Application\Query\GetOrderEventTimeline\GetOrderEventTimelineQuery;
use OrderHub\Application\Query\GetOrderInvoice\GetOrderInvoiceQuery;
use OrderHub\Application\Query\GetOrderSummary\GetOrderSummaryQuery;
use OrderHub\Application\Query\ListOrders\ListOrdersQuery;
use OrderHub\Application\ReadModel\OrderSummary;
use OrderHub\Interface\Web\Http\Session;
use OrderHub\Interface\Web\Http\WebRequest;
use OrderHub\Interface\Web\Http\WebResponse;
use Twig\Environment;

/**
 * The one controller that shows HTMX in action: pay/ship/cancel return just
 * the `_status_panel` fragment for an HX-Request, or redirect back to the
 * order page otherwise (progressive enhancement without JS). Reuses the exact
 * same Command/QueryBus as the JSON API — no business rule lives here.
 */
final class OrderController
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

    public function list(WebRequest $request): WebResponse
    {
        $tenantId = $this->currentUser($request)->tenantId();
        $status = $request->query('status');
        $perPage = (int) ($request->query('perPage') ?? '20');

        $result = $this->queryBus->ask(new ListOrdersQuery(
            $tenantId,
            $status !== '' ? $status : null,
            (int) ($request->query('page') ?? '1'),
            $perPage,
        ));

        return $this->render($this->twig, $this->session, 'orders/list.html.twig', [
            'orders' => $result['data'],
            'meta' => $result['meta'],
            'status' => $status,
            'perPage' => $perPage,
        ]);
    }

    public function detail(WebRequest $request): WebResponse
    {
        $tenantId = $this->currentUser($request)->tenantId();
        $id = (string) $request->attribute('id');

        $order = $this->queryBus->ask(new GetOrderSummaryQuery($tenantId, $id));
        $timeline = $this->queryBus->ask(new GetOrderEventTimelineQuery($tenantId, $id));

        return $this->render($this->twig, $this->session, 'orders/detail.html.twig', [
            'order' => $order,
            'timeline' => $timeline,
        ]);
    }

    public function pay(WebRequest $request): WebResponse
    {
        $tenantId = $this->currentUser($request)->tenantId();
        $id = (string) $request->attribute('id');

        $this->commandBus->dispatch(new PayOrderCommand(
            $tenantId,
            $id,
            (string) $request->input('paymentMethod', 'pix'),
        ));

        return $this->statusPanelOrRedirect($request, $tenantId, $id, 'Pedido marcado como pago.');
    }

    public function ship(WebRequest $request): WebResponse
    {
        $tenantId = $this->currentUser($request)->tenantId();
        $id = (string) $request->attribute('id');

        $this->commandBus->dispatch(new ShipOrderCommand(
            $tenantId,
            $id,
            (string) $request->input('trackingCode', ''),
        ));

        return $this->statusPanelOrRedirect($request, $tenantId, $id, 'Pedido marcado como enviado.');
    }

    public function deliver(WebRequest $request): WebResponse
    {
        $tenantId = $this->currentUser($request)->tenantId();
        $id = (string) $request->attribute('id');

        $this->commandBus->dispatch(new DeliverOrderCommand($tenantId, $id));

        return $this->statusPanelOrRedirect($request, $tenantId, $id, 'Pedido marcado como entregue.');
    }

    public function cancel(WebRequest $request): WebResponse
    {
        $tenantId = $this->currentUser($request)->tenantId();
        $id = (string) $request->attribute('id');

        $this->commandBus->dispatch(new CancelOrderCommand(
            $tenantId,
            $id,
            (string) $request->input('reason', 'cancelado pelo lojista'),
        ));

        return $this->statusPanelOrRedirect($request, $tenantId, $id, 'Pedido cancelado.');
    }

    public function invoice(WebRequest $request): WebResponse
    {
        $tenantId = $this->currentUser($request)->tenantId();
        $id = (string) $request->attribute('id');

        $pdf = $this->queryBus->ask(new GetOrderInvoiceQuery($tenantId, $id));

        return WebResponse::pdf($pdf, 'pedido-' . $id . '.pdf');
    }

    private function statusPanelOrRedirect(WebRequest $request, string $tenantId, string $id, string $successMessage): WebResponse
    {
        /** @var OrderSummary $order */
        $order = $this->queryBus->ask(new GetOrderSummaryQuery($tenantId, $id));

        if ($request->isHtmxRequest()) {
            return WebResponse::html($this->twig->render('orders/_status_panel.html.twig', ['order' => $order]));
        }

        $this->session->flash('success', $successMessage);

        return WebResponse::redirect('/app/orders/' . $id);
    }
}
