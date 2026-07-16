<?php

declare(strict_types=1);

namespace OrderHub\Interface\Api\Http;

use OrderHub\Interface\Api\Exceptions\ValidationException;

/**
 * Small typed reader over a request body that raises ValidationException with a
 * clear message instead of letting bad input reach the domain.
 */
final class Input
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(private readonly array $data)
    {
    }

    public function requireString(string $field): string
    {
        $value = $this->data[$field] ?? null;
        if (!\is_string($value) || trim($value) === '') {
            throw ValidationException::missing($field);
        }

        return $value;
    }

    public function optionalString(string $field): ?string
    {
        $value = $this->data[$field] ?? null;

        return \is_string($value) && $value !== '' ? $value : null;
    }

    public function requireInt(string $field): int
    {
        $value = $this->data[$field] ?? null;
        if (!\is_int($value) && !(\is_string($value) && ctype_digit($value))) {
            throw ValidationException::invalid($field, 'expected an integer');
        }

        return (int) $value;
    }

    public function optionalInt(string $field): ?int
    {
        $value = $this->data[$field] ?? null;
        if ($value === null) {
            return null;
        }
        if (!\is_int($value) && !(\is_string($value) && ctype_digit($value))) {
            throw ValidationException::invalid($field, 'expected an integer');
        }

        return (int) $value;
    }

    /**
     * @return list<array{productId: string, quantity: int}>
     */
    public function requireOrderItems(string $field): array
    {
        $raw = $this->data[$field] ?? null;
        if (!\is_array($raw) || $raw === []) {
            throw ValidationException::invalid($field, 'expected a non-empty array of items');
        }

        $items = [];
        foreach ($raw as $entry) {
            if (!\is_array($entry) || !isset($entry['productId'], $entry['quantity'])) {
                throw ValidationException::invalid($field, 'each item needs productId and quantity');
            }
            $quantity = $entry['quantity'];
            if ((!\is_int($quantity) && !(\is_string($quantity) && ctype_digit($quantity))) || (int) $quantity < 1) {
                throw ValidationException::invalid($field, 'quantity must be a positive integer');
            }
            $items[] = ['productId' => (string) $entry['productId'], 'quantity' => (int) $quantity];
        }

        return $items;
    }
}
