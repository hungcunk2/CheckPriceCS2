<?php

namespace Tests\Unit;

use App\Support\InventoryItemFilter;
use PHPUnit\Framework\TestCase;

class InventoryItemFilterTest extends TestCase
{
    public function test_excludes_service_medal_from_trade_hold(): void
    {
        $desc = [
            'tradable' => 0,
            'market_hash_name' => '2024 Service Medal',
            'name' => '2024 Service Medal',
            'tags' => [
                ['category' => 'Type', 'localized_tag_name' => 'Medal'],
            ],
        ];

        $this->assertTrue(InventoryItemFilter::isExcludedPermanentCollectible($desc));
        $this->assertFalse(InventoryItemFilter::isTradeHoldDescription($desc));
    }

    public function test_includes_skin_with_cache_expiration_as_hold(): void
    {
        $desc = [
            'tradable' => 0,
            'market_hash_name' => 'AK-47 | Redline (Field-Tested)',
            'name' => 'AK-47 | Redline (Field-Tested)',
            'cache_expiration' => '2026-05-25T12:00:00Z',
            'tags' => [
                ['category' => 'Type', 'localized_tag_name' => 'Rifle'],
            ],
        ];

        $this->assertFalse(InventoryItemFilter::isExcludedPermanentCollectible($desc));
        $this->assertTrue(InventoryItemFilter::isTradeHoldDescription($desc));
        $this->assertNotNull(InventoryItemFilter::tradeUnlockAt($desc));
    }

    public function test_tradable_skin_not_in_hold(): void
    {
        $desc = [
            'tradable' => 1,
            'market_hash_name' => 'Desert Eagle | Printstream (Field-Tested)',
            'name' => 'Desert Eagle | Printstream (Field-Tested)',
        ];

        $this->assertTrue(InventoryItemFilter::isTradableDescription($desc));
        $this->assertFalse(InventoryItemFilter::isTradeHoldDescription($desc));
    }
}
