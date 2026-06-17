<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use RoundlyConsulting\Shops\Orders\Enums\Status;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->nullableMorphs('shop');
            $table->unsignedBigInteger('coupon_id')->nullable()->index();
            $table->string('number');
            $table->string('status')->default(Status::New->value);

            $table->json('billing_address')->nullable();
            $table->json('shipping_address')->nullable();

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

            $table->unique(['number', 'shop_id', 'shop_type']);
        });
    }
};
