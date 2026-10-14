<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Core\Plugin\Theme\Model\View;

use Magento\Theme\Model\View\Design;
use Zero\Core\Model\Theme\FallbackRoutes;

/**
 * Renders the pages listed in FallbackRoutes with the fallback theme. This
 * is where core picks the store's configured theme while loading the design,
 * before layout and theme translations load, so the whole page (and its
 * translations) comes from the fallback theme. Only for the request itself:
 * a lookup for another store (emails) keeps that store's theme.
 */
class UseFallbackTheme
{
    public function __construct(
        private readonly FallbackRoutes $fallbackRoutes
    ) {
    }

    /**
     * The fallback theme instead of the configured one on a listed page.
     *
     * @param Design $subject
     * @param mixed $result
     * @param string|null $area
     * @param array $params
     * @return mixed
     */
    public function afterGetConfigurationDesignTheme(Design $subject, $result, $area = null, array $params = [])
    {
        if (($area ?: $subject->getArea()) !== 'frontend' || isset($params['store'])) {
            return $result;
        }

        return $this->fallbackRoutes->getThemeForCurrentRequest() ?? $result;
    }
}
