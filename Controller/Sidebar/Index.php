<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Core\Controller\Sidebar;

use Magento\Catalog\Helper\Product\Compare as CompareHelper;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\View\Element\FormKey;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\LayoutFactory;
use Magento\Wishlist\Block\Customer\Sidebar as WishlistSidebar;
use Magento\Wishlist\ViewModel\WishlistData;
use Zero\Core\Controller\PrivateFragmentActionInterface;

/**
 * The sidebar's "Compare Products" and "My Wish List" blocks, behind the
 * hx-get shell in sidebar.additional (Zero_Core::sidebar/shell.phtml).
 *
 * Rendered inline, they could never show anything: Magento depersonalizes a
 * cacheable page's layout (no visitor, no customer), so both always showed their
 * empty state, even right after adding a product. Here each block renders only
 * when it has items — compare for any visitor, the wish list for a logged-in
 * customer — and an empty response leaves nothing in the sidebar.
 */
class Index implements PrivateFragmentActionInterface
{
    public function __construct(
        private readonly ResultFactory $resultFactory,
        private readonly LayoutFactory $layoutFactory,
        private readonly CompareHelper $compareHelper,
        private readonly CustomerSession $customerSession,
        private readonly WishlistData $wishlistData
    ) {
    }

    /**
     * @return Raw
     */
    public function execute()
    {
        $layout = $this->layoutFactory->create();
        // A fresh layout has no 'formkey' block; the templates' Remove/Add to Cart forms need it.
        $layout->createBlock(FormKey::class, 'formkey');

        $html = '';
        if ($this->compareHelper->getItemCount() > 0) {
            $html .= $layout->createBlock(Template::class, 'catalog.compare.sidebar')
                ->setTemplate('Magento_Catalog::product/compare/sidebar.phtml')
                ->toHtml();
        }

        if ($this->customerSession->isLoggedIn()) {
            /** @var WishlistSidebar $wishlist */
            $wishlist = $layout->createBlock(WishlistSidebar::class, 'wishlist_sidebar');
            $wishlist->setData('wishlistDataViewModel', $this->wishlistData);
            if ($wishlist->hasWishlistItems()) {
                $html .= $wishlist->setTemplate('Magento_Wishlist::sidebar.phtml')->toHtml();
            }
        }

        /** @var Raw $result */
        $result = $this->resultFactory->create(ResultFactory::TYPE_RAW);
        $result->setContents($html);

        return $result;
    }
}
