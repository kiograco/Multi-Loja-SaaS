<?php

declare(strict_types=1);

namespace OrderHub\Tests\Integration\Api;

use OrderHub\Application\Command\RegisterUser\RegisterUserCommand;

/**
 * GenerateInvoicePdfJob already rendered a real PDF to var/invoices on every
 * PaymentReceived, but nothing served it back — this covers the new
 * GET /orders/{id}/invoice route that closes that gap, including the
 * not-ready-yet state before the async worker has processed the job.
 */
final class OrderInvoiceApiTest extends ApiTestCase
{
    private const PREFIX = '/api/v1';

    /**
     * @return array{token: string, orderId: string}
     */
    private function paidOrder(string $email): array
    {
        $this->container->commandBus()->dispatch(new RegisterUserCommand($email, 'secret123'));
        $token = (string) $this->decode($this->request('POST', self::PREFIX . '/auth/login', [
            'email' => $email,
            'password' => 'secret123',
        ]))['token'];
        $this->request('POST', self::PREFIX . '/tenants', ['store_name' => 'Invoice Shop'], $token);
        $token = (string) $this->decode($this->request('POST', self::PREFIX . '/auth/login', [
            'email' => $email,
            'password' => 'secret123',
        ]))['token'];

        $productId = (string) $this->decode($this->request('POST', self::PREFIX . '/products', [
            'name' => 'Widget',
            'priceCents' => 5000,
            'stockQuantity' => 10,
        ], $token))['id'];

        $orderId = (string) $this->decode($this->request('POST', self::PREFIX . '/orders', [
            'customerName' => 'Ada Lovelace',
            'customerEmail' => 'ada@example.com',
            'items' => [['productId' => $productId, 'quantity' => 1]],
        ], $token))['id'];

        $this->request('POST', self::PREFIX . "/orders/{$orderId}/pay", ['paymentMethod' => 'pix'], $token);

        return ['token' => $token, 'orderId' => $orderId];
    }

    public function testInvoiceIsNotReadyBeforeTheWorkerProcessesTheJob(): void
    {
        ['token' => $token, 'orderId' => $orderId] = $this->paidOrder('invoice-not-ready@shop.test');

        $response = $this->request('GET', self::PREFIX . "/orders/{$orderId}/invoice", [], $token);

        self::assertSame(404, $response->status);
        self::assertSame('INVOICE_NOT_READY', $this->decode($response)['error']['code']);
    }

    public function testInvoiceDownloadsAsPdfOnceTheWorkerHasProcessedIt(): void
    {
        ['token' => $token, 'orderId' => $orderId] = $this->paidOrder('invoice-ready@shop.test');

        $this->container->worker()->run(4);

        $response = $this->request('GET', self::PREFIX . "/orders/{$orderId}/invoice", [], $token);

        self::assertSame(200, $response->status);
        self::assertSame('application/pdf', $response->headers['content-type']);
        self::assertStringStartsWith('%PDF-1.4', $response->body);
    }

    public function testInvoiceOfAnotherTenantsOrderIsNotFound(): void
    {
        ['orderId' => $orderId] = $this->paidOrder('invoice-owner@shop.test');
        $this->container->worker()->run(4);

        $this->container->commandBus()->dispatch(new RegisterUserCommand('invoice-intruder@shop.test', 'secret123'));
        $intruderToken = (string) $this->decode($this->request('POST', self::PREFIX . '/auth/login', [
            'email' => 'invoice-intruder@shop.test',
            'password' => 'secret123',
        ]))['token'];
        $this->request('POST', self::PREFIX . '/tenants', ['store_name' => 'Intruder Shop'], $intruderToken);
        $intruderToken = (string) $this->decode($this->request('POST', self::PREFIX . '/auth/login', [
            'email' => 'invoice-intruder@shop.test',
            'password' => 'secret123',
        ]))['token'];

        $response = $this->request('GET', self::PREFIX . "/orders/{$orderId}/invoice", [], $intruderToken);

        self::assertSame(404, $response->status);
        self::assertSame('NOT_FOUND', $this->decode($response)['error']['code']);
    }
}
