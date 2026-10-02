<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Actions\Orders;

use RoundlyConsulting\Money\Exceptions\CurrencyMismatch;
use RoundlyConsulting\Shops\Cart\CartItem;
use RoundlyConsulting\Shops\Exceptions\InvalidQuantityException;
use RoundlyConsulting\Shops\Orders\Item;
use RoundlyConsulting\Shops\Orders\Order;
use RoundlyConsulting\Shops\Products\ProductVariant;
use RoundlyConsulting\Shops\Support\Quantity;

/**
 * Adds a variant to an order as a line item, snapshotting its name, sku, price and tax class
 * — and the tax rate that class resolves to for the order's shop and destination — so later
 * catalog or tax-rate changes never alter a placed order. Checkout passes the cart line the
 * variant came from: the line's own snapshot (what the customer saw in the cart) is used
 * instead of the variant's current values. Enforces the order's own (snapshotted) currency.
 *
 * Building block of checkout ({@see PlaceOrderAction}); not on the facade.
 *
 * @internal
 */
final class AddOrderItemAction
{
    /**
     * @throws InvalidQuantityException when the quantity is not 1..Quantity::MAX.
     * @throws CurrencyMismatch when the line is priced in another currency than the order.
     */
    public function execute(Order $order, ProductVariant $variant, int $quantity = 1, ?CartItem $from = null): Item
    {
        Quantity::assertValid($quantity);

        $price = $from !== null ? $from->price : $variant->price;
        $taxClass = $from !== null ? $from->tax_class : $variant->tax_class;

        if (! $price->currency()->equals($order->currency)) {
            throw CurrencyMismatch::between($order->currency, $price->currency());
        }

        $taxRate = $order->taxRateFor($taxClass);

        // The price cast writes the item's currency column from the Money itself.
        $item = new Item([
            'product_id' => $variant->product_id,
            'product_variant_id' => $variant->id,
            'name' => $from !== null ? $from->name : ($variant->name ?? $variant->product->name),
            'sku' => $from !== null ? $from->sku : $variant->sku,
            'quantity' => $quantity,
            'price' => $price,
            'tax_class' => $taxClass,
            'tax_rate' => $taxRate->basisPoints,
            'tax_label' => $taxRate->label,
        ]);

        $item->order()->associate($order);
        $item->save();

        return $item;
    }
}
