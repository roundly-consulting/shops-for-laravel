<!-- roundly-hero:start -->
<p align="center">
  <a href="https://roundly-consulting.com/open-source/docs/shops-for-laravel?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=shops-for-laravel">
    <img src="https://raw.githubusercontent.com/roundly-consulting/shops-for-laravel/main/art/hero.png" alt="Shops for Laravel — Roundly open source" width="100%">
  </a>
</p>
<!-- roundly-hero:end -->

<!-- roundly-badges:start -->
<p align="center">
  <a href="https://packagist.org/packages/roundly-consulting/shops-for-laravel"><img src="https://img.shields.io/packagist/v/roundly-consulting/shops-for-laravel?style=flat-square&label=release" alt="Latest release"></a>
  <a href="https://github.com/roundly-consulting/shops-for-laravel/actions/workflows/run-tests.yml"><img src="https://img.shields.io/github/actions/workflow/status/roundly-consulting/shops-for-laravel/run-tests.yml?branch=main&style=flat-square&label=tests" alt="Tests"></a>
  <a href="https://github.com/roundly-consulting/shops-for-laravel/actions/workflows/fix-php-code-style-issues.yml"><img src="https://img.shields.io/github/actions/workflow/status/roundly-consulting/shops-for-laravel/fix-php-code-style-issues.yml?branch=main&style=flat-square&label=code%20style" alt="Code style"></a>
  <a href="https://donate.stripe.com/dRmeVe8FX5PF1Qd9pXcEw00"><img src="https://img.shields.io/badge/donate-support%20our%20open%20source-F24E29?style=flat-square&logo=stripe&logoColor=white" alt="Donate"></a>
  <a href="https://www.patreon.com/cw/roundly"><img src="https://img.shields.io/badge/patreon-become%20a%20patron-F96854?style=flat-square&logo=patreon&logoColor=white" alt="Patreon"></a>
  <a href="https://roundly-consulting.com/support-us?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=shops-for-laravel#crypto"><img src="https://img.shields.io/badge/crypto-BTC%20%C2%B7%20ETH%20%C2%B7%20BNB%20%C2%B7%20SOL-F7931A?style=flat-square&logo=bitcoin&logoColor=white" alt="Crypto"></a>
</p>
<!-- roundly-badges:end -->

# Shops for Laravel

A production-grade e-commerce foundation for Laravel: products with variants and options, a
race-safe inventory ledger with stock reservations, a persistent cart with one-call checkout, a
guarded order state machine and exact net/gross tax pricing on arbitrary-precision money. Catalog
media, reviews, spec sheets, coupons, store credit and address books come from sibling Roundly
packages; payment and shipping are driver contracts you bind.

## Installation

Requires PHP 8.4 (`ext-bcmath`) and Laravel 12 or 13.

```bash
composer require roundly-consulting/shops-for-laravel
php artisan vendor:publish --tag="shops-migrations"
php artisan vendor:publish --tag="media-migrations" --tag="reviews-migrations" --tag="attributes-migrations" \
    --tag="coupons-migrations" --tag="credits-migrations" --tag="addresses-migrations"
php artisan migrate
```

If your customer or cart-owner models have UUID/ULID keys, set `SHOPS_KEY_TYPE` **before**
migrating; slug indexes are created for each locale in `sluggable.locales.supported`, so set it
first too.

## Usage

Stock a product — every product is created with a default variant:

```php
use RoundlyConsulting\Money\Money;
use RoundlyConsulting\Shops\Facades\Shops;
use RoundlyConsulting\Shops\Products\Product;

$product = Product::create(['name' => 'Sparkling Water']);

$variant = $product->defaultVariant;
$variant->update(['price' => Money::ofMinor(199, 'EUR')]);

Shops::inventory($variant)->receive(50, note: 'Opening stock');
```

Then sell it — cart, checkout, payment and fulfilment:

```php
use RoundlyConsulting\Shops\Cart\Cart;
use RoundlyConsulting\Shops\Orders\DataTransferObjects\Address;
use RoundlyConsulting\Shops\Orders\DataTransferObjects\PlaceOrderData;

$cart = Cart::create(['currency' => 'EUR']);
Shops::cart($cart)->add($variant, 2);
Shops::cart($cart)->price()->getFinalPrice();   // 3.98 EUR, tax included

$order = Shops::cart($cart)->checkout(new PlaceOrderData(
    shipping: new Address('Ada Lovelace', '1 Analytical Way', 'London', 'EC1A 1AA', 'GB'),
));                                             // stock reserved, cart emptied, OrderPlaced fired

Shops::order($order)->charge();                 // through your PaymentGateway → Paid
Shops::order($order)->fulfil();                 // the reservation becomes a sale
```

<!-- roundly-docs:start -->
## Documentation

The full documentation — configuration, every feature and its API, and testing — lives on our
website: **[roundly-consulting.com/open-source/docs/shops-for-laravel](https://roundly-consulting.com/open-source/docs/shops-for-laravel?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=shops-for-laravel)**

Release notes are in [CHANGELOG.md](CHANGELOG.md). To contribute, see the
[contributing guide](https://github.com/roundly-consulting/.github/blob/main/CONTRIBUTING.md).
<!-- roundly-docs:end -->

<!-- roundly-support:start -->
## Support our work

This package is free and open source, built and maintained by
[Roundly Consulting](https://roundly-consulting.com/open-source?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=shops-for-laravel).
If it saves you time, please consider supporting our open-source work — a one-time donation, a
monthly pledge on Patreon or a crypto donation helps fund maintenance, new features and new
packages.

<a href="https://donate.stripe.com/dRmeVe8FX5PF1Qd9pXcEw00"><img src="https://img.shields.io/badge/Donate-Support%20Roundly%20open%20source-F24E29?style=for-the-badge&logo=stripe&logoColor=white" alt="Donate to Roundly open source"></a>
<a href="https://www.patreon.com/cw/roundly"><img src="https://img.shields.io/badge/Patreon-Become%20a%20patron-F96854?style=for-the-badge&logo=patreon&logoColor=white" alt="Become a patron on Patreon"></a>
<a href="https://roundly-consulting.com/support-us?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=shops-for-laravel#crypto"><img src="https://img.shields.io/badge/Crypto-BTC%20%C2%B7%20ETH%20%C2%B7%20BNB%20%C2%B7%20SOL-F7931A?style=for-the-badge&logo=bitcoin&logoColor=white" alt="Donate crypto: BTC, ETH, BNB or SOL"></a>
<!-- roundly-support:end -->

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
