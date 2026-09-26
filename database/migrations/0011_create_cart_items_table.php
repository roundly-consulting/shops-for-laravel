<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cart_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cart_id')->constrained('carts')->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->string('name');
            $table->string('sku')->nullable();
            $table->smallInteger('quantity')->default(1);
            $table->money('price', currency: 'currency'); // decimal(38,0) + currency code
            $table->string('tax_class')->default('standard');
            $table->timestamps();
            $table->softDeletes();
        });
    }
};
