<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Core\Model\Social\Source;

use Magento\Catalog\Helper\Data as CatalogHelper;
use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Zero\Core\Model\Social\SourceInterface;
use Zero\Core\Model\Social\Text;

/**
 * A product page: its name, a description (meta description, else short
 * description, else the start of the description), main image, price and
 * whether it is in stock.
 */
class Product implements SourceInterface
{
    public function __construct(
        private readonly CatalogHelper $catalogHelper,
        private readonly ImageHelper $imageHelper,
        private readonly PriceCurrencyInterface $priceCurrency
    ) {
    }

    public function describe(): ?array
    {
        $product = $this->catalogHelper->getProduct();
        if (!$product || !$product->getId()) {
            return null;
        }
        $image = '';
        if ($product->getImage() && $product->getImage() !== 'no_selection') {
            $image = $this->imageHelper->init($product, 'product_base_image')->getUrl();
        }
        $extra = [
            'product:availability' => $product->isSalable() ? 'in stock' : 'out of stock',
            'product:price:currency' => (string) $this->priceCurrency->getCurrency()->getCode(),
        ];
        $price = (float) $product->getPriceInfo()->getPrice('final_price')->getAmount()->getValue();
        if ($price > 0) {
            $extra['product:price:amount'] = (string) round($price, 2);
        }

        return [
            'type' => 'product',
            'title' => (string) ($product->getData('meta_title') ?: $product->getName()),
            'description' => Text::first([
                (string) $product->getData('meta_description'),
                (string) $product->getData('short_description'),
                (string) $product->getData('description'),
            ]),
            'image' => $image,
            'image_alt' => (string) $product->getName(),
            'url' => (string) $product->getProductUrl(),
            'extra' => $extra,
        ];
    }
}
