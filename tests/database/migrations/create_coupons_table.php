<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table): void {
            $table->id();
            $table->integer('value');
            $table->integer('usage')->default(0);
            $table->integer('max_usage')->default(1);
            $table->timestamps();
        });
    }
};
