<?php

declare(strict_types=1);

use RoundlyConsulting\Money\Currency;
use RoundlyConsulting\Money\Money;
use RoundlyConsulting\Shops\Orders\DataTransferObjects\Price;
use RoundlyConsulting\Shops\Orders\DataTransferObjects\PriceLine;
use RoundlyConsulting\Shops\Orders\Enums\PriceType;
use RoundlyConsulting\Shops\Support\Tax\ConfigTaxResolver;
use RoundlyConsulting\Shops\Tests\Fixtures\FixedRateResolver;

it('multiplies the line price by its quantity (G4 regression)', function (): void {
    $price = new Price(
        lines: [new PriceLine(Money::ofMinor(199, 'EUR'), quantity: 2)],
        shipping: Money::zero('EUR'),
        priceType: PriceType::Net,
        taxResolver: new ConfigTaxResolver,
    );

    expect($price->getSubtotal()->minor())->toBe('398');
});

it('extracts tax from a gross price instead of adding on top (G1 regression)', function (): void {
    // 120 gross at 20% => net 100, tax 20.
    $price = new Price(
        lines: [new PriceLine(Money::ofMinor(120, 'EUR'), taxClass: 'standard')],
        shipping: Money::zero('EUR'),
        priceType: PriceType::Gross,
        taxResolver: new ConfigTaxResolver,
    );

    expect($price->getTaxPrice()->minor())->toBe('20')
        ->and($price->getNetPrice()->minor())->toBe('100')
        ->and($price->getFinalPrice()->minor())->toBe('120');
});

it('adds tax on top for a net price type', function (): void {
    $price = new Price(
        lines: [new PriceLine(Money::ofMinor(1000, 'EUR'), taxClass: 'standard')],
        shipping: Money::ofMinor(500, 'EUR'),
        priceType: PriceType::Net,
        taxResolver: new ConfigTaxResolver,
    );

    expect($price->getTaxPrice()->minor())->toBe('200')
        ->and($price->getNetPrice()->minor())->toBe('1000')
        ->and($price->getFinalPrice()->minor())->toBe('1700');
});

it('sums mixed tax classes correctly', function (): void {
    $price = new Price(
        lines: [
            new PriceLine(Money::ofMinor(1000, 'EUR'), taxClass: 'standard'), // +200
            new PriceLine(Money::ofMinor(1000, 'EUR'), taxClass: 'reduced'),  // +100
            new PriceLine(Money::ofMinor(1000, 'EUR'), taxClass: 'zero'),     // +0
        ],
        shipping: Money::zero('EUR'),
        priceType: PriceType::Net,
        taxResolver: new ConfigTaxResolver,
    );

    expect($price->getTaxPrice()->minor())->toBe('300');
});

it('adds shipping exactly once', function (): void {
    $price = new Price(
        lines: [new PriceLine(Money::ofMinor(1000, 'EUR'), taxClass: 'zero')],
        shipping: Money::ofMinor(500, 'EUR'),
        priceType: PriceType::Net,
        taxResolver: new ConfigTaxResolver,
    );

    expect($price->getFinalPrice()->minor())->toBe('1500');
});

it('returns a zero price in the default currency for an empty line set', function (): void {
    $price = new Price(
        lines: [],
        shipping: Money::zero('USD'),
        priceType: PriceType::Gross,
        taxResolver: new ConfigTaxResolver,
        currency: 'USD',
    );

    expect($price->getSubtotal()->minor())->toBe('0')
        ->and($price->getSubtotal()->currency()->code)->toBe('USD')
        ->and($price->getTaxPrice()->minor())->toBe('0')
        ->and($price->getFinalPrice()->minor())->toBe('0');
});

it('applies a resolved discount and scales tax proportionally', function (): void {
    $price = new Price(
        lines: [new PriceLine(Money::ofMinor(1000, 'EUR'), taxClass: 'standard')],
        shipping: Money::zero('EUR'),
        priceType: PriceType::Net,
        taxResolver: new ConfigTaxResolver,
        discount: Money::ofMinor(100, 'EUR'),
    );

    expect($price->getDiscountValue()->minor())->toBe('100')
        ->and($price->getPriceAfterDiscount()->minor())->toBe('900')
        ->and($price->getTaxPrice()->minor())->toBe('180');
});

