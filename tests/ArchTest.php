<?php

declare(strict_types=1);

arch('it does not use debugging functions')
    ->expect(['dd', 'dump', 'ray', 'var_dump'])
    ->not->toBeUsed();

arch('it declares strict types')
    ->expect('RoundlyConsulting\Shops')
    ->toUseStrictTypes();

arch('it does not depend on acme')
    ->expect('RoundlyConsulting\Shops')
    ->not->toUse('Acme');
