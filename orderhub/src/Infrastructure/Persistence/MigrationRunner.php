<?php

declare(strict_types=1);

namespace OrderHub\Infrastructure\Persistence;

/**
 * Applies the SQL migration files in lexical order. The migrations themselves
 * are idempotent (CREATE TABLE IF NOT EXISTS), which keeps the runner trivial
 * and safe to invoke on every container start.
 */
final class MigrationRunner
{
    public function __construct(private readonly Database $database)
    {
    }

    /**
     * @return list<string> names of the migration files applied
     */
    public function up(): array
    {
        $dir = __DIR__ . '/migrations';
        $files = glob($dir . '/*.sql');
        if ($files === false) {
            return [];
        }
        sort($files);

        $applied = [];
        $pdo = $this->database->pdo();
        foreach ($files as $file) {
            $sql = file_get_contents($file);
            if ($sql === false) {
                continue;
            }
            $pdo->exec($sql);
            $applied[] = basename($file);
        }

        return $applied;
    }
}