it('caps a discount at the subtotal so goods never go negative', function (): void {
    $price = new Price(
        lines: [new PriceLine(Money::ofMinor(1000, 'EUR'), taxClass: 'zero')],
        shipping: Money::zero('EUR'),
        priceType: PriceType::Net,
        taxResolver: new ConfigTaxResolver,
        discount: Money::ofMinor(5000, 'EUR'),
    );

    expect($price->getPriceAfterDiscount()->minor())->toBe('0')
        ->and($price->getDiscountValue()->minor())->toBe('1000');
});

it('zeroes shipping for a free-shipping coupon', function (): void {
    $price = new Price(
        lines: [new PriceLine(Money::ofMinor(1000, 'EUR'), taxClass: 'zero')],
        shipping: Money::ofMinor(500, 'EUR'),
        priceType: PriceType::Net,
        taxResolver: new ConfigTaxResolver,
        freeShipping: true,
    );

    expect($price->getFinalPrice()->minor())->toBe('1000')
        ->and($price->shippingCost()->minor())->toBe('0');
});

it('reports a zero discount when no coupon is set', function (): void {
    $price = new Price(
        lines: [new PriceLine(Money::ofMinor(1000, 'EUR'), taxClass: 'zero')],
        shipping: Money::zero('EUR'),
        priceType: PriceType::Net,
        taxResolver: new ConfigTaxResolver,
    );

    expect($price->getDiscountValue()->minor())->toBe('0')
        ->and($price->getPriceAfterDiscount()->minor())->toBe('1000');
});

it('defaults the price type to gross and uses the bound resolver', function (): void {
    $price = new Price(
        lines: [new PriceLine(Money::ofMinor(120, 'EUR'))],
        shipping: Money::zero('EUR'),
    );

    expect($price->priceType)->toBe(PriceType::Gross)
        ->and($price->getTaxPrice()->minor())->toBe('20');
});

it('extracts gross tax at fractional and whole basis-point rates', function (int $bp, string $gross, string $tax, string $net): void {
    $price = new Price(
        lines: [new PriceLine(Money::ofMinor((int) $gross, 'EUR'), taxClass: 'standard')],
        shipping: Money::zero('EUR'),
        priceType: PriceType::Gross,
        taxResolver: new FixedRateResolver($bp),
    );

    expect($price->getTaxPrice()->minor())->toBe($tax)
        ->and($price->getNetPrice()->minor())->toBe($net);
})->with([
    '8.5% of 1085' => [850, '1085', '85', '1000'],
    '19% of 1190' => [1900, '1190', '190', '1000'],
    '21% of 1210' => [2100, '1210', '210', '1000'],
]);

it('adds net tax on top at fractional and whole basis-point rates', function (int $bp, string $net, string $tax): void {
    $price = new Price(
        lines: [new PriceLine(Money::ofMinor((int) $net, 'EUR'), taxClass: 'standard')],
        shipping: Money::zero('EUR'),
        priceType: PriceType::Net,
        taxResolver: new FixedRateResolver($bp),
    );

    expect($price->getTaxPrice()->minor())->toBe($tax);
})->with([
    '8.5% of 1000' => [850, '1000', '85'],
    '19% of 1000' => [1900, '1000', '190'],
]);

it('yields zero tax for a zero rate on both price types', function (PriceType $type): void {
    $price = new Price(
        lines: [new PriceLine(Money::ofMinor(1000, 'EUR'), taxClass: 'standard')],
        shipping: Money::zero('EUR'),
        priceType: $type,
        taxResolver: new FixedRateResolver(0),
    );

    expect($price->getTaxPrice()->minor())->toBe('0');
})->with([
    'gross' => [PriceType::Gross],
    'net' => [PriceType::Net],
]);

/**
 * Hand-computed with money's exact tax (no float divisor): gross tax = gross − round(gross ×
 * 100 / (100 + rate)), net tax = round(net × rate), half away from zero, once per line.
 */
