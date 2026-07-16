<?php

declare(strict_types=1);

namespace OrderHub\Domain\User;

/**
 * A platform account that can own one or more tenants. Passwords are stored as
 * a hash produced by the infrastructure layer; the domain only verifies.
 */
final class User
{
    public function __construct(
        public readonly UserId $id,
        public readonly string $email,
        private readonly string $passwordHash,
    ) {
    }

    public function verifyPassword(string $plainPassword): bool
    {
        return password_verify($plainPassword, $this->passwordHash);
    }

    public function passwordHash(): string
    {
        return $this->passwordHash;
    }
}
