<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Support\Money;

use RoundlyConsulting\Shops\Exceptions\CurrencyMismatchException;

/**
 * Minimal immutable money value object that stores an integer amount in the
 * currency's minor unit (e.g. cents), implemented natively with no third-party
 * money dependency.
 */
final readonly class Money
{
    private int $amount;

    private Currency $currency;

    public function __construct(int|string $amount, Currency $currency)
    {
        $this->amount = (int) $amount;
        $this->currency = $currency;
    }

    public static function of(int|string $amount, string $currencyCode): self
    {
        return new self($amount, new Currency($currencyCode));
    }

    public static function EUR(int|string $amount): self
    {
        return self::of($amount, 'EUR');
    }

    public static function USD(int|string $amount): self
    {
        return self::of($amount, 'USD');
    }

    /**
     * Sum an arbitrary number of money values; every operand must share the
     * first operand's currency.
     */
    public static function sum(self $first, self ...$rest): self
    {
        $total = $first;

        foreach ($rest as $money) {
            $total = $total->add($money);
        }

        return $total;
    }

    public function getAmount(): string
    {
        return (string) $this->amount;
    }

    public function getCurrency(): Currency
    {
        return $this->currency;
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->amount + $other->amount, $this->currency);
    }

    public function subtract(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->amount - $other->amount, $this->currency);
    }

    public function multiply(int|float $multiplier): self
    {
        return new self((int) round($this->amount * $multiplier), $this->currency);
    }

    public function divide(int|float $divisor): self
    {
        return new self((int) round($this->amount / $divisor), $this->currency);
    }

    public function equals(self $other): bool
    {
        return $this->amount === $other->amount
            && $this->currency->equals($other->currency);
    }

    private function assertSameCurrency(self $other): void
    {
        if (! $this->currency->equals($other->currency)) {
            throw CurrencyMismatchException::between(
                $this->currency->getCode(),
                $other->currency->getCode(),
            );
        }
    }
}
