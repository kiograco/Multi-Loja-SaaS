<?php

declare(strict_types=1);

namespace OrderHub\Tests\Unit\Domain\Tenant;

use DateTimeImmutable;
use OrderHub\Domain\Tenant\Exceptions\InvalidTenantException;
use OrderHub\Domain\Tenant\Tenant;
use OrderHub\Domain\Tenant\TenantId;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class TenantTest extends TestCase
{
    private function tenant(?string $webhook = null): Tenant
    {
        return new Tenant(
            TenantId::generate(),
            Uuid::uuid4()->toString(),
            'Loja do Zé',
            new DateTimeImmutable(),
            $webhook,
        );
    }

    public function testRejectsBlankStoreName(): void
    {
        $this->expectException(InvalidTenantException::class);
        new Tenant(TenantId::generate(), Uuid::uuid4()->toString(), '  ', new DateTimeImmutable());
    }

    public function testWebhookIsOptional(): void
    {
        self::assertFalse($this->tenant()->hasWebhook());
    }

    public function testValidWebhookIsStored(): void
    {
        $tenant = $this->tenant('https://example.com/hooks/orders');
        self::assertTrue($tenant->hasWebhook());
        self::assertSame('https://example.com/hooks/orders', $tenant->webhookUrl());
    }

    public function testInvalidWebhookIsRejected(): void
    {
        $this->expectException(InvalidTenantException::class);
        $this->tenant('not a url');
    }
}
