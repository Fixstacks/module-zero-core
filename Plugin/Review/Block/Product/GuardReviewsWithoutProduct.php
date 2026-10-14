<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Core\Plugin\Review\Block\Product;

use Magento\Review\Block\Product\View;
use Magento\Review\Model\ResourceModel\Review\Collection;
use Magento\Review\Model\ResourceModel\Review\CollectionFactory;

/**
 * getReviewsCollection() calls getProduct()->getId() unconditionally, and
 * Varnish's ESI request for catalog.topnav builds a product page's layout
 * without a product (ESI is off in ops/varnish/default.vcl; this allows it).
 * Returns an always-empty collection (review_id = 0), not null: ListView
 * still loads it.
 */
class GuardReviewsWithoutProduct
{
    public function __construct(
        private readonly CollectionFactory $collectionFactory
    ) {
    }

    /**
     * @param View $subject
     * @param callable $proceed
     * @return Collection
     */
    public function aroundGetReviewsCollection(View $subject, callable $proceed)
    {
        if (!$subject->getProduct()) {
            return $this->collectionFactory->create()->addFieldToFilter('review_id', ['eq' => 0]);
        }
        return $proceed();
    }
}
