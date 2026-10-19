<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Core\Test\Unit\Plugin\PageCache;

use Magento\Framework\App\PageCache\Identifier;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\PageCache\Model\App\Request\Http\IdentifierForSave;
use Magento\PageCache\Model\App\Request\Http\IdentifierStoreReader;
use PHPUnit\Framework\TestCase;
use Zero\Core\Plugin\PageCache\UseVaryCookieInCacheKey;

class UseVaryCookieInCacheKeyTest extends TestCase
{
    private function plugin(?string $vary, string $version): UseVaryCookieInCacheKey
    {
        $request = $this->createStub(HttpRequest::class);
        $request->method('get')->willReturn($vary);
        $request->method('isSecure')->willReturn(true);
        $request->method('getUriString')->willReturn('https://shop.example/?utm_source=x');
        $identifier = $this->createStub(Identifier::class);
        $identifier->method('getMarketingParameterPatterns')->willReturn(['/&?utm_source=[^&]*/']);
        $reader = $this->createStub(IdentifierStoreReader::class);
        $reader->method('getPageTagsWithStoreCacheTags')->willReturnArgument(0);
        $objectManager = $this->createStub(ObjectManagerInterface::class);
        $objectManager->method('get')->willReturn($reader);
        $metadata = $this->createStub(ProductMetadataInterface::class);
        $metadata->method('getVersion')->willReturn($version);

        return new UseVaryCookieInCacheKey($request, $identifier, $objectManager, new Json(), $metadata);
    }

    public function testOn248TheVaryCookieKeysThePage(): void
    {
        $subject = $this->createStub(IdentifierForSave::class);
        $key = $this->plugin('arabic-vary', '2.4.8-p5')->aroundGetValue($subject, fn () => 'context-only');

        $this->assertSame(sha1(json_encode([true, 'https://shop.example/?', 'arabic-vary'])), $key);
    }

    public function testWithoutTheCookieOrOnOtherVersionsNothingChanges(): void
    {
        $subject = $this->createStub(IdentifierForSave::class);

        $this->assertSame('context-only', $this->plugin(null, '2.4.8')->aroundGetValue($subject, fn () => 'context-only'));
        $this->assertSame('context-only', $this->plugin('vary', '2.4.9')->aroundGetValue($subject, fn () => 'context-only'));
        $this->assertSame('context-only', $this->plugin('vary', '2.4.7-p10')->aroundGetValue($subject, fn () => 'context-only'));
    }
}
