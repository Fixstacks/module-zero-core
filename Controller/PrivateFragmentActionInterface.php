<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Core\Controller;

use Magento\Framework\App\Action\HttpGetActionInterface;

/**
 * Marker for the per-visitor HTMX fragment controllers (mini-cart/wishlist/
 * compare counters, welcome, account links, form_key, flash messages).
 *
 * Http::launch() enables the page cache plugin for any ResultInterface,
 * Result\Layout included, so these were being FPC-cached and shared between
 * visitors. Plugin\Controller\PreventFragmentCaching sends no-cache headers
 * for every implementor.
 */
interface PrivateFragmentActionInterface extends HttpGetActionInterface
{
}
