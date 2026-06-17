<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Support\Money;

use RoundlyConsulting\Shops\Exceptions\CurrencyMismatchException;
use RoundlyConsulting\Shops\Exceptions\DivisionByZeroException;

/**
 * Minimal immutable money value object that stores an integer amount in the
 * currency's minor unit (e.g. cents), implemented natively with no third-party
 * money dependency.
 *
 * Calculations that do not land on a whole minor unit round half-up (PHP's
 * default `round()` behaviour), matching standard retail rounding.
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

    public static function zero(string $currencyCode): self
    {
        return self::of(0, $currencyCode);
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

    /**
     * The raw integer amount in the currency's minor unit.
     */
    public function getMinorAmount(): int
    {
        return $this->amount;
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
        if ((float) $divisor === 0.0) {
            throw DivisionByZeroException::make();
        }

        return new self((int) round($this->amount / $divisor), $this->currency);
    }

    public function isZero(): bool
    {
        return $this->amount === 0;
    }

    public function isNegative(): bool
    {
        return $this->amount < 0;
    }

    public function isPositive(): bool
    {
        return $this->amount > 0;
    }

    /**
     * Compare two money values of the same currency, returning -1, 0 or 1.
     */
    public function compareTo(self $other): int
    {
        $this->assertSameCurrency($other);

        return $this->amount <=> $other->amount;
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
