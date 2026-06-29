<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Addresses\Contracts\Addressable;
use RoundlyConsulting\Addresses\Traits\HasAddresses;
use RoundlyConsulting\Coupons\Concerns\HasCoupons;
use RoundlyConsulting\Credits\Interfaces\Creditable;
use RoundlyConsulting\Credits\Traits\HasCredits;

/**
 * A buyer fixture used to exercise the commerce-trio integrations (addresses,
 * store credit, coupon redemption, verified purchase). Stands in for the host
 * application's User model.
 *
 * @property int $id
 * @property string|null $name
 */
final class Customer extends Model implements Addressable, Creditable
{
    use HasAddresses;
    use HasCoupons;
    use HasCredits;

    protected $table = 'customers';

    protected $guarded = [];
}
