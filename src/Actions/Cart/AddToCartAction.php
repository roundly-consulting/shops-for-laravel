<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Actions\Cart;

use RoundlyConsulting\Money\Exceptions\CurrencyMismatch;
use RoundlyConsulting\Shops\Cart\Cart;
use RoundlyConsulting\Shops\Cart\CartItem;
use RoundlyConsulting\Shops\Exceptions\InvalidQuantityException;
use RoundlyConsulting\Shops\Products\ProductVariant;
use RoundlyConsulting\Shops\Support\Quantity;

/**
 * Adds a variant to a cart, snapshotting its name, sku, price and tax class onto the line.
 * A variant the cart already holds increments that line instead of opening a second one.
 *
 * The read-then-write runs under the cart's row lock, so two concurrent adds of the same
 * variant (a double-clicked button) land on one line, and the merged quantity is checked
 * against the stored line, never a stale copy.
 */
final class AddToCartAction
{
    /**
     * @throws InvalidQuantityException when the quantity (or the merged line) is not 1..Quantity::MAX.
     * @throws CurrencyMismatch when the variant is priced in another currency than the cart.
     */
    public function execute(Cart $cart, ProductVariant $variant, int $quantity = 1): CartItem
    {
        Quantity::assertValid($quantity);

        if (! $variant->price->currency()->equals($cart->currency)) {
            throw CurrencyMismatch::between($cart->currency, $variant->price->currency());
        }

        $item = $cart->getConnection()->transaction(function () use ($cart, $variant, $quantity): CartItem {
            $cart->newQueryWithoutScopes()
                ->whereKey($cart->getKey())
                ->lockForUpdate()
                ->first([$cart->getKeyName()]);

            $existing = $cart->items()->where('product_variant_id', $variant->id)->first();

            if ($existing !== null) {
                // The merged line must still be a valid quantity (the model guard refuses it too).
                Quantity::assertValid($existing->quantity + $quantity);

                $existing->increment('quantity', $quantity);

                return $existing->refresh();
            }

            $item = new CartItem([
                'product_variant_id' => $variant->id,
                'name' => $variant->name ?? $variant->product->name,
                'sku' => $variant->sku,
                'quantity' => $quantity,
                'price' => $variant->price, // the cast writes the item's currency column
                'tax_class' => $variant->tax_class,
            ]);

            $cart->items()->save($item);

            return $item;
        });

        // A loaded `items` relation is now stale; the next read (price, subtotal) reloads it.
        $cart->unsetRelation('items');

        return $item;
    }
}
