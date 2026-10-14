<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Core\Test\Unit\Model\Ui;

use Magento\Framework\App\DeploymentConfig;
use PHPUnit\Framework\TestCase;
use Zero\Core\Model\Ui\PreviewToken;

class PreviewTokenTest extends TestCase
{
    private const NOW = 1_800_000_000;

    public function testATokenOpensItsStoreViewUntilItExpires(): void
    {
        $tokens = $this->tokens('key-a');
        $link = $tokens->create(1, self::NOW);

        $this->assertTrue($tokens->isValid($link['zero_ui_token'], $link['zero_ui_expires'], 1, self::NOW + 10));
        $this->assertFalse($tokens->isValid($link['zero_ui_token'], $link['zero_ui_expires'], 3, self::NOW + 10), 'other store view');
        $this->assertFalse(
            $tokens->isValid($link['zero_ui_token'], $link['zero_ui_expires'], 1, self::NOW + PreviewToken::LIFETIME + 1),
            'expired'
        );
    }

    public function testTamperingOrAnotherInstallationsKeyFails(): void
    {
        $link = $this->tokens('key-a')->create(1, self::NOW);

        $this->assertFalse($this->tokens('key-a')->isValid($link['zero_ui_token'], $link['zero_ui_expires'] + 600, 1, self::NOW));
        $this->assertFalse($this->tokens('key-b')->isValid($link['zero_ui_token'], $link['zero_ui_expires'], 1, self::NOW));
        $this->assertFalse($this->tokens('key-a')->isValid('', $link['zero_ui_expires'], 1, self::NOW));
    }

    public function testAnExpiryFurtherAheadThanALifetimeIsRefused(): void
    {
        $tokens = $this->tokens('key-a');
        $farAhead = $tokens->create(1, self::NOW + 86400);

        $this->assertFalse($tokens->isValid($farAhead['zero_ui_token'], $farAhead['zero_ui_expires'], 1, self::NOW));
    }

    private function tokens(string $key): PreviewToken
    {
        $config = $this->createStub(DeploymentConfig::class);
        $config->method('get')->willReturn($key);

        return new PreviewToken($config);
    }
}
