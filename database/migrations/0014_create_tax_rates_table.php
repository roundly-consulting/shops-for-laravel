<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_rates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shop_id')->nullable()->constrained('shops')->cascadeOnDelete();
            $table->string('name');
            $table->string('tax_class')->default('standard');
            $table->string('country', 2)->nullable()->index();
            $table->unsignedInteger('rate');
            $table->boolean('is_default')->default(false);
            $table->unsignedInteger('priority')->default(0);
            $table->timestamps();

            $table->index(['shop_id', 'tax_class', 'country']);
        });
    }
};
