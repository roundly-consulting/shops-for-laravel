<?php

declare(strict_types=1);

use RoundlyConsulting\Shops\Orders\NumberGenerators\DefaultNumberGenerator;

return [

    /*
    |--------------------------------------------------------------------------
    | Tax Rate
    |--------------------------------------------------------------------------
    |
    | The percentage tax rate applied to an order's price (after any discount
    | and shipping). Expressed as a whole number, e.g. 20 means 20%.
    |
    */

    'tax_rate' => env('SHOPS_TAX_RATE', 20),

    'orders' => [

        /*
        |----------------------------------------------------------------------
        | Order Number Generator
        |----------------------------------------------------------------------
        |
        | The class used to generate an order's human-readable number when it is
        | created. It must implement
        | RoundlyConsulting\Shops\Orders\NumberGenerators\NumberGenerator.
        |
        */

        'number_generator' => DefaultNumberGenerator::class,

        /*
        |----------------------------------------------------------------------
        | Coupon Model
        |----------------------------------------------------------------------
        |
        | The Eloquent model backing an order's coupon relation. It should
        | implement RoundlyConsulting\Shops\Contracts\Coupon so the order price
        | can apply its discount. Leave the default if your application does not
        | use coupons; the relation simply resolves to null.
        |
        */

        'coupon_model' => env('SHOPS_COUPON_MODEL'),

    ],

];
