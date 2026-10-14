<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Core\Test\Unit\Plugin\View\Element\Template\File;

use Magento\Framework\Component\ComponentRegistrarInterface;
use Magento\Framework\View\Design\ThemeInterface;
use Magento\Framework\View\DesignInterface;
use Magento\Framework\View\Element\Template\File\Resolver;
use PHPUnit\Framework\TestCase;
use Zero\Core\Model\Compat\Registry;
use Zero\Core\Plugin\View\Element\Template\File\UseCompatTemplates;

class UseCompatTemplatesTest extends TestCase
{
    private const MODULE_DIR = '/app/code/Vendor/Module';
    private const COMPAT_DIR = '/app/code/ZeroCompat/VendorModule';

    /** @var array<string, string|false> Template => resolved file, as core would answer */
    private array $files = [];

    protected function setUp(): void
    {
        $this->files = [
            'Vendor_Module::form.phtml' => self::MODULE_DIR . '/view/frontend/templates/form.phtml',
            'ZeroCompat_VendorModule::form.phtml' => self::COMPAT_DIR . '/view/frontend/templates/form.phtml',
            'Vendor_Module::list.phtml' => self::MODULE_DIR . '/view/frontend/templates/list.phtml',
            'ZeroCompat_VendorModule::list.phtml' => false,
            'Vendor_Module::themed.phtml' => '/app/design/frontend/Zero/default/Vendor_Module/templates/themed.phtml',
            'ZeroCompat_VendorModule::themed.phtml' => self::COMPAT_DIR . '/view/frontend/templates/themed.phtml',
        ];
    }

    public function testCompatTemplateReplacesTheOriginal(): void
    {
        $this->assertSame(
            self::COMPAT_DIR . '/view/frontend/templates/form.phtml',
            $this->resolve('Vendor_Module::form.phtml')
        );
    }

    public function testTemplateWithoutModulePrefixUsesTheBlockModule(): void
    {
        $this->assertSame(
            self::COMPAT_DIR . '/view/frontend/templates/form.phtml',
            $this->resolve('form.phtml', ['module' => 'Vendor_Module'])
        );
    }

    public function testTemplatesTheCompatModuleDoesNotHaveStayOriginal(): void
    {
        $this->assertSame(
            self::MODULE_DIR . '/view/frontend/templates/list.phtml',
            $this->resolve('Vendor_Module::list.phtml')
        );
    }

    public function testThemeOverrideBeatsCompat(): void
    {
        $this->assertSame(
            '/app/design/frontend/Zero/default/Vendor_Module/templates/themed.phtml',
            $this->resolve('Vendor_Module::themed.phtml')
        );
    }

    public function testOtherThemesKeepTheOriginal(): void
    {
        $this->assertSame(
            self::MODULE_DIR . '/view/frontend/templates/form.phtml',
            $this->resolve('Vendor_Module::form.phtml', [], 'Magento/luma', ['Magento/blank'])
        );
    }

    public function testChildThemesOfZeroUseCompat(): void
    {
        $this->assertSame(
            self::COMPAT_DIR . '/view/frontend/templates/form.phtml',
            $this->resolve('Vendor_Module::form.phtml', [], 'Acme/shop', ['Zero/default', 'Magento/blank'])
        );
    }

    public function testModulesWithoutCompatAreUntouched(): void
    {
        $this->files['Other_Module::view.phtml'] = '/app/code/Other/Module/view/frontend/templates/view.phtml';

        $this->assertSame(
            '/app/code/Other/Module/view/frontend/templates/view.phtml',
            $this->resolve('Other_Module::view.phtml')
        );
    }

    /**
     * @param string $template
     * @param array $params
     * @param string $themePath
     * @param string[] $parents
     * @return string|false
     */
    private function resolve(
        string $template,
        array $params = [],
        string $themePath = 'Zero/default',
        array $parents = ['Magento/blank']
    ) {
        $theme = $this->theme($themePath);
        $theme->method('getInheritedThemes')->willReturn(array_map(fn ($path) => $this->theme($path), $parents));
        $design = $this->createStub(DesignInterface::class);
        $design->method('getDesignTheme')->willReturn($theme);
        $registrar = $this->createStub(ComponentRegistrarInterface::class);
        $registrar->method('getPath')->willReturnMap([
            ['module', 'Vendor_Module', self::MODULE_DIR],
            ['module', 'Other_Module', '/app/code/Other/Module'],
        ]);
        $plugin = new UseCompatTemplates(
            new Registry(['Vendor_Module' => ['ZeroCompat_VendorModule' => 'ZeroCompat_VendorModule']]),
            $design,
            $registrar
        );

        return $plugin->aroundGetTemplateFileName(
            $this->createStub(Resolver::class),
            function (string $template, array $params) {
                if (!str_contains($template, '::')) {
                    $template = $params['module'] . '::' . $template;
                }

                return $this->files[$template] ?? false;
            },
            $template,
            $params
        );
    }

    /**
     * @param string $path
     * @return ThemeInterface&\PHPUnit\Framework\MockObject\Stub
     */
    private function theme(string $path): ThemeInterface
    {
        $theme = $this->createStub(ThemeInterface::class);
        $theme->method('getThemePath')->willReturn($path);
        $theme->method('getArea')->willReturn('frontend');

        return $theme;
    }
}
