<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Core\Controller\Ui;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\ForwardFactory;
use Magento\Framework\View\Result\PageFactory;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Zero\Core\Model\Ui\PreviewToken;

/**
 * /zero/ui/: the Zero component library, each component live with the
 * code to copy (ViewModel\UiComponents). Opens with a signed link from the
 * admin (Content > Design > UI Components, Model\Ui\PreviewToken), or for
 * everyone when Zero > Theme > Component Library is on. Otherwise 404: a
 * store doesn't want a public page of samples. Never indexed or cached.
 */
class Index implements HttpGetActionInterface
{
    public const XML_PATH_ENABLED = 'zero_theme/ui/enabled';

    public function __construct(
        private readonly PageFactory $pageFactory,
        private readonly ForwardFactory $forwardFactory,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly RequestInterface $request,
        private readonly StoreManagerInterface $storeManager,
        private readonly PreviewToken $previewToken
    ) {
    }

    /**
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        $public = $this->scopeConfig->isSetFlag(self::XML_PATH_ENABLED, ScopeInterface::SCOPE_STORE);
        $signed = $this->previewToken->isValid(
            (string) $this->request->getParam('zero_ui_token', ''),
            (int) $this->request->getParam('zero_ui_expires', 0),
            (int) $this->storeManager->getStore()->getId()
        );
        if (!$public && !$signed) {
            return $this->forwardFactory->create()->forward('noroute');
        }
        $page = $this->pageFactory->create();
        $page->getConfig()->getTitle()->set(__('Zero UI Components'));
        $page->getConfig()->setRobots('NOINDEX,NOFOLLOW');

        return $page;
    }
}
