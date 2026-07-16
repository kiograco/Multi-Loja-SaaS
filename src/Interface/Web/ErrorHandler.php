<?php

declare(strict_types=1);

namespace OrderHub\Interface\Web;

use OrderHub\Application\Exceptions\AuthorizationException;
use OrderHub\Application\Exceptions\InvoiceNotReadyException;
use OrderHub\Domain\Shared\Exceptions\AggregateNotFoundException;
use OrderHub\Domain\Shared\Exceptions\ConcurrencyException;
use OrderHub\Domain\Shared\Exceptions\DomainException;
use OrderHub\Interface\Web\Http\WebResponse;
use Throwable;
use Twig\Environment;

/**
 * Web counterpart of Api\ErrorHandler: turns thrown exceptions into an HTML
 * error page instead of the API's JSON envelope, since this channel talks to
 * a browser, not an HTTP client expecting `{"error": {...}}`.
 */
final class ErrorHandler
{
    public function __construct(
        private readonly Environment $twig,
        private readonly bool $debug = false,
    ) {
    }

    public function toResponse(Throwable $e): WebResponse
    {
        [$status, $title] = $this->classify($e);

        $message = $status >= 500 && !$this->debug
            ? 'Ocorreu um erro inesperado.'
            : $e->getMessage();

        return WebResponse::html($this->twig->render('errors/generic.html.twig', [
            'status' => $status,
            'title' => $title,
            'message' => $message,
        ]), $status);
    }

    /**
     * @return array{0: int, 1: string}
     */
    private function classify(Throwable $e): array
    {
        return match (true) {
            $e instanceof InvoiceNotReadyException => [404, 'Nota fiscal ainda não disponível'],
            $e instanceof AggregateNotFoundException => [404, 'Não encontrado'],
            $e instanceof AuthorizationException => [403, 'Acesso negado'],
            $e instanceof ConcurrencyException => [409, 'Conflito de concorrência'],
            $e instanceof DomainException => [422, 'Requisição inválida'],
            default => [500, 'Erro interno'],
        };
    }
}
