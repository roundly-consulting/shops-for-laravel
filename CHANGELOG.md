# Changelog

All notable changes to `shops-for-laravel` will be documented in this file.

## Unreleased

- Every amount is money-for-laravel's `Money` (now required, with `ext-bcmath`): the package's
  own `Support\Money\{Money,Currency}`, `MoneyCast`, `Discounts\MoneyBridge` and the
  currency / division / invalid-currency exceptions are removed; coupons, credits and shops
  share one Money type.
- Prices and `orders.store_credit_applied` are `decimal(38,0)` money columns (exact past int64
  on pgsql/MySQL); carts, orders and shops use money's `currencyCode` column.
- Orders snapshot their own `currency` (cart → shop → config) — config changes no longer
  re-denominate placed orders; `AddOrderItemAction` and `Cart::add()` refuse a variant in
  another currency (`Cart::add()` used to silently re-denominate it).
- Tax is exact and per line: the discount is spread with `DiscountAllocator`, each line is taxed
  once with money's `TaxRate`; `Price::taxSummary()` returns a per-rate `TaxSummary`. Totals can
  differ by ≤ 1 minor unit per line from the previous float ratio.
- `TaxRateValue::percent()` / `grossDivisor()` are replaced by `toTaxRate()` / `percentage()`;
  `Shop::currency()` returns a `Currency`; `DiscountResult` gains `?Discount $source`.
- Store credit requires a bucket denominated in the order currency (`credits.currencies`):
  `StoreCreditBucketNotDenominated` / `StoreCreditCurrencyMismatch`; the refund listener skips
  such a bucket with a warning.
- Shop, product and category slugs now come from `sluggable-for-laravel`: per-locale, unique per
  shop (products, categories) or across shops (shops), enforced by per-locale unique indexes.
- Route binding by slug works on every engine (it never matched before, and was a 500 on
  Postgres), including `->scopeBindings()` under `Shop::products()` / `Shop::categories()`.
- Opt-in slug history with 301 redirects (`shops.slugs.history`).
- The default variant SKU derives from the fallback-locale slug, so it no longer depends on the
  request locale.
- Removed the internal `Concerns\HasSlug` trait.
- `orders.number` is unique across all orders (was `(number, shop_id)`, which let shop-less
  orders share one); the default generator skips taken numbers and a checkout that races another
  to the same generated number is renumbered instead of duplicating it.
- Placed orders keep their tax: `AddOrderItemAction` snapshots each line's resolved rate
  (`order_items.tax_rate` + `tax_label`) and orders snapshot `price_type`, so later rate edits,
  address changes or a price-type flip no longer re-price them (a net order's total included).
- Quantities are validated (`InvalidQuantityException`): `Cart::add()`, `AddToCart` and
  `AddOrderItemAction` refuse zero, negative and oversized (> 32 767) quantities, including a
  merged cart line that would shrink or overflow; `UpdateCartItem` still removes a line set to
  `0` but refuses a negative quantity (it used to remove it); `CartItem` / `Item` refuse an
  invalid `quantity` on any write (fractions included); `PriceLine` needs at least one item.
  They used to produce zero or negative totals.
- `AdjustStockAction` refuses a zero delta and a delta whose sign contradicts its reason
  (`StockReason::direction()`): receiving −5 used to remove stock and selling +1 to add it.
