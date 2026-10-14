<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Core\Test\Unit\Model\Theme;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Request\Http;
use PHPUnit\Framework\TestCase;
use Zero\Core\Model\Theme\FallbackRoutes;

class FallbackRoutesTest extends TestCase
{
    private const ROUTES = "# extensions not adapted yet\n rma/* \n\n/blog/\namasty_rma/guest/view\n";

    public function testMatchesFrontNameControllerAction(): void
    {
        $this->assertSame('Magento/luma', $this->themeFor('rma', 'returns', 'create', 'rma/returns/create'));
        $this->assertSame('Magento/luma', $this->themeFor('amasty_rma', 'guest', 'view', 'amasty_rma/guest/view'));
    }

    public function testMatchesUrlPath(): void
    {
        $this->assertSame('Magento/luma', $this->themeFor('cms', 'page', 'view', '/blog/'));
    }

    public function testOtherPagesKeepTheStoreTheme(): void
    {
        $this->assertNull($this->themeFor('amasty_rma', 'guest', 'create', 'amasty_rma/guest/create'));
        $this->assertNull($this->themeFor('catalog', 'product', 'view', 'joust-duffle-bag.html'));
    }

    public function testCommentsAndBlankLinesAreIgnored(): void
    {
        $this->assertSame(['rma/*', 'blog', 'amasty_rma/guest/view'], $this->routes(self::ROUTES)->getPatterns());
    }

    public function testNothingConfiguredMeansNoFallback(): void
    {
        $this->assertNull($this->themeFor('rma', 'returns', 'create', 'rma/returns/create', ''));
    }

    private function themeFor(
        string $module,
        string $controller,
        string $action,
        string $path,
        string $routes = self::ROUTES
    ): ?string {
        $request = $this->createStub(Http::class);
        $request->method('getModuleName')->willReturn($module);
        $request->method('getControllerName')->willReturn($controller);
        $request->method('getActionName')->willReturn($action);
        $request->method('getOriginalPathInfo')->willReturn($path);

        return $this->routes($routes, $request)->getThemeForCurrentRequest();
    }

    private function routes(string $routes, ?Http $request = null): FallbackRoutes
    {
        $config = $this->createStub(ScopeConfigInterface::class);
        $config->method('getValue')->willReturnMap([
            [FallbackRoutes::XML_PATH_ROUTES, 'store', null, $routes],
            [FallbackRoutes::XML_PATH_THEME, 'store', null, 'Magento/luma'],
        ]);

        return new FallbackRoutes($config, $request ?? $this->createStub(Http::class));
    }
}
