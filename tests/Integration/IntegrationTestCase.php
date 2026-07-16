<?php

declare(strict_types=1);

namespace OrderHub\Tests\Integration;

use OrderHub\Infrastructure\Persistence\Database;
use OrderHub\Infrastructure\Persistence\MigrationRunner;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Base for tests that hit the real Postgres. Migrations run once per process;
 * every test starts from truncated tables so cases stay isolated.
 */
abstract class IntegrationTestCase extends TestCase
{
    private static bool $migrated = false;
    protected Database $database;

    protected function setUp(): void
    {
        $this->database = Database::fromEnv();

        if (!self::$migrated) {
            (new MigrationRunner($this->database))->up();
            self::$migrated = true;
        }

        $this->truncateAll();
    }

    protected function pdo(): PDO
    {
        return $this->database->pdo();
    }

    private function truncateAll(): void
    {
        $this->database->pdo()->exec(
            'TRUNCATE event_store, order_summary_projection, daily_sales_projection,
                      top_products_projection, products, tenants, users, processed_jobs
             RESTART IDENTITY CASCADE'
        );
    }
}
