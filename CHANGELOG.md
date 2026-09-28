# Changelog

All notable changes to `shops-for-laravel` are documented in this file. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses
[Semantic Versioning](https://semver.org/).

## Unreleased

Initial public release.

### Added

- An e-commerce foundation for one shop or many: a `Shop` tenant with `CurrentShop` context
  and per-shop tax rates.
- A `Shops` facade (alias `Shops`) over an injectable `ShopsManager`, with scoped handles:
  `Shops::cart($cart)->add/update/remove/clear/price/subtotal/checkout`,
  `Shops::order($order)->transition/cancel/refund/fulfil/charge/quoteShipping`,
  `Shops::inventory($variant)->receive/returned/adjust/available/inStock`,
  `Shops::coupons()->preview`, `Shops::current()` (set/get/id/forget/run) and
  `Shops::addresses()->defaults`. Every host-facing action is public under
  `RoundlyConsulting\Shops\Actions\{Cart,Inventory,Orders}`.
- Receiving stock, customer returns and manual corrections (`Shops::inventory()`), emptying a
  cart (`clear()`) and shipping quotes (`quoteShipping()`) — the actions existed, but nothing
  could reach them.
- `Shops::fake()` returns a `ShopsFake`, a recording `ShopsManager` subtype (so injected managers
  get it too) that still runs every operation. It records calls made through the facade, the
  manager and the model methods (`$cart->add()`, `$order->markPaid()`, `$order->cancel()` …) and
  asserts `assertOrderPlaced()` / `assertNothingPlaced()`, `assertCharged()` /
  `assertNothingCharged()`, `assertTransitioned()` / `assertNothingTransitioned()`,
  `assertStockAdjusted()` / `assertNothingStockAdjusted()` and `assertCartChanged()` (with a
  `CartChange`) / `assertNothingCartChanged()`.
- `ForeignItemException`: a cart handle refuses a line of another cart, and a return is refused
  against an order the variant was never on.
- Products and categories with native per-locale translations and per-shop unique slugs with
  locale-aware route binding (via `sluggable-for-laravel`).
- Product variants with their own SKU, price, tax class and stock, composed from options such
  as size and colour.
- A race-safe inventory ledger (`AdjustStockAction`) with stock reservations for pending
  orders, plus `StockAdjusted` and `StockRanLow` events.
- A persistent cart for users or guests, and `checkout()` (`PlaceOrderAction`) to turn it into
  an order in one transaction.
- Exact net or gross tax pricing on arbitrary-precision money (`money-for-laravel`); orders keep
  their own currency and snapshot their tax rates and discounts.
- A guarded order state machine (`New`, `InProgress`, `Paid`, `Fulfilled`, `Canceled`,
  `Refunded`) with status events and pluggable order numbers (`NumberGenerator`).
- Coupons via `coupons-for-laravel` and paying with store credit via `credits-for-laravel`.
- Product and category images via `media-library-for-laravel`, product reviews with
  verified-purchase flags via `reviews-for-laravel`, and filterable spec-sheet attributes via
  `attributes-for-laravel`.
- Order billing and shipping snapshots from a customer's address book (`addresses-for-laravel`).
- `PaymentGateway` and `ShippingMethod` driver contracts for your own payment and shipping
  integrations.

### Changed

- The facade is `Shops` (was `Shop`, which clashed with the `Shop` model) and its root
  `ShopsManager` (was `ShopManager`). The flat `placeOrder()`, `transition()`, `charge()` and
  `discountFor()` are replaced by `cart()->checkout()`, `order()->transition()`,
  `order()->charge()` and `coupons()->preview()`.
- The actions moved to `RoundlyConsulting\Shops\Actions\{Cart,Inventory,Orders}`, and the cart
  actions gained the `Action` suffix (`AddToCartAction`, `UpdateCartItemAction`,
  `RemoveFromCartAction`, `ClearCartAction`). `UpdateCartItemAction` and `RemoveFromCartAction`
  take the cart first. `AddOrderItemAction`, `ReserveStockAction` and `ReleaseStockAction` are
  `@internal`.
- The add-to-cart logic lives in `AddToCartAction`; `Cart::add()` and the `Order` status methods
  (`transitionTo()`, `mark*()`, `cancel()`, `refund()`) go through `ShopsManager`.
- `AddressBook::defaults()` returns an `OrderAddresses` DTO instead of a
  `['shipping' => …, 'billing' => …]` array.
- Adding to a cart locks the cart row, so two concurrent adds of the same variant land on one
  line. Adding, updating or removing a line drops a loaded `items` relation, so the next
  `price()` / `subtotal()` sees the change.

### Fixed

- The manager was a singleton that received its actions (and through them the coupon manager,
  the discount resolver and the payment gateway) at first resolution. A binding or a provider
  fake installed later, such as `Coupons::fake()`, was never seen by checkout. Each call now
  resolves its action from the container.
