<?php

declare(strict_types=1);

namespace OrderHub\Interface\Web\Controller;

use OrderHub\Application\Bus\CommandBus;
use OrderHub\Application\Bus\QueryBus;
use OrderHub\Application\Command\TestWebhook\TestWebhookCommand;
use OrderHub\Application\Command\UpdateTenantSettings\UpdateTenantSettingsCommand;
use OrderHub\Application\Exceptions\ConflictException;
use OrderHub\Application\Query\GetTenantSettings\GetTenantSettingsQuery;
use OrderHub\Application\Query\ListWebhookDeliveries\ListWebhookDeliveriesQuery;
use OrderHub\Domain\Shared\Exceptions\DomainException;
use OrderHub\Interface\Web\Http\Session;
use OrderHub\Interface\Web\Http\WebRequest;
use OrderHub\Interface\Web\Http\WebResponse;
use Twig\Environment;

/**
 * Exposes Tenant::rename()/configureWebhook() — reachable from the domain and
 * from CreateTenantCommand at signup, but until now with no way to change
 * them afterwards from either channel. Also surfaces the webhook delivery
 * history (Seção 18): before this, a failure only ever reached the server log.
 */
final class SettingsController
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

    public function edit(WebRequest $request): WebResponse
    {
        $tenantId = $this->currentUser($request)->tenantId();

        $settings = $this->queryBus->ask(new GetTenantSettingsQuery($tenantId));
        $deliveries = $this->queryBus->ask(new ListWebhookDeliveriesQuery($tenantId));

        return $this->render($this->twig, $this->session, 'settings/index.html.twig', [
            'settings' => $settings,
            'deliveries' => $deliveries,
            'errors' => [],
        ]);
    }

    public function update(WebRequest $request): WebResponse
    {
        $tenantId = $this->currentUser($request)->tenantId();

        $storeName = trim((string) $request->input('storeName', ''));
        $webhookUrl = trim((string) $request->input('webhookUrl', ''));

        try {
            $this->commandBus->dispatch(new UpdateTenantSettingsCommand(
                $tenantId,
                $storeName,
                $webhookUrl !== '' ? $webhookUrl : null,
            ));
        } catch (DomainException $e) {
            return $this->render($this->twig, $this->session, 'settings/index.html.twig', [
                'settings' => [
                    'id' => $tenantId,
                    'storeName' => $storeName,
                    'webhookUrl' => $webhookUrl !== '' ? $webhookUrl : null,
                ],
                'deliveries' => $this->queryBus->ask(new ListWebhookDeliveriesQuery($tenantId)),
                'errors' => [$e->getMessage()],
            ], 422);
        }

        $this->session->flash('success', 'Configurações da loja atualizadas com sucesso.');

        return WebResponse::redirect('/app/settings');
    }

    public function testWebhook(WebRequest $request): WebResponse
    {
        $tenantId = $this->currentUser($request)->tenantId();

        try {
            $result = $this->commandBus->dispatch(new TestWebhookCommand($tenantId));
            $this->session->flash(
                $result['success'] ? 'success' : 'error',
                $result['success']
                    ? \sprintf('Webhook de teste entregue com sucesso (HTTP %d).', $result['responseCode'])
                    : \sprintf('Falha ao entregar o webhook de teste: %s', $result['error']),
            );
        } catch (ConflictException $e) {
            $this->session->flash('error', $e->getMessage());
        }

        return WebResponse::redirect('/app/settings');
    }
}
