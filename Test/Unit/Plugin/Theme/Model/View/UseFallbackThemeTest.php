<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Core\Test\Unit\Plugin\Theme\Model\View;

use Magento\Theme\Model\View\Design;
use PHPUnit\Framework\TestCase;
use Zero\Core\Model\Theme\FallbackRoutes;
use Zero\Core\Plugin\Theme\Model\View\UseFallbackTheme;

class UseFallbackThemeTest extends TestCase
{
    public function testListedPageGetsTheFallbackTheme(): void
    {
        $this->assertSame('Magento/luma', $this->plugin('Magento/luma')->afterGetConfigurationDesignTheme(
            $this->design('frontend'),
            '6'
        ));
    }

    public function testOtherPagesKeepTheConfiguredTheme(): void
    {
        $this->assertSame('6', $this->plugin(null)->afterGetConfigurationDesignTheme($this->design('frontend'), '6'));
    }

    public function testLookupsForAnotherStoreAreUntouched(): void
    {
        $this->assertSame('6', $this->plugin('Magento/luma')->afterGetConfigurationDesignTheme(
            $this->design('frontend'),
            '6',
            null,
            ['store' => 2]
        ));
    }

    public function testOnlyTheStorefront(): void
    {
        $this->assertSame('2', $this->plugin('Magento/luma')->afterGetConfigurationDesignTheme(
            $this->design('frontend'),
            '2',
            'adminhtml'
        ));
    }

    private function plugin(?string $theme): UseFallbackTheme
    {
        $routes = $this->createStub(FallbackRoutes::class);
        $routes->method('getThemeForCurrentRequest')->willReturn($theme);

        return new UseFallbackTheme($routes);
    }

    private function design(string $area): Design
    {
        $design = $this->createStub(Design::class);
        $design->method('getArea')->willReturn($area);

        return $design;
    }
}
