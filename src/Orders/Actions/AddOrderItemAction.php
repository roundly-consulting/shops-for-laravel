<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Orders\Actions;

use RoundlyConsulting\Money\Exceptions\CurrencyMismatch;
use RoundlyConsulting\Shops\Orders\Item;
use RoundlyConsulting\Shops\Orders\Order;
use RoundlyConsulting\Shops\Products\ProductVariant;

/**
 * Adds a variant to an order as a line item, snapshotting the variant's
 * name, sku, price, and tax class at purchase time so later catalog changes
 * never alter a placed order. Enforces the order's own (snapshotted) currency.
 */
final class AddOrderItemAction
{
    /**
     * @throws CurrencyMismatch when the variant is priced in another currency than the order.
     */
    public function execute(Order $order, ProductVariant $variant, int $quantity = 1): Item
    {
        if (! $variant->price->currency()->equals($order->currency)) {
            throw CurrencyMismatch::between($order->currency, $variant->price->currency());
        }

        // The price cast writes the item's currency column from the Money itself.
        $item = new Item([
            'product_id' => $variant->product_id,
            'product_variant_id' => $variant->id,
            'name' => $variant->name ?? $variant->product->name,
            'sku' => $variant->sku,
            'quantity' => $quantity,
            'price' => $variant->price,
            'tax_class' => $variant->tax_class,
        ]);

        $item->order()->associate($order);
        $item->save();

        return $item;
    }
}
