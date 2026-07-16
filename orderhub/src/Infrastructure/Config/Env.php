<?php

declare(strict_types=1);

namespace OrderHub\Infrastructure\Config;

/**
 * Thin, typed accessor over environment variables. Centralises the "read config
 * from the environment" concern so the rest of the code never touches $_ENV/getenv.
 */
final class Env
{
    public static function get(string $key, ?string $default = null): string
    {
        $value = getenv($key);
        if ($value === false || $value === '') {
            if ($default === null) {
                throw new \RuntimeException(\sprintf('Missing required environment variable "%s".', $key));
            }

            return $default;
        }

        return $value;
    }

    public static function int(string $key, int $default): int
    {
        $value = getenv($key);

        return ($value === false || $value === '') ? $default : (int) $value;
    }
}
