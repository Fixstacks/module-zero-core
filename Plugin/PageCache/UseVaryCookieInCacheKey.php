<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Core\Plugin\PageCache;

use Magento\Framework\App\PageCache\Identifier;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\PageCache\Model\App\Request\Http\IdentifierForSave;
use Magento\PageCache\Model\App\Request\Http\IdentifierStoreReader;

/**
 * Magento 2.4.8's built-in page cache keys on the HTTP context only, still
 * empty at lookup, so the first store view, currency or group to load a page
 * is served to all (the Arabic home page came back in English). 2.4.9 keys on
 * X-Magento-Vary first; this does the same on 2.4.8. Varnish is unaffected.
 */
class UseVaryCookieInCacheKey
{
    public function __construct(
        private readonly HttpRequest $request,
        private readonly Identifier $identifier,
        private readonly IdentifierStoreReader $storeReader,
        private readonly Json $serializer,
        private readonly ProductMetadataInterface $productMetadata
    ) {
    }

    /**
     * @param IdentifierForSave $subject
     * @param callable $proceed
     * @return string
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function aroundGetValue(IdentifierForSave $subject, callable $proceed)
    {
        $vary = $this->request->get(HttpResponse::COOKIE_VARY_STRING);
        if (!$vary || !str_starts_with((string) $this->productMetadata->getVersion(), '2.4.8')) {
            return $proceed();
        }

        $patterns = $this->identifier->getMarketingParameterPatterns();
        $data = [
            $this->request->isSecure(),
            preg_replace($patterns, array_fill(0, count($patterns), ''), (string) $this->request->getUriString()),
            $vary,
        ];

        return sha1($this->serializer->serialize($this->storeReader->getPageTagsWithStoreCacheTags($data)));
    }
}
