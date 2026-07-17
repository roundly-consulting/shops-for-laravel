<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use RoundlyConsulting\PackageToolkit\Enums\KeyType;

return new class extends Migration
{
    public function up(): void
    {
        $keyType = KeyType::fromConfig('shops.key_type');

        Schema::create('stock_adjustments', function (Blueprint $table) use ($keyType): void {
            $table->id();
            $table->foreignId('product_variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->integer('quantity');
            $table->string('reason');
            $table->morphKey('reference', $keyType, nullable: true);
            $table->string('note')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }
};
