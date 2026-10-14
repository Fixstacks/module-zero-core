<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Core\ViewModel;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * Whether the customer is logged in, for templates that branch on it (the
 * account menu shows "My Account" only to customers; stock's Account\Link
 * always shows it).
 *
 * Session-dependent: use it only in private output (e.g. the accountlinks
 * fragment), never in FPC-cached page HTML.
 */
class CustomerLoginState implements ArgumentInterface
{
    /**
     * @param CustomerSession $customerSession
     */
    public function __construct(
        private readonly CustomerSession $customerSession
    ) {
    }

    /**
     * Whether a real customer is currently logged in.
     *
     * @return bool
     */
    public function isLoggedIn(): bool
    {
        return $this->customerSession->isLoggedIn();
    }
}
