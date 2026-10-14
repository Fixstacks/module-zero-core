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
 * Core puts the store view and currency into the HTTP context (what the
 * X-Magento-Vary cookie and the full-page cache key are made of) in a plugin
 * on AbstractAction::dispatch(), so controllers that implement
 * ActionInterface directly never get them: every Zero HTMX fragment and the
 * Zero Checkout. Their vary string came out empty, and core then deletes the
 * visitor's X-Magento-Vary cookie (Response\Http::sendVary()). A visitor on
 * another store view or currency was then served, and could fill, the
 * default store view's cached pages in Varnish.
 *
 * This sets the same values the same way, only when core's plugin hasn't.
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
