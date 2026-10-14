<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Core\ViewModel;

use Magento\Catalog\Helper\Product\Compare as CompareHelper;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * Compare item count for the counter fragment (same getItemCount() as the
 * stock customer-data section). Session-based, so it works for guests too.
 */
class CompareCounter implements ArgumentInterface
{
    /**
     * @param CompareHelper $compareHelper
     */
    public function __construct(
        private readonly CompareHelper $compareHelper
    ) {
    }

    /**
     * The real, current item count — 0 for an empty comparison list.
     */
    public function getItemCount(): int
    {
        return (int) $this->compareHelper->getItemCount();
    }
}
