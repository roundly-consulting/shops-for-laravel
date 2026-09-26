<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('sku');
            $table->string('name')->nullable();
            $table->money('price', currency: 'currency'); // decimal(38,0) + currency code
            $table->string('tax_class')->default('standard');
            $table->boolean('track_stock')->default(true);
            $table->integer('stock')->default(0);
            $table->integer('reserved')->default(0);
            $table->integer('position')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['product_id', 'sku']);
        });
    }
};
