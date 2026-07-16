<?php

declare(strict_types=1);

use RoundlyConsulting\Shops\Tests\Fixtures\SwappedShopTestCase;
use RoundlyConsulting\Shops\Tests\TestCase;

uses(TestCase::class)->in('ArchTest.php', 'Feature', 'Unit');

// The model-swap proofs need `shops.shop_model` pointed at the host subclass BEFORE
// the providers boot, so they run on their own base case in their own directory —
// Pest binds a test case per directory, not per file.
uses(SwappedShopTestCase::class)->in('ModelSwap');
