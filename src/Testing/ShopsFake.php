<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Testing;

use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Assert;
use RoundlyConsulting\Shops\Cart\Cart;
use RoundlyConsulting\Shops\Cart\CartItem;
use RoundlyConsulting\Shops\Inventory\Enums\StockReason;
use RoundlyConsulting\Shops\Inventory\StockAdjustment;
use RoundlyConsulting\Shops\Orders\DataTransferObjects\PlaceOrderData;
use RoundlyConsulting\Shops\Orders\Enums\Status;
use RoundlyConsulting\Shops\Orders\Order;
use RoundlyConsulting\Shops\Payments\PaymentResult;
use RoundlyConsulting\Shops\Products\ProductVariant;
use RoundlyConsulting\Shops\ShopsManager;

/**
 * The recording manager `Shops::fake()` installs — a spy. Every operation still runs (carts,
 * orders and stock rows are real, events fire, and a charge still goes through the bound
 * PaymentGateway — the default NullPaymentGateway charges nothing), and every state change
 * is recorded, whether it came through the facade, an injected manager or a model
 * convenience method (`$cart->add()`, `$order->markPaid()`, `$order->cancel()` …), which all
 * go through the manager.
 *
 * Only calls a host makes are recorded: the stock checkout reserves shows up as the placed
 * order, and the transitions a successful charge makes show up as the charge.
 */
final class ShopsFake extends ShopsManager
{
    /** @var list<array{cart: Cart, data: PlaceOrderData, order: Order}> */
    private array $placed = [];

    /** @var list<array{order: Order, result: PaymentResult}> */
    private array $charged = [];

    /** @var list<array{order: Order, to: Status}> */
    private array $transitioned = [];

    /** @var list<array{variant: ProductVariant, delta: int, reason: StockReason, reference: ?Model}> */
    private array $adjusted = [];

    /** @var list<array{cart: Cart, change: CartChange}> */
    private array $cartChanges = [];

    public function addToCart(Cart $cart, ProductVariant $variant, int $quantity): CartItem
    {
        $item = parent::addToCart($cart, $variant, $quantity);

        $this->cartChanges[] = ['cart' => $cart, 'change' => CartChange::Added];

        return $item;
    }

    public function updateCartItem(Cart $cart, CartItem $item, int $quantity): ?CartItem
    {
        $updated = parent::updateCartItem($cart, $item, $quantity);

        $this->cartChanges[] = ['cart' => $cart, 'change' => $updated === null ? CartChange::Removed : CartChange::Updated];

        return $updated;
    }

    public function removeFromCart(Cart $cart, CartItem $item): void
    {
        parent::removeFromCart($cart, $item);

        $this->cartChanges[] = ['cart' => $cart, 'change' => CartChange::Removed];
    }

    public function clearCart(Cart $cart): Cart
    {
        $cleared = parent::clearCart($cart);

        $this->cartChanges[] = ['cart' => $cart, 'change' => CartChange::Cleared];

        return $cleared;
    }

    public function placeOrder(Cart $cart, PlaceOrderData $data): Order
    {
        $order = parent::placeOrder($cart, $data);

        $this->placed[] = ['cart' => $cart, 'data' => $data, 'order' => $order];

        return $order;
    }

    public function transition(Order $order, Status $to): Order
    {
        $transitioned = parent::transition($order, $to);

        $this->transitioned[] = ['order' => $order, 'to' => $to];

        return $transitioned;
    }

    public function charge(Order $order): PaymentResult
    {
        $result = parent::charge($order);

        $this->charged[] = ['order' => $order, 'result' => $result];

        return $result;
    }

    public function adjustStock(
        ProductVariant $variant,
        int $delta,
        StockReason $reason,
        ?Model $reference = null,
        ?string $note = null,
    ): StockAdjustment {
        $adjustment = parent::adjustStock($variant, $delta, $reason, $reference, $note);

        $this->adjusted[] = ['variant' => $variant, 'delta' => $delta, 'reason' => $reason, 'reference' => $reference];

        return $adjustment;
    }

