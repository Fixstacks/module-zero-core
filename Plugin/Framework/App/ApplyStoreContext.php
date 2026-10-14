<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Core\Plugin\Framework\App;

use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\Http\Context as HttpContext;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Session\SessionManagerInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManager;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Core sets the store and currency in the HTTP context (the X-Magento-Vary
 * cookie and page cache key) in a plugin on AbstractAction::dispatch(), which
 * controllers implementing ActionInterface directly (HTMX fragments, Zero
 * Checkout) skip. Their empty vary deleted the visitor's cookie, so another
 * store view could get, and fill, the default one's Varnish pages. This sets
 * the same values when core hasn't.
 */
class ApplyStoreContext
{
    public function __construct(
        private readonly HttpContext $httpContext,
        private readonly StoreManagerInterface $storeManager,
        private readonly SessionManagerInterface $session,
        private readonly RequestInterface $request
    ) {
    }

    /**
     * Store view and currency context, as Magento\Store\App\Action\Plugin\Context sets it.
     *
     * @param ActionInterface $subject
     * @return void
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function beforeExecute(ActionInterface $subject): void
    {
        if ($this->httpContext->getValue(StoreManagerInterface::CONTEXT_STORE) !== null) {
            return;
        }

        $store = $this->storeManager->getStore();
        if ($store->isUseStoreInUrl()) {
            $defaultStore = $store;
        } elseif ($this->request->getServerValue(StoreManager::PARAM_RUN_TYPE) === ScopeInterface::SCOPE_STORE) {
            $defaultStore = $this->storeManager->getStore(
                (string) $this->request->getServerValue(StoreManager::PARAM_RUN_CODE)
            );
        } else {
            $defaultStore = $this->storeManager->getDefaultStoreView() ?? $store;
        }

        $this->httpContext->setValue(
            StoreManagerInterface::CONTEXT_STORE,
            $store->getCode(),
            $defaultStore->getCode()
        );
        $this->httpContext->setValue(
            HttpContext::CONTEXT_CURRENCY,
            $this->session->getCurrencyCode() ?: $store->getDefaultCurrencyCode(),
            $defaultStore->getDefaultCurrencyCode()
        );
    }
}
