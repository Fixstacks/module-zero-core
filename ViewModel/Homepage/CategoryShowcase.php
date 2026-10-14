<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Core\ViewModel\Homepage;

use Magento\Catalog\Model\Category;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Zero\Core\Model\Homepage\Settings;

/**
 * Top-level categories for the homepage's department tiles, read from the
 * catalog so renames/reordering show up without editing CMS content: the
 * departments chosen in Stores > Configuration > Zero > Homepage, or else
 * the store root's first four active categories (Model\Homepage\Settings).
 */
class CategoryShowcase implements ArgumentInterface
{
    public function __construct(
        private readonly Settings $settings
    ) {
    }

    /**
     * Up to four categories in admin position order; empty if there are none.
     *
     * @return Category[]
     */
    public function getCategories(): array
    {
        return $this->settings->departments();
    }
}
