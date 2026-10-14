<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Core\ViewModel\Homepage;

use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Top-level categories for the homepage's department tiles, read from the
 * catalog so renames/reordering show up without editing CMS content.
 * EXCLUDED_URL_KEYS drops "What's New" (a dynamic listing) and "Sale" (a
 * cross-department filter).
 */
class CategoryShowcase implements ArgumentInterface
{
    private const EXCLUDED_URL_KEYS = ['whats-new', 'sale'];
    private const MAX_CATEGORIES = 4;

    /**
     * @param CategoryCollectionFactory $categoryCollectionFactory
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        private readonly CategoryCollectionFactory $categoryCollectionFactory,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * Active children of the store root in admin position order, capped at four
     * (the 4-column tile grid). Empty if there are none.
     *
     * @return Category[]
     */
    public function getCategories(): array
    {
        // The current store's root: other websites have roots of their own.
        $rootId = (int) $this->storeManager->getStore()->getRootCategoryId();
        if (!$rootId) {
            return [];
        }

        $children = $this->categoryCollectionFactory->create()
            ->addAttributeToSelect(['name', 'url_key'])
            ->addAttributeToFilter('is_active', 1)
            ->addFieldToFilter('parent_id', $rootId)
            ->addFieldToFilter('url_key', ['nin' => self::EXCLUDED_URL_KEYS])
            ->addUrlRewriteToResult()
            ->setOrder('position', 'ASC')
            ->setPageSize(self::MAX_CATEGORIES);

        return $children->getItems();
    }
}
