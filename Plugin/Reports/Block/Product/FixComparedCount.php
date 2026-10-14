<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Core\Plugin\Reports\Block\Product;

use Magento\Reports\Block\Product\Compared;

/**
 * Compared::_toHtml() renders only if getCount() > 0, and that count is cached
 * per session (`product_index_compared_count`), refreshed only by the
 * compare-add observer in that same session. The widget's fragment runs in
 * another session, so it saw 0 and rendered nothing. This answers with a live
 * count. (Viewed doesn't call getCount().)
 */
class FixComparedCount
{
    /**
     * Live count instead of the session-cached one.
     *
     * @param Compared $subject
     * @param callable $proceed
     * @return int
     */
    public function aroundGetCount(Compared $subject, callable $proceed): int
    {
        return $subject->getItemsCollection()->count();
    }
}
