<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Core\ViewModel;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Header greeting for the welcome fragment: the customer's full name (what
 * Knockout's `customer().fullname` showed) or the guest message from
 * `design/header/welcome`.
 *
 * Returns raw strings; the template escapes. Stock's getWelcome() quote-escapes
 * for a Knockout JS string literal, which doesn't apply here.
 */
class CustomerWelcome implements ArgumentInterface
{
    private const XML_PATH_WELCOME = 'design/header/welcome';

    /**
     * @param CustomerSession $customerSession
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        private readonly CustomerSession $customerSession,
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    /**
     * The real greeting to show right now — "Welcome, {name}!" for a logged-in customer, the configured
     * store message otherwise.
     */
    public function getMessage(): string
    {
        if ($this->customerSession->isLoggedIn()) {
            $name = $this->customerSession->getCustomer()->getName();
            return (string) __('Welcome, %1!', $name);
        }

        $configured = (string) $this->scopeConfig->getValue(self::XML_PATH_WELCOME, ScopeInterface::SCOPE_STORE);
        return (string) __($configured);
    }
}
