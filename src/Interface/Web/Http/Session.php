<?php

declare(strict_types=1);

namespace OrderHub\Interface\Web\Http;

/**
 * Thin wrapper over PHP's native session ($_SESSION), used for Web
 * authentication instead of the JWT the API uses — a traditional browser
 * session is the appropriate mechanism for a channel with cookies and forms,
 * and it deliberately doesn't share machinery with the API's TokenIssuer.
 *
 * Cookie flags (httpOnly, sameSite=Lax, secure in prod) are set once here so
 * every route benefits without repeating the option array.
 */
final class Session
{
    public function start(): void
    {
        if (session_status() === \PHP_SESSION_ACTIVE) {
            return;
        }

        session_set_cookie_params([
            'httponly' => true,
            'samesite' => 'Lax',
            'secure' => (getenv('APP_ENV') ?: 'dev') === 'prod',
        ]);
        session_start();
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public function destroy(): void
    {
        $_SESSION = [];
        if (session_status() === \PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    public function flash(string $type, string $message): void
    {
        $_SESSION['_flashes'][$type][] = $message;
    }

    /**
     * Reads and clears the pending flash messages — meant to be called once
     * per render, so a message is shown exactly on the next page view.
     *
     * @return array<string, list<string>>
     */
    public function pullFlashes(): array
    {
        /** @var array<string, list<string>> $flashes */
        $flashes = $_SESSION['_flashes'] ?? [];
        unset($_SESSION['_flashes']);

        return $flashes;
    }
}
