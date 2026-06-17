<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Support\Money;

use RoundlyConsulting\Shops\Exceptions\InvalidCurrencyException;

final readonly class Currency
{
    public string $code;

    public function __construct(string $code)
    {
        $normalized = strtoupper(trim($code));

        if (! preg_match('/^[A-Z]{3}$/', $normalized)) {
            throw InvalidCurrencyException::forCode($code);
        }

        $this->code = $normalized;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function equals(self $other): bool
    {
        return $this->code === $other->code;
    }
}
