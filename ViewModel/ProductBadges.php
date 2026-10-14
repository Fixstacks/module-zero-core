<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Core\ViewModel;

use Magento\Catalog\Model\Product;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Zero\Core\Model\Pricing\CachedPrices;

/**
 * "New" and "Sale" badges (product/badges.phtml).
 * - New: today is within news_from_date/news_to_date (store time zone), as
 *   stock's New Products widget reads them; no "from" date, not new.
 * - Sale: final price below regular, with the rounded percentage; for a
 *   configurable its cheapest variant's. Prices from CachedPrices: computed
 *   on every render they cost a 12-product list ~55 ms.
 */
class ProductBadges implements ArgumentInterface
{
    public function __construct(
        private readonly TimezoneInterface $timezone,
        private readonly CachedPrices $prices
    ) {
    }

    /**
     * @return array<int, array{code: string, label: string}>
     */
    public function getBadges(Product $product): array
    {
        $badges = [];
        if ($this->isNew($product)) {
            $badges[] = ['code' => 'new', 'label' => (string) __('New')];
        }
        $discount = $this->discountPercent($product);
        if ($discount > 0) {
            $badges[] = ['code' => 'sale', 'label' => (string) __('Sale -%1%', $discount)];
        }

        return $badges;
    }

    private function isNew(Product $product): bool
    {
        $from = (string) $product->getData('news_from_date');
        if ($from === '') {
            return false;
        }

        return $this->timezone->isScopeDateInInterval(
            $product->getStoreId(),
            $from,
            $product->getData('news_to_date') ?: null
        );
    }

    private function discountPercent(Product $product): int
    {
        ['regular' => $regular, 'final' => $final] = $this->prices->get($product);
        if ($regular <= 0 || $final >= $regular) {
            return 0;
        }

        return (int) round((1 - $final / $regular) * 100);
    }
}
