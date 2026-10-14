<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Core\ViewModel;

use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Wishlist\Helper\Data as WishlistHelper;

/**
 * Wishlist item count for the counter fragment (same getItemCount() as the
 * stock customer-data section).
 */
class WishlistCounter implements ArgumentInterface
{
    /**
     * @param WishlistHelper $wishlistHelper
     */
    public function __construct(
        private readonly WishlistHelper $wishlistHelper
    ) {
    }

    /**
     * Whether the wishlist feature is enabled at all (guests and disabled stores both render nothing).
     */
    public function isEnabled(): bool
    {
        return $this->wishlistHelper->isAllow();
    }

    /**
     * The real, current item count — 0 for a guest or an empty wishlist.
     */
    public function getItemCount(): int
    {
        return (int) $this->wishlistHelper->getItemCount();
    }
}
