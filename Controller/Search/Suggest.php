<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Core\Controller\Search;

use Zero\Core\Controller\PrivateFragmentActionInterface;
use Magento\Framework\View\Result\Layout;
use Magento\Framework\View\Result\LayoutFactory;

/**
 * Live search suggestions for the header search box as an HTML fragment,
 * replacing stock's `quickSearch` widget (not run on this theme). Same data
 * source as stock's `search/ajax/suggest` (see ViewModel\SearchSuggest).
 *
 * A private fragment: the response varies by `q`, so it must not be FPC-cached.
 */
class Suggest implements PrivateFragmentActionInterface
{
    private const BLOCK_NAME = 'zero.search.suggest';

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
