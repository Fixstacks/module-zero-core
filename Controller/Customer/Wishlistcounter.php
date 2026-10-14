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
 * The personalized wishlist counter as an HTML fragment (replacing Knockout's
 * `wishlist().counter`). The cacheable page ships a placeholder that hx-gets this
 * (Magento_Wishlist::link.phtml).
 *
 * Renders the zero_customer_wishlistcounter handle (just the one block, no
 * `default` handle). addOutputElement() is required: layout XML's
 * `output="1"` isn't wired to it in this Magento version, so the response
 * would be empty.
 */
class Wishlistcounter implements PrivateFragmentActionInterface
{
    private const BLOCK_NAME = 'zero.customer.wishlistcounter';

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
