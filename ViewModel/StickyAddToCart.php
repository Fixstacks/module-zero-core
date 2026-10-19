<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Core\ViewModel;

use Magento\Catalog\Model\Product;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * The product page's sticky Add to Cart bar (Magento_Catalog::
 * product/view/sticky-addtocart.phtml), on in Stores > Configuration >
 * Zero > Theme > Product Page.
 */
class StickyAddToCart implements ArgumentInterface
{
    private const DIRECT_TYPES = ['simple', 'virtual', 'downloadable'];

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    public function isEnabled(Product $product): bool
    {
        return $product->isSaleable()
            && $this->scopeConfig->isSetFlag('zero_theme/product_page/sticky_add_to_cart', ScopeInterface::SCOPE_STORE);
    }

    /**
     * Adds to the cart straight from the bar: a product with nothing to
     * choose. Otherwise ("Choose options") the bar takes the shopper to the
     * options (a size, a colour, a required custom option, a bundle's items).
     */
    public function addsDirectly(Product $product): bool
    {
        if (!in_array($product->getTypeId(), self::DIRECT_TYPES, true)) {
            return false;
        }
        foreach ($product->getOptions() ?? [] as $option) {
            if ($option->getIsRequire()) {
                return false;
            }
        }
        if ($product->getTypeId() === 'downloadable' && $product->getData('links_purchased_separately')) {
            return false;
        }

        return true;
    }
}
