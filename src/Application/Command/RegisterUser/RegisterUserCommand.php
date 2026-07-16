<?php

declare(strict_types=1);

namespace OrderHub\Application\Command\RegisterUser;

use OrderHub\Application\Bus\Command;

final readonly class RegisterUserCommand implements Command
{
    public function __construct(
        public string $email,
        public string $plainPassword,
    ) {
    }
}
