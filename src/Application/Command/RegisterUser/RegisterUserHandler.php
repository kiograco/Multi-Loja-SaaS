<?php

declare(strict_types=1);

namespace OrderHub\Application\Command\RegisterUser;

use OrderHub\Application\Exceptions\ConflictException;
use OrderHub\Domain\User\User;
use OrderHub\Domain\User\UserId;
use OrderHub\Domain\User\UserRepository;

final class RegisterUserHandler
{
    public function __construct(private readonly UserRepository $users)
    {
    }

    public function __invoke(RegisterUserCommand $command): string
    {
        if ($this->users->findByEmail($command->email) !== null) {
            throw ConflictException::because(\sprintf('A user with e-mail "%s" already exists.', $command->email));
        }

        $user = new User(
            UserId::generate(),
            $command->email,
            password_hash($command->plainPassword, \PASSWORD_BCRYPT),
        );
        $this->users->save($user);

        return $user->id->value;
    }
}
