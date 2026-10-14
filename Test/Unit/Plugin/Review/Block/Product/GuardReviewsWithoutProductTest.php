<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Core\Test\Unit\Plugin\Review\Block\Product;

use Magento\Catalog\Model\Product;
use Magento\Review\Block\Product\View;
use Magento\Review\Model\ResourceModel\Review\Collection;
use Magento\Review\Model\ResourceModel\Review\CollectionFactory;
use PHPUnit\Framework\TestCase;
use Zero\Core\Plugin\Review\Block\Product\GuardReviewsWithoutProduct;

class GuardReviewsWithoutProductTest extends TestCase
{
    public function testWithoutProductReturnsEmptyCollectionInsteadOfCrashing(): void
    {
        $collection = $this->createMock(Collection::class);
        $collection->expects($this->once())->method('addFieldToFilter')
            ->with('review_id', ['eq' => 0])->willReturnSelf();
        $factory = $this->createStub(CollectionFactory::class);
        $factory->method('create')->willReturn($collection);
        $block = $this->createStub(View::class);
        $block->method('getProduct')->willReturn(null);

        $result = (new GuardReviewsWithoutProduct($factory))->aroundGetReviewsCollection(
            $block,
            fn () => $this->fail('stock method must not run without a product')
        );

        $this->assertSame($collection, $result);
    }

    public function testWithProductUsesStockCollection(): void
    {
        $stock = $this->createStub(Collection::class);
        $block = $this->createStub(View::class);
        $block->method('getProduct')->willReturn($this->createStub(Product::class));

        $result = (new GuardReviewsWithoutProduct($this->createStub(CollectionFactory::class)))
            ->aroundGetReviewsCollection($block, static fn () => $stock);

        $this->assertSame($stock, $result);
    }
}