it('extracts gross tax exactly and adds net tax exactly at 20 %', function (): void {
    $gross = new Price(
        lines: [new PriceLine(Money::ofMinor(1000, 'EUR'))],
        shipping: Money::zero('EUR'),
        priceType: PriceType::Gross,
        taxResolver: new ConfigTaxResolver,
    );
    $net = new Price(
        lines: [new PriceLine(Money::ofMinor(1000, 'EUR'))],
        shipping: Money::zero('EUR'),
        priceType: PriceType::Net,
        taxResolver: new ConfigTaxResolver,
    );

    // 1000 − round(1000 / 1.2 = 833.33) = 1000 − 833 = 167.
    expect($gross->getTaxPrice()->minor())->toBe('167')
        ->and($gross->getNetPrice()->minor())->toBe('833')
        ->and($net->getTaxPrice()->minor())->toBe('200');
});

it('taxes 999 minor units at 8.5 % in both directions', function (): void {
    $gross = new Price([new PriceLine(Money::ofMinor(999, 'EUR'))], Money::zero('EUR'), PriceType::Gross, new FixedRateResolver(850));
    $net = new Price([new PriceLine(Money::ofMinor(999, 'EUR'))], Money::zero('EUR'), PriceType::Net, new FixedRateResolver(850));

    // gross: 999 × 100 / 108.5 = 920.74 → 921, tax 78. net: 999 × 0.085 = 84.915 → 85.
    expect($gross->getTaxPrice()->minor())->toBe('78')
        ->and($gross->getNetPrice()->minor())->toBe('921')
        ->and($net->getTaxPrice()->minor())->toBe('85')
        ->and($net->getFinalPrice()->minor())->toBe('1084');
});

it('spreads a discount over the lines before taxing each line once', function (): void {
    $price = new Price(
        lines: [
            new PriceLine(Money::ofMinor(1000, 'EUR'), taxClass: 'standard'), // 20 %
            new PriceLine(Money::ofMinor(1000, 'EUR'), taxClass: 'reduced'),  // 10 %
        ],
        shipping: Money::zero('EUR'),
        priceType: PriceType::Gross,
        taxResolver: new ConfigTaxResolver,
        discount: Money::ofMinor(100, 'EUR'),
    );

    // Shares 50 / 50 → taxable 950 / 950.
    // 950 − round(950 / 1.2 = 791.67) = 158;  950 − round(950 / 1.1 = 863.64) = 86;  Σ 244.
    // (The previous float ratio gave round((167 + 91) × 1900/2000 = 245.1) = 245: −1.)
    expect($price->getTaxPrice()->minor())->toBe('244')
        ->and($price->getPriceAfterDiscount()->minor())->toBe('1900')
        ->and($price->getNetPrice()->add($price->getTaxPrice())->equals($price->getPriceAfterDiscount()))->toBeTrue();
});

it('documents the per-line deltas against the previous total-level ratio', function (array $lines, int $discount, PriceType $type, string $tax, string $previous): void {
    $price = new Price(
        lines: array_map(fn (array $line): PriceLine => new PriceLine(Money::ofMinor($line[0], 'EUR'), taxClass: $line[1]), $lines),
        shipping: Money::zero('EUR'),
        priceType: $type,
        taxResolver: new ConfigTaxResolver,
        discount: Money::ofMinor($discount, 'EUR'),
    );

    expect($price->getTaxPrice()->minor())->toBe($tax)
        ->and(abs((int) $tax - (int) $previous))->toBeLessThanOrEqual(count($lines));
})->with([
    // 1000 − 7 = 993 → 993 − round(827.5) = 165; previously round(167 × 993/1000 = 165.83) = 166.
    'gross 1000@20 %, −7' => [[[1000, 'standard']], 7, PriceType::Gross, '165', '166'],
    // shares 200/100 → 800 − round(666.67) = 133 (+0 on the zero line); previously round(167 × 1200/1500) = 134.
    'gross 1000@20 % + 500@0 %, −300' => [[[1000, 'standard'], [500, 'zero']], 300, PriceType::Gross, '133', '134'],
    // shares 499/1 → 500 − round(416.67) = 83 (+0 on the emptied line); previously round(167 × 500/1000 = 83.5) = 84.
    'gross 999@20 % + 1@20 %, −500' => [[[999, 'standard'], [1, 'standard']], 500, PriceType::Gross, '83', '84'],
    // shares 50/50 → round(950 × 0.2) + round(950 × 0.1) = 190 + 95 = 285; unchanged.
    'net 1000@20 % + 1000@10 %, −100' => [[[1000, 'standard'], [1000, 'reduced']], 100, PriceType::Net, '285', '285'],
    // 900 → 900 − 750 = 150; unchanged.
    'gross 1000@20 %, −100' => [[[1000, 'standard']], 100, PriceType::Gross, '150', '150'],
]);

