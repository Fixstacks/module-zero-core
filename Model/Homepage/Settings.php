<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Core\Model\Homepage;

use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * The homepage settings (Stores > Configuration > Zero > Homepage), each with
 * a default that works for any catalog:
 * - departments: chosen top-level categories, or the root's first four;
 * - new arrivals: categories left out, and the "View all" category (none: no link);
 * - Zero's promotional sections: off unless on; images from product image
 *   paths, or else from the featured products.
 */
class Settings
{
    private const PATH = 'zero_theme/homepage/';
    public const MAX_DEPARTMENTS = 4;

    /** @var array<int, Category[]> store id => departments */
    private array $departments = [];

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly CategoryCollectionFactory $categoryCollectionFactory,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * Active departments of the current store, at most four, in admin position order.
     *
     * @return Category[]
     */
    public function departments(): array
    {
        $store = $this->storeManager->getStore();
        $storeId = (int) $store->getId();
        if (isset($this->departments[$storeId])) {
            return $this->departments[$storeId];
        }
        $rootId = (int) $store->getRootCategoryId();
        if (!$rootId) {
            return $this->departments[$storeId] = [];
        }
        $collection = $this->categoryCollectionFactory->create()
            ->setStoreId($storeId)
            ->addAttributeToSelect(['name', 'url_key'])
            ->addAttributeToFilter('is_active', 1)
            ->addFieldToFilter('parent_id', $rootId)
            ->addUrlRewriteToResult()
            ->setOrder('position', 'ASC')
            ->setPageSize(self::MAX_DEPARTMENTS);
        $chosen = $this->ids('departments');
        if ($chosen) {
            $collection->addFieldToFilter('entity_id', ['in' => $chosen]);
        }

        return $this->departments[$storeId] = array_values($collection->getItems());
    }

    /**
     * Category ids whose products stay out of the new arrivals.
     *
     * @return int[]
     */
    public function newArrivalsExcluded(): array
    {
        return $this->ids('new_arrivals_exclude');
    }

    /**
     * Where the new arrivals' "View all" link goes, or null for no link.
     */
    public function newArrivalsUrl(): ?string
    {
        $id = (int) $this->value('new_arrivals_link');
        if (!$id) {
            return null;
        }
        $category = $this->categoryCollectionFactory->create()
            ->setStoreId((int) $this->storeManager->getStore()->getId())
            ->addAttributeToSelect(['name', 'url_key'])
            ->addAttributeToFilter('is_active', 1)
            ->addFieldToFilter('entity_id', $id)
            ->addUrlRewriteToResult()
            ->setPageSize(1)
            ->getFirstItem();

        return $category->getId() ? (string) $category->getUrl() : null;
    }

    public function showPromo(): bool
    {
        return $this->scopeConfig->isSetFlag(self::PATH . 'promo', ScopeInterface::SCOPE_STORE);
    }

    /**
     * A promotional section's image as a catalog product image path
     * ("/w/t/wt06-blue_main_1.jpg"), or '' to use a featured product's.
     */
    public function promoImage(string $which): string
    {
        $path = trim((string) $this->value('promo_image_' . $which));

        return $path === '' ? '' : '/' . ltrim($path, '/');
    }

    /**
     * @return int[]
     */
    private function ids(string $field): array
    {
        return array_values(array_filter(array_map('intval', explode(',', (string) $this->value($field)))));
    }

    private function value(string $field): mixed
    {
        return $this->scopeConfig->getValue(self::PATH . $field, ScopeInterface::SCOPE_STORE);
    }
}
