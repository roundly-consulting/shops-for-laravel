# Changelog

All notable changes to `shops-for-laravel` will be documented in this file.

## Unreleased

- Shop, product and category slugs now come from `sluggable-for-laravel`: per-locale, unique per
  shop (products, categories) or across shops (shops), enforced by per-locale unique indexes.
- Route binding by slug works on every engine (it never matched before, and was a 500 on
  Postgres), including `->scopeBindings()` under `Shop::products()` / `Shop::categories()`.
- Opt-in slug history with 301 redirects (`shops.slugs.history`).
- The default variant SKU derives from the fallback-locale slug, so it no longer depends on the
  request locale.
- Removed the internal `Concerns\HasSlug` trait.
