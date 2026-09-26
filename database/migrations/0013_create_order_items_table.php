<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->nullable()->constrained('shops')->nullOnDelete();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->string('name');
            $table->string('sku')->nullable();
            $table->smallInteger('quantity')->default(1);
            $table->money('price', currency: 'currency'); // decimal(38,0) + currency code
            $table->string('tax_class')->default('standard');
            // The rate (basis points) and label the line was taxed at, snapshotted when it was
            // added; null resolves the tax class live (an item written around AddOrderItemAction).
            $table->unsignedInteger('tax_rate')->nullable();
            $table->string('tax_label')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }
};