it('keeps net + tax equal to the discounted goods exactly for gross catalogs', function (): void {
    $price = new Price(
        lines: [
            new PriceLine(Money::ofMinor(333, 'EUR'), quantity: 3, taxClass: 'standard'),
            new PriceLine(Money::ofMinor(1999, 'EUR'), taxClass: 'reduced'),
            new PriceLine(Money::ofMinor(7, 'EUR'), quantity: 11, taxClass: 'zero'),
        ],
        shipping: Money::ofMinor(495, 'EUR'),
        priceType: PriceType::Gross,
        taxResolver: new ConfigTaxResolver,
        discount: Money::ofMinor(1234, 'EUR'),
    );

    expect($price->getNetPrice()->add($price->getTaxPrice())->equals($price->getPriceAfterDiscount()))->toBeTrue()
        ->and($price->getFinalPrice()->equals($price->getPriceAfterDiscount()->add(Money::ofMinor(495, 'EUR'))))->toBeTrue();
});

it('taxes a zero-exponent and a three-exponent currency', function (): void {
    $yen = new Price([new PriceLine(Money::ofMinor(1000, 'JPY'))], Money::zero('JPY'), PriceType::Gross, new FixedRateResolver(1000), currency: 'JPY');
    $dinar = new Price([new PriceLine(Money::ofMinor(10000, 'BHD'))], Money::zero('BHD'), PriceType::Gross, new FixedRateResolver(1000), currency: 'BHD');

    // ¥1000 gross @10 %: net round(909.09) = 909, tax ¥91. 10.000 BD: net round(9090.91) = 9091, tax 0.909 BD.
    expect((string) $yen->getTaxPrice())->toBe('91 JPY')
        ->and((string) $dinar->getTaxPrice())->toBe('0.909 BHD')
        ->and((string) $dinar->getNetPrice())->toBe('9.091 BHD');
});

it('summarises the discounted tax per rate', function (): void {
    $price = new Price(
        lines: [
            new PriceLine(Money::ofMinor(1000, 'EUR'), taxClass: 'standard'),
            new PriceLine(Money::ofMinor(500, 'EUR'), taxClass: 'standard'),
            new PriceLine(Money::ofMinor(1000, 'EUR'), taxClass: 'reduced'),
        ],
        shipping: Money::zero('EUR'),
        priceType: PriceType::Net,
        taxResolver: new ConfigTaxResolver,
        discount: Money::ofMinor(250, 'EUR'),
    );

    $summary = $price->taxSummary();
    $perRate = $summary->perRate();

    // shares 100/50/100 → standard 900 + 450 = 1350 net, tax 180 + 90 = 270; reduced 900 net, tax 90.
    expect($perRate)->toHaveCount(2)
        ->and($perRate[0]->rate->percentage()->value())->toBe('10')
        ->and($perRate[0]->net->minor())->toBe('900')
        ->and($perRate[0]->tax->minor())->toBe('90')
        ->and($perRate[1]->net->minor())->toBe('1350')
        ->and($perRate[1]->tax->minor())->toBe('270')
        ->and($summary->tax()->equals($price->getTaxPrice()))->toBeTrue();
});

it('accepts a Currency object for the empty-cart currency', function (): void {
    $price = new Price([], Money::zero('JPY'), PriceType::Gross, new ConfigTaxResolver, currency: Currency::of('JPY'));

    expect((string) $price->getSubtotal())->toBe('0 JPY')
        ->and((string) $price->getTaxPrice())->toBe('0 JPY')
        ->and($price->taxSummary()->perRate())->toBe([]);
});
