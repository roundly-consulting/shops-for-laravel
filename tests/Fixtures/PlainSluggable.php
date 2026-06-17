<?php

declare(strict_types=1);

namespace RoundlyConsulting\Shops\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Shops\Concerns\HasSlug;

/**
 * A non-translatable model using the slug concern, exercising the single-string
 * slug path.
 *
 * @property string $name
 * @property string $slug
 */
final class PlainSluggable extends Model
{
    use HasSlug;

    protected $table = 'plain_sluggables';

    protected $guarded = [];
}
