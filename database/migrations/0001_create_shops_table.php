<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use RoundlyConsulting\Sluggable\DataTransferObjects\SlugIndexSpec;
use RoundlyConsulting\Sluggable\Schema\SlugIndexes;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shops', function (Blueprint $table): void {
            $table->id();
            $table->jsonb('name');
            $table->localizedSlug('slug');
            $table->currencyCode('currency', nullable: true);
            $table->timestamps();
            $table->softDeletes();
        });

        // One unique index per supported locale (sluggable.locales.supported); trashed
        // shops keep their slug reserved, matching the definition's default.
        SlugIndexes::ensure(SlugIndexSpec::localeMap('shops', 'slug'));
    }
};
