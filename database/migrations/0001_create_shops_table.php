<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shops', function (Blueprint $table): void {
            $table->id();
            $table->json('name');
            $table->json('slug');
            $table->string('currency', 3)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }
};
