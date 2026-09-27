# Changelog

All notable changes to `shops-for-laravel` are documented in this file. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses
[Semantic Versioning](https://semver.org/).

## Unreleased

Initial public release.

### Added

- An e-commerce foundation for one shop or many: a `Shop` tenant with `CurrentShop` context,
  per-shop tax rates and a `Shop` facade.
- Products and categories with native per-locale translations and per-shop unique slugs with
  locale-aware route binding (via `sluggable-for-laravel`).
- Product variants with their own SKU, price, tax class and stock, composed from options such
  as size and colour.
- A race-safe inventory ledger (`AdjustStockAction`) with stock reservations for pending
  orders, plus `StockAdjusted` and `StockRanLow` events.
- A persistent cart for users or guests, and `PlaceOrderAction` to turn it into an order in one
  transaction.
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
