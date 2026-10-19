<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Core\Test\Unit\ViewModel;

use Magento\Catalog\Model\Product;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use PHPUnit\Framework\TestCase;
use Zero\Core\Api\ProductBadgeProviderInterface;
use Zero\Core\Model\Catalog\BadgeSettings;
use Zero\Core\Model\Pricing\CachedPrices;
use Zero\Core\ViewModel\ProductBadges;

class ProductBadgesTest extends TestCase
{
    /**
     * @param array<int, array{code: string, label: string, sort: int}> $provided
     */
    private function badges(bool $sale, float $regular, float $final, array $provided = []): array
    {
        $settings = $this->createStub(BadgeSettings::class);
        $settings->method('showsNew')->willReturn(true);
        $settings->method('showsSale')->willReturn($sale);
        $prices = $this->createStub(CachedPrices::class);
        $prices->method('get')->willReturn(['regular' => $regular, 'final' => $final]);
        $provider = $this->createStub(ProductBadgeProviderInterface::class);
        $provider->method('getBadges')->willReturn($provided);

        $viewModel = new ProductBadges($this->createStub(TimezoneInterface::class), $prices, $settings, [$provider]);

        return array_column($viewModel->getBadges($this->createStub(Product::class)), 'label', 'code');
    }

    public function testSaleWithItsPercentage(): void
    {
        $this->assertSame(['sale' => 'Sale -20%'], $this->badges(true, 150.0, 120.0));
        $this->assertSame([], $this->badges(true, 100.0, 100.0));
        $this->assertSame([], $this->badges(false, 150.0, 120.0));
    }

    public function testProvidersBadgesInTheirSortOrder(): void
    {
        $this->assertSame(
            ['custom' => 'Bestseller', 'sale' => 'Sale -20%', 'free-shipping' => 'Free shipping'],
            $this->badges(true, 150.0, 120.0, [
                ['code' => 'free-shipping', 'label' => 'Free shipping', 'sort' => 50],
                ['code' => 'custom', 'label' => 'Bestseller', 'sort' => 10],
            ])
        );
    }
}
