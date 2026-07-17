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

        Schema::create('carts', function (Blueprint $table) use ($keyType): void {
            $table->id();
            $table->foreignId('shop_id')->nullable()->constrained('shops')->nullOnDelete();
            $table->morphKey('owner', $keyType, nullable: true);
            $table->string('token')->nullable()->unique();
            $table->string('currency');
            $table->string('coupon_code')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }
};
