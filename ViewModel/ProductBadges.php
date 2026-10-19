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
use Zero\Core\Api\ProductBadgeProviderInterface;
use Zero\Core\Model\Catalog\BadgeSettings;
use Zero\Core\Model\Pricing\CachedPrices;

/**
 * A product's badges (product/badges.phtml), each switched on in
 * Stores > Configuration > Zero > Theme > Product Badges:
 * - New: today is within news_from_date/news_to_date (store time zone), as
 *   stock's New Products widget reads them; no "from" date, not new.
 * - Sale: final price below regular, with the rounded percentage; for a
 *   configurable its cheapest variant's. Prices from CachedPrices: computed
 *   on every render they cost a 12-product list ~55 ms.
 * Plus the badges of modules registered as `providers`
 * (Api\ProductBadgeProviderInterface; Zero_ProductLabels: the product's own
 * badge, Only a few left, Free shipping), in their `sort` order.
 */
class ProductBadges implements ArgumentInterface
{
    private const NEW_SORT = 20;
    private const SALE_SORT = 30;

    /**
     * @param ProductBadgeProviderInterface[] $providers
     */
    public function __construct(
        private readonly TimezoneInterface $timezone,
        private readonly CachedPrices $prices,
        private readonly BadgeSettings $settings,
        private readonly array $providers = []
    ) {
    }

    /**
     * @return array<int, array{code: string, label: string}>
     */
    public function getBadges(Product $product): array
    {
        $badges = [];
        if ($this->settings->showsNew() && $this->isNew($product)) {
            $badges[] = ['code' => 'new', 'label' => (string) __('New'), 'sort' => self::NEW_SORT];
        }
        $discount = $this->settings->showsSale() ? $this->discountPercent($product) : 0;
        if ($discount > 0) {
            $badges[] = ['code' => 'sale', 'label' => (string) __('Sale -%1%', $discount), 'sort' => self::SALE_SORT];
        }
        foreach ($this->providers as $provider) {
            array_push($badges, ...$provider->getBadges($product));
        }
        // Stable: equal sorts keep their order.
        usort($badges, static fn (array $a, array $b) => $a['sort'] <=> $b['sort']);

        return array_map(static fn (array $badge) => ['code' => $badge['code'], 'label' => $badge['label']], $badges);
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
