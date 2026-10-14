<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Core\Controller\Security;

use Zero\Core\Controller\PrivateFragmentActionInterface;
use Magento\Framework\View\Result\Layout;
use Magento\Framework\View\Result\LayoutFactory;

/**
 * Returns a current `form_key` hidden input as an HTML fragment.
 *
 * FPC serves the form_key baked into the cached page to every visitor, which
 * fails CSRF validation for anyone else's session ("Invalid Form Key") — Add to
 * Cart, wishlist, reviews, newsletter. Stock solves this with customer-data
 * (Knockout); here the session bootstrap's script (session/bootstrap.phtml)
 * fetches it once and writes it into every form_key input on the page.
 */
class Formkey implements PrivateFragmentActionInterface
{
    private const BLOCK_NAME = 'zero.security.formkey';

    /**
     * @param LayoutFactory $resultLayoutFactory
     */
    public function __construct(
        private readonly LayoutFactory $resultLayoutFactory
    ) {
    }

    /**
     * Render the fresh form_key fragment.
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
