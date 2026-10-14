<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Core\Controller\Checkout;

use Zero\Core\Controller\PrivateFragmentActionInterface;
use Magento\Framework\View\Result\Layout;
use Magento\Framework\View\Result\LayoutFactory;

/**
 * Country → region cascade: a country <select> hx-gets this on change and
 * gets a populated region field back (stock fills it with JS).
 */
class Regions implements PrivateFragmentActionInterface
{
    private const BLOCK_NAME = 'zero.checkout.regions';

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
