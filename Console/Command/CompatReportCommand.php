<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Core\Console\Command;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Component\ComponentRegistrar;
use Magento\Framework\Component\ComponentRegistrarInterface;
use Magento\Framework\Module\ModuleListInterface;
use Magento\Theme\Model\ResourceModel\Theme\CollectionFactory as ThemeCollectionFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Zero\Core\Model\Compat\Registry;
use Zero\Core\Model\Theme\FallbackRoutes;

/**
 * Which enabled storefront modules are ready for the Zero theme: "Zero
 * styles" (own tailwind.css), "compat: X", "theme" (a Zero theme overrides
 * its templates), "Luma pages", or not covered, with a count of its Luma-only
 * JS (x-magento-init, data-mage-init, UI components). Magento's own modules
 * only with --all.
 */
class CompatReportCommand extends Command
{
    private const LUMA_JS = [
        'text/x-magento-init',
        'data-mage-init',
        'x-magento-init',
        'data-bind=',
        'Magento_Ui/js/core/app',
        'uiComponent',
        'require([',
        'requirejs(',
    ];

    public function __construct(
        private readonly ModuleListInterface $moduleList,
        private readonly ComponentRegistrarInterface $componentRegistrar,
        private readonly Registry $registry,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly ThemeCollectionFactory $themeCollectionFactory
    ) {
        parent::__construct();
    }

    /**
     * @inheritDoc
     */
    protected function configure(): void
    {
        $this->setName('zero:compat:report')
            ->setDescription('Lists enabled modules with a storefront and whether they work on the Zero theme')
            ->addOption('all', null, InputOption::VALUE_NONE, "Include Magento's own modules");
    }

    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $themeDirs = $this->zeroThemeDirs();
        $fallbackPatterns = $this->fallbackPatterns();
        $compat = $this->registry->getAll();
        $compatModules = array_merge([], ...array_values($compat));

        $rows = [];
        $uncovered = 0;
        foreach ($this->moduleList->getNames() as $moduleName) {
            if (str_starts_with($moduleName, 'Zero_')
                || in_array($moduleName, $compatModules, true)
                || (!$input->getOption('all') && str_starts_with($moduleName, 'Magento_'))
            ) {
                continue;
            }
            $dir = $this->componentRegistrar->getPath(ComponentRegistrar::MODULE, $moduleName);
            $files = $dir ? $this->storefrontFiles($dir) : [];
            if (!$files) {
                continue;
            }

            $coveredBy = [];
            if (is_file($dir . '/view/frontend/zero/tailwind.css')) {
                $coveredBy[] = 'Zero styles';
            }
            foreach ($compat[$moduleName] ?? [] as $compatModule) {
                $coveredBy[] = 'compat: ' . $compatModule;
            }
            foreach ($themeDirs as $themePath => $themeDir) {
                if (is_dir($themeDir . '/' . $moduleName)) {
                    $coveredBy[] = 'theme: ' . $themePath;
                }
            }
            $frontNames = $this->frontNames($dir);
            foreach ($fallbackPatterns as $pattern) {
                foreach ($frontNames as $frontName) {
                    if (fnmatch($pattern, $frontName . '/x/x') || str_starts_with($pattern, $frontName . '/')) {
                        $coveredBy[] = 'Luma pages: ' . $pattern;
                    }
                }
            }
            if (!$coveredBy) {
                $uncovered++;
            }

            $rows[] = [
                $moduleName,
                count($files),
                count(array_filter($files, [$this, 'usesLumaJs'])),
                $coveredBy ? implode(', ', array_unique($coveredBy)) : '<comment>not covered</comment>',
            ];
        }

        if (!$rows) {
            $output->writeln('No enabled module has storefront templates or layouts to check.');

            return Command::SUCCESS;
        }
        (new Table($output))
            ->setHeaders(['Module', 'Storefront files', 'Luma JS', 'Works on Zero via'])
            ->setRows($rows)
            ->render();
        $output->writeln(sprintf(
            '%d of %d modules not covered. Adapt one with a compat module (docs/extensions.md in the Zero theme)'
            . ' or list its pages under Stores > Configuration > Zero > Theme > Pages on Another Theme.',
            $uncovered,
            count($rows)
        ));

        return Command::SUCCESS;
    }

    /**
     * Storefront templates, layouts and web files of a module.
     *
     * @param string $moduleDir
     * @return string[]
     */
    private function storefrontFiles(string $moduleDir): array
    {
        $files = [];
        foreach (['templates', 'layout', 'page_layout', 'ui_component'] as $type) {
            $dir = $moduleDir . '/view/frontend/' . $type;
            if (!is_dir($dir)) {
                continue;
            }
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                if ($file->isFile() && in_array($file->getExtension(), ['phtml', 'xml', 'html'], true)) {
                    $files[] = $file->getPathname();
                }
            }
        }

        return $files;
    }

    private function usesLumaJs(string $file): bool
    {
        $content = (string) file_get_contents($file);
        foreach (self::LUMA_JS as $marker) {
            if (str_contains($content, $marker)) {
                return true;
            }
        }

        return str_ends_with($file, '.html') || str_contains($file, '/ui_component/');
    }

    /**
     * Front names the module routes on the storefront.
     *
     * @param string $moduleDir
     * @return string[]
     */
    private function frontNames(string $moduleDir): array
    {
        $routes = $moduleDir . '/etc/frontend/routes.xml';
        if (!is_file($routes)) {
            return [];
        }
        preg_match_all('/frontName="([^"]+)"/', (string) file_get_contents($routes), $matches);

        return array_map('strtolower', $matches[1]);
    }

    /**
     * Pages on Another Theme, default scope.
     *
     * @return string[]
     */
    private function fallbackPatterns(): array
    {
        $lines = preg_split('/\R/', (string) $this->scopeConfig->getValue(FallbackRoutes::XML_PATH_ROUTES)) ?: [];

        return array_values(array_filter(
            array_map(static fn ($line) => strtolower(trim(trim($line), '/')), $lines),
            static fn ($line) => $line !== '' && !str_starts_with($line, '#')
        ));
    }

    /**
     * Directories of the Zero themes and their child themes, by theme path.
     *
     * @return array<string, string>
     */
    private function zeroThemeDirs(): array
    {
        $dirs = [];
        $themes = $this->themeCollectionFactory->create()->addFieldToFilter('area', 'frontend');
        foreach ($themes as $theme) {
            $paths = [$theme->getThemePath()];
            foreach ($theme->getInheritedThemes() as $inherited) {
                $paths[] = $inherited->getThemePath();
            }
            if (!array_intersect($paths, $this->registry->getThemes())) {
                continue;
            }
            $dir = $this->componentRegistrar->getPath(
                ComponentRegistrar::THEME,
                'frontend/' . $theme->getThemePath()
            );
            if ($dir) {
                $dirs[(string) $theme->getThemePath()] = $dir;
            }
        }

        return $dirs;
    }
}
