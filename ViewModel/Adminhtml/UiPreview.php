<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Core\ViewModel\Adminhtml;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\StoreManagerInterface;
use Zero\Core\Model\Ui\PreviewToken;

/**
 * Store views to preview the component library in, and the signed link for
 * the chosen one (Controller\Adminhtml\Ui\Index).
 */
class UiPreview implements ArgumentInterface
{
    public function __construct(
        private readonly StoreManagerInterface $storeManager,
        private readonly RequestInterface $request,
        private readonly PreviewToken $previewToken
    ) {
    }

    /**
     * Active store views, by id: name (website / store view).
     *
     * @return array<int, string>
     */
    public function getStores(): array
    {
        $stores = [];
        foreach ($this->storeManager->getStores() as $store) {
            if ($store->isActive()) {
                $stores[(int) $store->getId()] = $store->getWebsite()->getName() . ' / ' . $store->getName();
            }
        }

        return $stores;
    }

    public function getSelectedStoreId(): int
    {
        $stores = $this->getStores();
        $requested = (int) $this->request->getParam('store', 0);
        if (isset($stores[$requested])) {
            return $requested;
        }

        return (int) ($this->storeManager->getDefaultStoreView()?->getId() ?? array_key_first($stores));
    }

    /** The storefront library for the chosen store view, with a link valid for an hour. */
    public function getPreviewUrl(): string
    {
        $store = $this->storeManager->getStore($this->getSelectedStoreId());
        $query = $this->previewToken->create((int) $store->getId());
        $query['___store'] = $store->getCode();

        return $store->getBaseUrl() . 'zero/ui/?' . http_build_query($query);
    }
}
