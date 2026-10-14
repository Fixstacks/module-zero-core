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
 * Header greeting as an HTML fragment, replacing Knockout's
 * `customer().fullname` binding. Same shape as Wishlistcounter.
 */
class Welcome implements PrivateFragmentActionInterface
{
    private const BLOCK_NAME = 'zero.customer.welcome';

    /**
     * @param LayoutFactory $resultLayoutFactory
     */
    public function __construct(
        private readonly LayoutFactory $resultLayoutFactory
    ) {
    }

    /**
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
