<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Core\Plugin\Store;

use Magento\Framework\App\Request\Http;
use Magento\Framework\App\Request\PathInfo;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Api\StoreRepositoryInterface;
use Magento\Store\Api\StoreResolverInterface;
use Magento\Store\App\Request\PathInfoProcessor;
use Magento\Store\App\Request\StorePathInfoValidator;
use Magento\Store\Model\Store;

/**
 * Store views at a path of the main domain (/ua/, /ua/en/, /kids/), even from
 * another website. Magento's "Add Store Code to Urls" is all or nothing, uses
 * the code itself (/ua_en/), and serves another website only via
 * MAGE_RUN_CODE. For the store views listed here only, this:
 * - gives their links the path (/ua/en/ for the code ua_en);
 * - selects the store view from the path (PathInfoProcessor strips it);
 * - lets the store resolver pick it outside the run website.
 *
 * A module lists its store views in di.xml, store code => path:
 *
 *     <type name="Zero\Core\Plugin\Store\StorePathPrefix">
 *         <arguments>
 *             <argument name="paths" xsi:type="array">
 *                 <item name="ua" xsi:type="string">ua</item>
 *                 <item name="ua_en" xsi:type="string">ua/en</item>
 *             </argument>
 *         </arguments>
 *     </type>
 */
class StorePathPrefix
{
    /** @var array<string, string> store code => path, the longer path first (/ua/en/ before /ua/) */
    private readonly array $paths;

    /**
     * @param array<string, string> $paths
     */
    public function __construct(
        private readonly RequestInterface $request,
        private readonly PathInfo $pathInfo,
        private readonly StoreRepositoryInterface $storeRepository,
        array $paths = []
    ) {
        $paths = array_map(static fn ($path) => trim((string) $path, '/'), array_filter($paths));
        uksort($paths, static fn ($a, $b) => strlen($paths[$b]) <=> strlen($paths[$a]));
        $this->paths = $paths;
    }

    /**
     * @see Store::isUseStoreInUrl()
     */
    public function afterIsUseStoreInUrl(Store $subject, bool $result): bool
    {
        return $result || (isset($this->paths[$subject->getCode()])
            && !($subject->hasDisableStoreInUrl() && $subject->getDisableStoreInUrl()));
    }

    /**
     * The store view's link base with its path in place of its code: /ua/en/, not /ua_en/.
     *
     * @see Store::getBaseUrl()
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterGetBaseUrl(Store $subject, string $result, ...$arguments): string
    {
        $code = (string) $subject->getCode();
        $path = $this->paths[$code] ?? $code;
        if ($path === $code || !str_ends_with($result, '/' . $code . '/')) {
            return $result;
        }

        return substr($result, 0, -strlen($code) - 1) . $path . '/';
    }

    /**
     * /ua/en/… reaches Magento as /ua_en/…: the store code it takes off the path.
     *
     * @see PathInfoProcessor::process()
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function beforeProcess(PathInfoProcessor $subject, RequestInterface $request, $pathInfo): array
    {
        $pathInfo = (string) $pathInfo;
        $code = $this->codeFor($pathInfo);
        if ($code !== null && $this->paths[$code] !== $code) {
            $pathInfo = '/' . $code . substr(ltrim($pathInfo, '/'), strlen($this->paths[$code]));
        }

        return [$request, $pathInfo];
    }

    /**
     * @see StorePathInfoValidator::getValidStoreCode()
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterGetValidStoreCode(
        StorePathInfoValidator $subject,
        ?string $result,
        Http $request,
        string $pathInfo = ''
    ): ?string {
        if ($result !== null) {
            return $result;
        }
        $code = $this->codeFor($pathInfo !== '' ? $pathInfo : $this->path($request));

        return $code !== null && $this->storeId($code) !== null ? $code : null;
    }

    /**
     * @see StoreResolverInterface::getCurrentStoreId()
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterGetCurrentStoreId(StoreResolverInterface $subject, $result)
    {
        if (!$this->request instanceof Http) {
            return $result;
        }
        $code = $this->codeFor($this->path($this->request));

        return ($code !== null ? $this->storeId($code) : null) ?? $result;
    }

    /**
     * The store code a path starts with: its path (/ua/en/…) or its code (/ua_en/…).
     */
    public function codeFor(string $pathInfo): ?string
    {
        $path = ltrim($pathInfo, '/');
        foreach ($this->paths as $code => $prefix) {
            foreach (array_unique([$prefix, $code]) as $start) {
                if ($path === $start || str_starts_with($path, $start . '/')) {
                    return $code;
                }
            }
        }

        return null;
    }

    private function path(Http $request): string
    {
        return $this->pathInfo->getPathInfo((string) $request->getRequestUri(), (string) $request->getBaseUrl());
    }

    /**
     * The store view's id when it is active.
     */
    private function storeId(string $code): ?int
    {
        try {
            return (int) $this->storeRepository->getActiveStoreByCode($code)->getId();
        } catch (LocalizedException) {
            return null;
        }
    }
}
