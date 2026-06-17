<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->nullableMorphs('shop');
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->integer('price');
            $table->string('currency');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['name', 'shop_id', 'shop_type']);
            $table->unique(['slug', 'shop_id', 'shop_type']);
        });
    }
};