    /**
     * Assert an order was placed — any, or one checked out from the given cart.
     */
    public function assertOrderPlaced(?Cart $cart = null): void
    {
        $matching = array_filter($this->placed, static fn (array $placed): bool => $cart === null || $placed['cart']->is($cart));

        Assert::assertNotEmpty(
            $matching,
            $cart === null
                ? 'Expected an order to be placed, but none was.'
                : "Expected an order to be placed from cart [{$cart->getKey()}], but none was.",
        );
    }

    public function assertNothingPlaced(): void
    {
        Assert::assertCount(0, $this->placed, sprintf('Expected no order to be placed, but %d were.', count($this->placed)));
    }

    /**
     * Assert an order was charged — any, or the given one. Failed charges count too; the
     * PaymentResult says how it went.
     */
    public function assertCharged(?Order $order = null): void
    {
        $matching = array_filter($this->charged, static fn (array $charged): bool => $order === null || $charged['order']->is($order));

        Assert::assertNotEmpty(
            $matching,
            $order === null
                ? 'Expected an order to be charged, but none was.'
                : "Expected order [{$order->number}] to be charged, but it was not.",
        );
    }

    public function assertNothingCharged(): void
    {
        Assert::assertCount(0, $this->charged, sprintf('Expected no order to be charged, but %d charge(s) were made.', count($this->charged)));
    }

    /**
     * Assert an order was moved to a status — any order, or the given one, optionally only
     * to the given status.
     */
    public function assertTransitioned(?Order $order = null, ?Status $to = null): void
    {
        $matching = array_filter(
            $this->transitioned,
            static fn (array $moved): bool => ($order === null || $moved['order']->is($order)) && ($to === null || $moved['to'] === $to),
        );

        Assert::assertNotEmpty($matching, sprintf(
            'Expected %s to be transitioned%s, but it was not.',
            $order === null ? 'an order' : "order [{$order->number}]",
            $to === null ? '' : " to {$to->value}",
        ));
    }

    public function assertNothingTransitioned(): void
    {
        Assert::assertCount(0, $this->transitioned, sprintf('Expected no order to be transitioned, but %d transition(s) were made.', count($this->transitioned)));
    }

    /**
     * Assert a variant's stock was adjusted — any variant, or the given one, optionally by
     * exactly the delta and/or for the reason.
     */
    public function assertStockAdjusted(?ProductVariant $variant = null, ?int $delta = null, ?StockReason $reason = null): void
    {
        $matching = array_filter(
            $this->adjusted,
            static fn (array $adjusted): bool => ($variant === null || $adjusted['variant']->is($variant))
                && ($delta === null || $adjusted['delta'] === $delta)
                && ($reason === null || $adjusted['reason'] === $reason),
        );

        Assert::assertNotEmpty($matching, sprintf(
            'Expected the stock of %s to be adjusted%s%s, but it was not.',
            $variant === null ? 'a variant' : "variant [{$variant->sku}]",
            $delta === null ? '' : " by {$delta}",
            $reason === null ? '' : " ({$reason->value})",
        ));
    }

    public function assertNothingStockAdjusted(): void
    {
        Assert::assertCount(0, $this->adjusted, sprintf('Expected no stock adjustment, but %d were made.', count($this->adjusted)));
    }

    /**
     * Assert a cart was changed — any cart, or the given one, optionally only by the kind of
     * change (a line added, updated, removed, or the cart cleared).
     */
    public function assertCartChanged(?Cart $cart = null, ?CartChange $change = null): void
    {
        $matching = array_filter(
            $this->cartChanges,
            static fn (array $changed): bool => ($cart === null || $changed['cart']->is($cart)) && ($change === null || $changed['change'] === $change),
        );

        Assert::assertNotEmpty($matching, sprintf(
            'Expected %s to be changed%s, but it was not.',
            $cart === null ? 'a cart' : "cart [{$cart->getKey()}]",
            $change === null ? '' : " ({$change->value})",
        ));
    }

    public function assertNothingCartChanged(): void
    {
        Assert::assertCount(0, $this->cartChanges, sprintf('Expected no cart change, but %d were made.', count($this->cartChanges)));
    }
}
