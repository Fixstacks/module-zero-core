<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Core\Test\Unit\Plugin\Reports\Block\Product;

use Magento\Reports\Block\Product\Compared;
use Magento\Reports\Model\ResourceModel\Product\Index\Collection\AbstractCollection;
use PHPUnit\Framework\TestCase;
use Zero\Core\Plugin\Reports\Block\Product\FixComparedCount;

class FixComparedCountTest extends TestCase
{
    public function testCountsLiveCollectionNotSessionCache(): void
    {
        $collection = $this->createStub(AbstractCollection::class);
        $collection->method('count')->willReturn(3);
        $block = $this->createStub(Compared::class);
        $block->method('getItemsCollection')->willReturn($collection);

        $count = (new FixComparedCount())->aroundGetCount($block, static fn () => 0);

        $this->assertSame(3, $count);
    }
}
