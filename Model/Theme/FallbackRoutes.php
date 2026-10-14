<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Core\Model\Theme;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\RequestInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Pages that render with another theme (Stores > Configuration > Zero >
 * Theme > Pages on Another Theme): for extensions not yet adapted to Zero,
 * whose frontend needs Luma's RequireJS/Knockout stack.
 *
 * One pattern per line, "*" as wildcard, matched against the request's
 * "front name/controller/action" (rma/returns/create, amasty_rma/*) and its
 * URL path (blog/*). Empty lines and lines starting with # are ignored.
 */
class FallbackRoutes
{
    public const XML_PATH_ROUTES = 'zero_theme/fallback/routes';
    public const XML_PATH_THEME = 'zero_theme/fallback/theme';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly RequestInterface $request
    ) {
    }

    /**
     * The fallback theme path for the current request, or null to keep the store's theme.
     *
     * @return string|null
     */
    public function getThemeForCurrentRequest(): ?string
    {
        $patterns = $this->getPatterns();
        if (!$patterns || !$this->request instanceof HttpRequest) {
            return null;
        }
        $theme = trim((string) $this->scopeConfig->getValue(self::XML_PATH_THEME, ScopeInterface::SCOPE_STORE));
        if ($theme === '') {
            return null;
        }

        $route = strtolower(implode('/', [
            (string) $this->request->getModuleName(),
            (string) $this->request->getControllerName(),
            (string) $this->request->getActionName(),
        ]));
        $path = strtolower(trim((string) $this->request->getOriginalPathInfo(), '/'));
        foreach ($patterns as $pattern) {
            if (($route !== '//' && fnmatch($pattern, $route)) || ($path !== '' && fnmatch($pattern, $path))) {
                return $theme;
            }
        }

        return null;
    }

    /**
     * Configured patterns, lowercased and trimmed of slashes.
     *
     * @return string[]
     */
    public function getPatterns(): array
    {
        $lines = preg_split('/\R/', (string) $this->scopeConfig->getValue(
            self::XML_PATH_ROUTES,
            ScopeInterface::SCOPE_STORE
        )) ?: [];
        $patterns = [];
        foreach ($lines as $line) {
            $line = strtolower(trim(trim($line), '/'));
            if ($line !== '' && !str_starts_with($line, '#')) {
                $patterns[] = $line;
            }
        }

        return $patterns;
    }
}
