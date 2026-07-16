<?php

declare(strict_types=1);

namespace OrderHub\Infrastructure\Persistence;

use OrderHub\Infrastructure\Config\Env;
use PDO;

/**
 * Owns the single PDO connection. Configured for strict, exception-throwing
 * behaviour so silent SQL failures cannot slip through.
 */
final class Database
{
    private ?PDO $pdo = null;

    public function __construct(
        private readonly string $dsn,
        private readonly string $user,
        private readonly string $password,
    ) {
    }

    public static function fromEnv(): self
    {
        return new self(
            Env::get('DB_DSN'),
            Env::get('DB_USER'),
            Env::get('DB_PASSWORD'),
        );
    }

    public function pdo(): PDO
    {
        if ($this->pdo === null) {
            $this->pdo = new PDO($this->dsn, $this->user, $this->password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        }

        return $this->pdo;
    }
}
