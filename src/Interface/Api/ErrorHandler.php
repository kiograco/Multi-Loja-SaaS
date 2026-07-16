<?php

declare(strict_types=1);

namespace OrderHub\Interface\Api;

use OrderHub\Application\Exceptions\AuthenticationException;
use OrderHub\Application\Exceptions\AuthorizationException;
use OrderHub\Application\Exceptions\ConflictException;
use OrderHub\Application\Exceptions\InvoiceNotReadyException;
use OrderHub\Domain\Order\Exceptions\InvalidOrderException;
use OrderHub\Domain\Product\Exceptions\InvalidProductException;
use OrderHub\Domain\Shared\Exceptions\AggregateNotFoundException;
use OrderHub\Domain\Shared\Exceptions\ConcurrencyException;
use OrderHub\Domain\Shared\Exceptions\DomainException;
use OrderHub\Domain\Shared\Exceptions\InvalidMoneyException;
use OrderHub\Domain\Shared\Exceptions\InvalidUuidException;
use OrderHub\Domain\Tenant\Exceptions\InvalidTenantException;
use OrderHub\Interface\Api\Exceptions\ValidationException;
use OrderHub\Interface\Api\Http\Response;
use Throwable;

/**
 * Single place that turns any thrown exception into the API's uniform error
 * envelope `{"error":{"code","message"}}` with an appropriate HTTP status.
 * Unknown exceptions become a generic 500 so internals never leak.
 */
final class ErrorHandler
{
    public function __construct(private readonly bool $debug = false)
    {
    }

    public function toResponse(Throwable $e): Response
    {
        [$status, $code] = $this->classify($e);

        $message = $status >= 500 && !$this->debug
            ? 'An unexpected error occurred.'
            : $e->getMessage();

        return Response::error($code, $message, $status);
    }

    /**
     * @return array{0: int, 1: string}
     */
    private function classify(Throwable $e): array
    {
        return match (true) {
            $e instanceof ValidationException => [422, 'VALIDATION_ERROR'],
            $e instanceof AuthenticationException => [401, 'UNAUTHENTICATED'],
            $e instanceof AuthorizationException => [403, 'FORBIDDEN'],
            $e instanceof ConflictException => [409, 'CONFLICT'],
            $e instanceof InvoiceNotReadyException => [404, 'INVOICE_NOT_READY'],
            $e instanceof AggregateNotFoundException => [404, 'NOT_FOUND'],
            $e instanceof ConcurrencyException => [409, 'CONCURRENCY_CONFLICT'],
            $e instanceof InvalidUuidException,
            $e instanceof InvalidMoneyException,
            $e instanceof InvalidOrderException,
            $e instanceof InvalidProductException,
            $e instanceof InvalidTenantException => [422, 'INVALID_INPUT'],
            // Remaining domain exceptions are business-rule / state violations.
            $e instanceof DomainException => [409, 'DOMAIN_RULE_VIOLATION'],
            default => [500, 'INTERNAL_ERROR'],
        };
    }
}
