<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Handles;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Shops\Exceptions\ForeignItemException;
use RoundlyConsulting\Shops\Exceptions\InvalidQuantityException;
use RoundlyConsulting\Shops\Inventory\Enums\StockReason;
use RoundlyConsulting\Shops\Inventory\Exceptions\InsufficientStockException;
use RoundlyConsulting\Shops\Inventory\StockAdjustment;
use RoundlyConsulting\Shops\Orders\Order;
use RoundlyConsulting\Shops\Products\ProductVariant;
use RoundlyConsulting\Shops\ShopsManager;

/**
 * One variant's stock, returned by `Shops::inventory($variant)`. Every change writes an
 * auditable StockAdjustment row under the variant's row lock, fires StockAdjusted (and
 * StockRanLow at the threshold), and optionally points at the model that caused it — a
 * purchase order, an RMA, the order a return came back from.
 */
final readonly class InventoryHandle
{
    public function __construct(
        private ShopsManager $manager,
        private ProductVariant $variant,
    ) {}

    /**
     * Book a delivery: on-hand stock goes up by the quantity.
     *
     * @throws InvalidQuantityException when the quantity is not positive.
     */
    public function receive(int $quantity, ?Model $ref = null, ?string $note = null): StockAdjustment
    {
        return $this->manager->adjustStock($this->variant, $quantity, StockReason::Received, $ref, $note);
    }

    /**
     * Book a customer return: on-hand stock goes up by the quantity. When the reference is an
     * Order, the variant must be one of its lines.
     *
     * @throws ForeignItemException when the referenced order never contained this variant.
     * @throws InvalidQuantityException when the quantity is not positive.
     */
    public function returned(int $quantity, ?Model $ref = null, ?string $note = null): StockAdjustment
    {
        if ($ref instanceof Order && ! $ref->items()->where('product_variant_id', $this->variant->getKey())->exists()) {
            throw ForeignItemException::notOnOrder($ref, $this->variant);
        }

        return $this->manager->adjustStock($this->variant, $quantity, StockReason::Returned, $ref, $note);
    }

    /**
     * Any signed adjustment — by default a manual correction (a stock count, shrinkage, a
     * damaged unit), which may go either way. The sign must match the reason.
     *
     * @throws InvalidQuantityException when the delta is zero or its sign contradicts the reason.
     * @throws InsufficientStockException when a decrement would take a tracked variant below zero available.
     */
    public function adjust(
        int $delta,
        StockReason $reason = StockReason::Manual,
        ?Model $ref = null,
        ?string $note = null,
    ): StockAdjustment {
        return $this->manager->adjustStock($this->variant, $delta, $reason, $ref, $note);
    }

    /**
     * On-hand stock minus what pending orders hold.
     */
    public function available(): int
    {
        return $this->variant->availableStock();
    }

    /**
     * Whether the quantity can be sold now (always true for an untracked variant).
     */
    public function inStock(int $quantity = 1): bool
    {
        return $this->variant->inStock($quantity);
    }
}
