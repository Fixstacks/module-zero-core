<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Core\Model\Pricing;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Pricing\Price\FinalPrice;
use Magento\Catalog\Pricing\Price\RegularPrice;
use Magento\Framework\App\Cache\StateInterface as CacheState;
use Magento\Framework\App\Cache\Type\Block as BlockCache;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * A product's regular and final price (as its price box shows them), from the
 * block cache like core's price boxes, for badges and GA4 item lists: a
 * configurable's prices load its variants (~5 queries). Keyed by product,
 * store, group, currency and date (price rules); cleaned by product tags.
 */
class CachedPrices
{
    private const CACHE_LIFETIME = 86400;

    /** @var array<string, array{regular: float, final: float}> */
    private array $loaded = [];

    public function __construct(
        private readonly CacheInterface $cache,
        private readonly CacheState $cacheState,
        private readonly StoreManagerInterface $storeManager,
        private readonly TimezoneInterface $timezone
    ) {
    }

    /**
     * @return array{regular: float, final: float}
     */
    public function get(Product $product): array
    {
        if (!$product->getId() || !$this->cacheState->isEnabled(BlockCache::TYPE_IDENTIFIER)) {
            return $this->compute($product);
        }
        $store = $this->storeManager->getStore();
        $key = 'zero_prices_' . implode('_', [
            (int) $product->getId(),
            (int) $store->getId(),
            (string) ($product->getCustomerGroupId() ?? ''),
            (string) $store->getCurrentCurrencyCode(),
            $this->timezone->scopeDate($store)->format('Y-m-d'),
        ]);
        if (isset($this->loaded[$key])) {
            return $this->loaded[$key];
        }
        $cached = $this->cache->load($key);
        if (is_string($cached) && preg_match('/^(-?[\d.]+)\|(-?[\d.]+)$/', $cached, $m)) {
            return $this->loaded[$key] = ['regular' => (float) $m[1], 'final' => (float) $m[2]];
        }
        $prices = $this->compute($product);
        $this->cache->save(
            $prices['regular'] . '|' . $prices['final'],
            $key,
            array_merge($product->getIdentities(), [BlockCache::CACHE_TAG]),
            self::CACHE_LIFETIME
        );

        return $this->loaded[$key] = $prices;
    }

    /**
     * @return array{regular: float, final: float}
     */
    private function compute(Product $product): array
    {
        $priceInfo = $product->getPriceInfo();

        return [
            'regular' => (float) $priceInfo->getPrice(RegularPrice::PRICE_CODE)->getAmount()->getValue(),
            'final' => (float) $priceInfo->getPrice(FinalPrice::PRICE_CODE)->getAmount()->getValue(),
        ];
    }
}
