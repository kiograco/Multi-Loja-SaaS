<?php

declare(strict_types=1);

namespace OrderHub\Infrastructure\Persistence;

use OrderHub\Domain\User\User;
use OrderHub\Domain\User\UserId;
use OrderHub\Domain\User\UserRepository;

final class PostgresUserRepository implements UserRepository
{
    public function __construct(private readonly Database $database)
    {
    }

    public function save(User $user): void
    {
        $stmt = $this->database->pdo()->prepare(
            'INSERT INTO users (id, email, password_hash)
             VALUES (:id, :email, :hash)
             ON CONFLICT (id) DO UPDATE SET email = EXCLUDED.email, password_hash = EXCLUDED.password_hash'
        );
        $stmt->execute([
            'id' => $user->id->value,
            'email' => $user->email,
            'hash' => $user->passwordHash(),
        ]);
    }

    public function findByEmail(string $email): ?User
    {
        $stmt = $this->database->pdo()->prepare('SELECT * FROM users WHERE email = :email');
        $stmt->execute(['email' => $email]);
        $row = $stmt->fetch();

        return $row === false ? null : $this->hydrate($row);
    }

    public function findById(UserId $id): ?User
    {
        $stmt = $this->database->pdo()->prepare('SELECT * FROM users WHERE id = :id');
        $stmt->execute(['id' => $id->value]);
        $row = $stmt->fetch();

        return $row === false ? null : $this->hydrate($row);
    }

    /**
     * @param array{id: string, email: string, password_hash: string} $row
     */
    private function hydrate(array $row): User
    {
        return new User(
            UserId::fromString($row['id']),
            $row['email'],
            $row['password_hash'],
        );
    }
}
