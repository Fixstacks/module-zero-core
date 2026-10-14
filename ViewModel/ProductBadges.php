<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Core\ViewModel;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Pricing\Price\FinalPrice;
use Magento\Catalog\Pricing\Price\RegularPrice;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * "New" and "Sale" badges for product cards and the product page
 * (Zero_Core::product/badges.phtml).
 *
 * - New: the product's "Set Product as New From/To" dates (news_from_date /
 *   news_to_date) include today in the store's timezone, the same dates
 *   stock's New Products widget reads. No "from" date means not new.
 * - Sale: the final price (special price, catalog price rule, tier for qty 1)
 *   is below the regular price; the label carries the rounded percentage.
 *   For a configurable both are its cheapest variant's, as the price box shows.
 */
class ProductBadges implements ArgumentInterface
{
    public function __construct(
        private readonly TimezoneInterface $timezone
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
        $priceInfo = $product->getPriceInfo();
        $regular = (float) $priceInfo->getPrice(RegularPrice::PRICE_CODE)->getAmount()->getValue();
        $final = (float) $priceInfo->getPrice(FinalPrice::PRICE_CODE)->getAmount()->getValue();
        if ($regular <= 0 || $final >= $regular) {
            return 0;
        }

        return (int) round((1 - $final / $regular) * 100);
    }
}
