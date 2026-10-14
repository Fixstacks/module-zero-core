<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Core\Test\Unit\ViewModel;

use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Stdlib\Cookie\CookieMetadataFactory;
use Magento\Framework\Stdlib\Cookie\PublicCookieMetadata;
use Magento\Framework\Stdlib\CookieManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Zero\Core\ViewModel\CookieMessages;

/**
 * Flash messages are read from the mage-messages cookie once, then the cookie
 * is deleted so they don't show again.
 */
class CookieMessagesTest extends TestCase
{
    private CookieManagerInterface&MockObject $cookies;

    protected function setUp(): void
    {
        $this->cookies = $this->createMock(CookieManagerInterface::class);
    }

    public function testReturnsMessagesAndDeletesCookie(): void
    {
        $this->cookies->method('getCookie')->with('mage-messages')
            ->willReturn('[{"type":"success","text":"Saved."}]');
        $this->cookies->expects($this->once())->method('deleteCookie')->with('mage-messages');

        $this->assertSame([['type' => 'success', 'text' => 'Saved.']], $this->viewModel()->getMessages());
    }

    public function testNoCookieMeansNoMessages(): void
    {
        $this->cookies->method('getCookie')->willReturn(null);
        $this->cookies->expects($this->never())->method('deleteCookie');

        $this->assertSame([], $this->viewModel()->getMessages());
    }

    public function testMalformedCookieIsIgnored(): void
    {
        $this->cookies->method('getCookie')->willReturn('{not json');
        $this->cookies->expects($this->never())->method('deleteCookie');

        $this->assertSame([], $this->viewModel()->getMessages());
    }

    private function viewModel(): CookieMessages
    {
        $metadata = $this->createStub(PublicCookieMetadata::class);
        $metadata->method('setPath')->willReturnSelf();
        $factory = $this->createStub(CookieMetadataFactory::class);
        $factory->method('createPublicCookieMetadata')->willReturn($metadata);

        return new CookieMessages($this->cookies, $factory, new Json());
    }
}
