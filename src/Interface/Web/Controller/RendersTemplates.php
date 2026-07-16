<?php

declare(strict_types=1);

namespace OrderHub\Interface\Web\Controller;

use OrderHub\Interface\Web\Http\Session;
use OrderHub\Interface\Web\Http\WebResponse;
use Twig\Environment;

/**
 * Every Web controller renders Twig templates the same way: pull the pending
 * flash messages (set-once, shown-once) and the authenticated state into the
 * view context, so templates never poke at the session directly.
 */
trait RendersTemplates
{
    /**
     * @param array<string, mixed> $data
     */
    private function render(Environment $twig, Session $session, string $template, array $data = [], int $status = 200): WebResponse
    {
        return WebResponse::html($twig->render($template, [
            ...$data,
            'flashes' => $session->pullFlashes(),
            'isAuthenticated' => $session->has('user_id'),
            'stores' => $session->get('stores', []),
            'activeTenantId' => $session->get('tenant_id'),
        ]), $status);
    }
}
