<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use RoundlyConsulting\PackageToolkit\Enums\KeyType;
use RoundlyConsulting\Shops\Orders\Enums\Status;

return new class extends Migration
{
    public function up(): void
    {
        $keyType = KeyType::fromConfig('shops.key_type');

        Schema::create('orders', function (Blueprint $table) use ($keyType): void {
            $table->id();
            $table->foreignId('shop_id')->nullable()->constrained('shops')->nullOnDelete();
            $table->unsignedBigInteger('coupon_id')->nullable()->index();
            $table->morphKey('customer', $keyType, nullable: true);
            $table->string('number');
            $table->string('status')->default(Status::New->value);

            // The order's own currency, snapshotted at creation (config changes never
            // re-denominate a placed order).
            $table->currencyCode('currency');
            // The catalog price type (gross / net) the order was priced under, snapshotted at
            // creation like the currency: flipping the config never re-prices a placed order.
            $table->string('price_type');
            // Store credit applied before the gateway charge; shares the order currency
            // column added above, so the macro skips it.
            $table->money('store_credit_applied', currency: 'currency', nullable: true);

            // The coupon discount granted at placement, snapshotted in the order currency:
            // a coupon that later expires, is revoked or runs out of uses (including the use
            // this order consumed) never re-prices a placed order.
            $table->money('discount', currency: 'currency', nullable: true);
            $table->boolean('free_shipping')->default(false);
            $table->string('coupon_code')->nullable();

            $table->jsonb('billing_address')->nullable();
            $table->jsonb('shipping_address')->nullable();

            $table->string('payment')->nullable();
            $table->string('shipping')->nullable();
            $table->timestamp('in_progress_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('fulfilled_at')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Globally unique — orders route-bind by number alone, and a composite with the
            // nullable shop_id would let shop-less orders share one (NULLs never collide).
            $table->unique('number');
        });
    }
};
