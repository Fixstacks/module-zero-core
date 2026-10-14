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
 * Review\Block\Product\View::getReviewsCollection() calls
 * getProduct()->getId() unconditionally. Varnish's ESI sub-request for
 * catalog.topnav rebuilds the whole layout of a product page without loading
 * the product, so it crashed inside the ESI slot (ESI is currently disabled in
 * ops/varnish/default.vcl; this lets it be re-enabled).
 *
 * Returns an always-empty Collection, not null: ListView::_prepareLayout()
 * calls load()/addRateVotes() on it and hands it to the toolbar. Filtering on
 * review_id = 0 ensures it can never accidentally list every review.
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
