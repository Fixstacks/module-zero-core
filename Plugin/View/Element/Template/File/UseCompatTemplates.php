<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Core\Plugin\View\Element\Template\File;

use Magento\Framework\Component\ComponentRegistrar;
use Magento\Framework\Component\ComponentRegistrarInterface;
use Magento\Framework\View\DesignInterface;
use Magento\Framework\View\Element\Template\File\Resolver;
use Zero\Core\Model\Compat\Registry;

/**
 * Template fallback for compat modules (see Compat\Registry): on a Zero
 * theme, "Vendor_Module::path.phtml" resolves in this order:
 *
 * 1. the theme's own Vendor_Module/templates/path.phtml (unchanged: themes win);
 * 2. the first compat module's templates/path.phtml (or a theme override of it);
 * 3. Vendor_Module's own templates/path.phtml (unchanged).
 *
 * Templates the compat module doesn't have keep coming from the original, so
 * a compat module carries only what it changes.
 */
class UseCompatTemplates
{
    /** @var array<string, bool> Whether a theme is a Zero theme, by theme path. */
    private array $zeroThemes = [];

    public function __construct(
        private readonly Registry $registry,
        private readonly DesignInterface $design,
        private readonly ComponentRegistrarInterface $componentRegistrar
    ) {
    }

    /**
     * The compat module's template when the theme doesn't override the original.
     *
     * @param Resolver $subject
     * @param callable $proceed
     * @param string|null $template
     * @param array $params
     * @return string|false
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function aroundGetTemplateFileName(
        Resolver $subject,
        callable $proceed,
        $template,
        $params = []
    ) {
        $original = $proceed($template, $params);
        if (!is_string($template) || $template === '' || !$this->isZeroTheme()) {
            return $original;
        }

        [$moduleName, $path] = $this->split($template, $params);
        if ($moduleName === null) {
            return $original;
        }
        $compatModules = $this->registry->getCompatModules($moduleName);
        if (!$compatModules || ($original && !$this->isInModule((string) $original, $moduleName))) {
            return $original;
        }

        foreach ($compatModules as $compatModule) {
            $compatParams = array_merge($params, ['module' => $compatModule]);
            $file = $proceed($compatModule . '::' . $path, $compatParams);
            if ($file) {
                return $file;
            }
        }

        return $original;
    }

    /**
     * Module name and module-relative path of a template.
     *
     * @param string $template
     * @param array $params
     * @return array{0: string|null, 1: string}
     */
    private function split(string $template, array $params): array
    {
        if (str_contains($template, '::')) {
            [$moduleName, $path] = explode('::', $template, 2);

            return [$moduleName, $path];
        }
        $moduleName = $params['module'] ?? null;

        return [is_string($moduleName) && $moduleName !== '' ? $moduleName : null, $template];
    }

    private function isInModule(string $file, string $moduleName): bool
    {
        $moduleDir = $this->componentRegistrar->getPath(ComponentRegistrar::MODULE, $moduleName);

        return $moduleDir !== null && str_starts_with($file, rtrim($moduleDir, '/') . '/');
    }

    private function isZeroTheme(): bool
    {
        // Per theme, not per request: emails from the admin emulate a store's design.
        $theme = $this->design->getDesignTheme();
        $key = $theme->getArea() . '/' . $theme->getThemePath();
        if (!isset($this->zeroThemes[$key])) {
            $paths = [(string) $theme->getThemePath()];
            foreach ($theme->getInheritedThemes() as $inherited) {
                $paths[] = (string) $inherited->getThemePath();
            }
            $this->zeroThemes[$key] = $theme->getArea() === 'frontend'
                && (bool) array_intersect($paths, $this->registry->getThemes());
        }

        return $this->zeroThemes[$key];
    }
}
