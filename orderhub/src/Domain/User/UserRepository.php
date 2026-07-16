<?php

declare(strict_types=1);

namespace OrderHub\Domain\User;

interface UserRepository
{
    public function save(User $user): void;

    public function findByEmail(string $email): ?User;

    public function findById(UserId $id): ?User;
}
