<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Core\ViewModel;

use Magento\Catalog\Model\Layer\Resolver as LayerResolver;
use Magento\Catalog\Model\Product;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Cache\StateInterface as CacheState;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Http\Context as HttpContext;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Framework\View\DesignInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Framework\View\Element\BlockInterface;
use Magento\Framework\App\Cache\Type\Block as BlockCache;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Product list cards from the block cache, one entry per card, as Hyvä does:
 * a card's price, swatches, image and badges are worked out once an hour
 * instead of on every uncached list page (~half of a category's rendering).
 *
 * Keyed by everything a card's HTML depends on: product, store, theme, the
 * HTTP context (customer group, currency, tax), the store's date (price rules,
 * "New"/"Sale"), the category (product URLs may carry it) and the list's view
 * settings. Cleaned with the product's cache tags. What belongs to the request
 * stays out of the stored HTML: the form key (a token, put back on output)
 * and the caller's $replace placeholders (Add to Cart's URL, which carries the
 * current page). Off: Stores > Configuration > Zero > Theme > Performance.
 */
class ProductCardCache implements ArgumentInterface
{
    private const CONFIG_ENABLED = 'zero_theme/performance/product_card_cache';
    private const LIFETIME = 3600;
    private const FORM_KEY_TOKEN = '%%zero-form-key%%';

    private ?bool $enabled = null;
    private ?string $requestKey = null;

    public function __construct(
        private readonly CacheInterface $cache,
        private readonly CacheState $cacheState,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly StoreManagerInterface $storeManager,
        private readonly DesignInterface $design,
        private readonly HttpContext $httpContext,
        private readonly CustomerSession $customerSession,
        private readonly TimezoneInterface $timezone,
        private readonly LayerResolver $layerResolver,
        private readonly FormKey $formKey
    ) {
    }

    /**
     * The card's HTML: cached, or $render()'s output (then stored).
     *
     * @param Product $product
     * @param BlockInterface $list The list block whose template renders the card
     * @param array $vary The list's own view settings (view mode, image area, ...)
     * @param callable(): string $render Renders the card
     * @param array<string, string> $replace Placeholders in the card => this request's values
     * @return string
     */
    public function render(Product $product, BlockInterface $list, array $vary, callable $render, array $replace = []): string
    {
        if (!$product->getId() || !$this->isEnabled()) {
            return strtr($render(), $replace);
        }
        $formKey = $this->formKey->getFormKey();
        $key = 'zero_card_' . sha1($this->requestKey() . '|' . json_encode([
            (int) $product->getId(),
            method_exists($list, 'getTemplateFile') ? (string) $list->getTemplateFile() : get_class($list),
            $vary,
        ]));
        $html = $this->cache->load($key);
        if (!is_string($html) || $html === '') {
            $html = str_replace($formKey, self::FORM_KEY_TOKEN, $render());
            $this->cache->save(
                $html,
                $key,
                array_merge($product->getIdentities(), [BlockCache::CACHE_TAG]),
                self::LIFETIME
            );
        }

        return strtr($html, [self::FORM_KEY_TOKEN => $formKey] + $replace);
    }

    private function isEnabled(): bool
    {
        return $this->enabled ??= $this->cacheState->isEnabled(BlockCache::TYPE_IDENTIFIER)
            && $this->scopeConfig->isSetFlag(self::CONFIG_ENABLED, ScopeInterface::SCOPE_STORE);
    }

    /**
     * The key's part shared by every card of this request.
     */
    private function requestKey(): string
    {
        if ($this->requestKey !== null) {
            return $this->requestKey;
        }
        $store = $this->storeManager->getStore();
        $category = $this->layerResolver->get()->getCurrentCategory();

        return $this->requestKey = json_encode([
            (int) $store->getId(),
            (int) $this->design->getDesignTheme()->getId(),
            $this->httpContext->getVaryString(),
            (int) $this->customerSession->getCustomerGroupId(),
            (string) $store->getCurrentCurrencyCode(),
            $this->timezone->scopeDate($store)->format('Y-m-d'),
            $category ? (int) $category->getId() : 0,
        ]);
    }
}
