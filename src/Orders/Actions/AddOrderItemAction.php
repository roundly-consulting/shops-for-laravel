<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Orders\Actions;

use RoundlyConsulting\Shops\Exceptions\CurrencyMismatchException;
use RoundlyConsulting\Shops\Orders\Item;
use RoundlyConsulting\Shops\Orders\Order;
use RoundlyConsulting\Shops\Products\ProductVariant;

/**
 * Adds a variant to an order as a line item, snapshotting the variant's
 * name, sku, price, and tax class at purchase time so later catalog changes
 * never alter a placed order. Enforces the order's (configured) currency.
 */
final class AddOrderItemAction
{
    public function execute(Order $order, ProductVariant $variant, int $quantity = 1): Item
    {
        $currency = (string) config('shops.pricing.default_currency', 'EUR');
        $variantCurrency = $variant->price->getCurrency()->getCode();

        if ($variantCurrency !== $currency) {
            throw CurrencyMismatchException::between($currency, $variantCurrency);
        }

        $item = new Item([
            'product_id' => $variant->product_id,
            'product_variant_id' => $variant->id,
            'name' => $variant->name ?? $variant->product->name,
            'sku' => $variant->sku,
            'quantity' => $quantity,
            'price' => $variant->price,
            'currency' => $currency,
            'tax_class' => $variant->tax_class,
        ]);

        $item->order()->associate($order);
        $item->save();

        return $item;
    }
}
