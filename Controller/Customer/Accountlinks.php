<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Core\Controller\Customer;

use Zero\Core\Controller\PrivateFragmentActionInterface;
use Magento\Framework\View\Result\Layout;
use Magento\Framework\View\Result\LayoutFactory;

/**
 * The personalized account-menu links (Sign In vs Sign Out, My Account) as
 * an HTML fragment, so the cacheable header shell stays visitor-neutral
 * (Magento_Customer::account/toplinks.phtml). Layout: zero_customer_accountlinks.xml.
 */
class Accountlinks implements PrivateFragmentActionInterface
{
    private const BLOCK_NAME = 'top.links';

    /**
     * @param LayoutFactory $resultLayoutFactory
     */
    public function __construct(
        private readonly LayoutFactory $resultLayoutFactory
    ) {
    }

    /**
     * Render the fresh account-links fragment.
     *
     * @return Layout
     */
    public function execute()
    {
        $resultLayout = $this->resultLayoutFactory->create();
        $resultLayout->addDefaultHandle();
        $resultLayout->getLayout()->addOutputElement(self::BLOCK_NAME);

        return $resultLayout;
    }
}
