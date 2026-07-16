<?php

declare(strict_types=1);

namespace OrderHub\Infrastructure\Auth;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use OrderHub\Application\Auth\TokenIssuer;
use OrderHub\Application\Exceptions\AuthenticationException;
use Throwable;

/**
 * JWT adapter (HS256) built on firebase/php-jwt. Translates any verification
 * failure into the application's AuthenticationException so callers never catch
 * library-specific types.
 */
final class FirebaseJwtTokenIssuer implements TokenIssuer
{
    private const ALGORITHM = 'HS256';

    public function __construct(private readonly string $secret)
    {
    }

    public function issue(array $claims): string
    {
        return JWT::encode($claims, $this->secret, self::ALGORITHM);
    }

    public function verify(string $token): array
    {
        try {
            $decoded = JWT::decode($token, new Key($this->secret, self::ALGORITHM));
        } catch (Throwable $e) {
            throw AuthenticationException::invalidToken($e->getMessage());
        }

        /** @var array<string, mixed> $claims */
        $claims = (array) $decoded;

        return $claims;
    }
}
