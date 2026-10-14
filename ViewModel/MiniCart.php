<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Core\ViewModel;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Quote\Model\Quote\Item;

/**
 * The current quote for the mini-cart fragments (count badge and popover).
 *
 * Doesn't reuse stock's Cart\Sidebar: its item renderers need a
 * rendererListName that stock only configures for Knockout, so
 * getItemHtml() would throw. Items show name/qty/price, without
 * configurable-option detail.
 *
 * getFormKey() serves the qty-update/remove forms (Zero\Checkout
 * Cart\Update); the fragment is never FPC-cached, so a plain value is fine.
 */
class MiniCart implements ArgumentInterface
{
    /**
     * @param CheckoutSession $checkoutSession
     * @param PriceCurrencyInterface $priceCurrency
     * @param UrlInterface $urlBuilder
     * @param FormKey $formKey
     */
    public function __construct(
        private readonly CheckoutSession $checkoutSession,
        private readonly PriceCurrencyInterface $priceCurrency,
        private readonly UrlInterface $urlBuilder,
        private readonly FormKey $formKey
    ) {
    }

    public function getFormKey(): string
    {
        return $this->formKey->getFormKey();
    }

    /**
     * @return Item[]
     */
    public function getItems(): array
    {
        return array_values($this->checkoutSession->getQuote()->getAllVisibleItems());
    }

    public function getItemCount(): int
    {
        return (int) $this->checkoutSession->getQuote()->getItemsQty();
    }

    public function getFormattedSubtotal(): string
    {
        return $this->formatQuoteAmount((float) $this->checkoutSession->getQuote()->getSubtotal());
    }

    public function getFormattedItemPrice(Item $item): string
    {
        return $this->formatQuoteAmount((float) $item->getRowTotal());
    }

    /**
     * Quote amounts are already in the quote's currency: formatted, not
     * converted again (that multiplied them by the rate in store views that
     * show another currency).
     */
    private function formatQuoteAmount(float $amount): string
    {
        return $this->priceCurrency->format(
            $amount,
            false,
            PriceCurrencyInterface::DEFAULT_PRECISION,
            null,
            $this->checkoutSession->getQuote()->getQuoteCurrencyCode() ?: null
        );
    }

    /**
     * Stock's checkout URL: Zero_Checkout's RedirectLegacyCheckout plugin decides
     * where it goes, so Zero_Core works without Zero_Checkout installed.
     */
    public function getCheckoutUrl(): string
    {
        return $this->urlBuilder->getUrl('checkout/index/index', ['_secure' => true]);
    }

    public function getCartUrl(): string
    {
        return $this->urlBuilder->getUrl('checkout/cart');
    }

    /**
     * Product thumbnail URL (the base image listings use).
     */
    public function getThumbnailUrl(Item $item): ?string
    {
        $product = $item->getProduct();
        $image = $product->getSmallImage() ?: $product->getImage();
        if (!$image || $image === 'no_selection') {
            return null;
        }

        return $this->urlBuilder->getBaseUrl(['_type' => UrlInterface::URL_TYPE_MEDIA]) . 'catalog/product' . $image;
    }
}
