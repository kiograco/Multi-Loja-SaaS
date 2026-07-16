<?php

declare(strict_types=1);

namespace OrderHub\Application\Auth;

/**
 * Port for issuing and verifying bearer tokens. The application deals in claims;
 * the JWT specifics live in an infrastructure adapter.
 */
interface TokenIssuer
{
    /**
     * @param array<string, mixed> $claims
     */
    public function issue(array $claims): string;

    /**
     * @return array<string, mixed> the verified claims
     *
     * @throws \OrderHub\Application\Exceptions\AuthenticationException on any invalid/expired token
     */
    public function verify(string $token): array;
}
