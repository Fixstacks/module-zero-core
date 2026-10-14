<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Core\Model\Social;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Stores > Configuration > Zero > Theme > Sharing in Social Media.
 */
class Config
{
    public const XML_PATH_ENABLED = 'zero_theme/social/enabled';
    public const UPLOAD_DIR = 'zero/social';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_ENABLED, ScopeInterface::SCOPE_STORE);
    }

    /** The image for pages without their own, or ''. */
    public function defaultImage(): string
    {
        $file = trim((string) $this->scopeConfig->getValue('zero_theme/social/default_image', ScopeInterface::SCOPE_STORE));

        return $file !== '' && !str_contains($file, '..')
            ? rtrim($this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA), '/') . '/' . self::UPLOAD_DIR . '/' . ltrim($file, '/')
            : '';
    }

    /** The store's X account as "@name", or ''. */
    public function twitterSite(): string
    {
        $handle = ltrim(trim((string) $this->scopeConfig->getValue('zero_theme/social/twitter_site', ScopeInterface::SCOPE_STORE)), '@');

        return preg_match('/^\w{1,15}$/', $handle) ? '@' . $handle : '';
    }

    public function siteName(): string
    {
        return (string) ($this->scopeConfig->getValue('general/store_information/name', ScopeInterface::SCOPE_STORE)
            ?: $this->storeManager->getStore()->getFrontendName());
    }

    public function locale(): string
    {
        return (string) $this->scopeConfig->getValue('general/locale/code', ScopeInterface::SCOPE_STORE);
    }
}
