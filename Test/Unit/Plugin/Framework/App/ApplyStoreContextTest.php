<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Core\Test\Unit\Plugin\Framework\App;

use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\Http\Context as HttpContext;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Session\Generic;
use Magento\Framework\Session\SessionManagerInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Zero\Core\Plugin\Framework\App\ApplyStoreContext;

/** The session is a partial mock only to answer its magic getCurrencyCode(). */
#[AllowMockObjectsWithoutExpectations]
class ApplyStoreContextTest extends TestCase
{
    public function testSetsStoreAndCurrencyAgainstTheDefaultStoreView(): void
    {
        $context = new HttpContext([], [], new Json());
        $this->plugin($context, $this->store('arabic_rtl', 'USD'), $this->store('default', 'USD'), null)
            ->beforeExecute($this->createStub(ActionInterface::class));

        $this->assertSame('arabic_rtl', $context->getValue(StoreManagerInterface::CONTEXT_STORE));
        $this->assertSame('USD', $context->getValue(HttpContext::CONTEXT_CURRENCY));
        // What the vary string (the X-Magento-Vary cookie) is made of
        $this->assertSame([StoreManagerInterface::CONTEXT_STORE => 'arabic_rtl'], $context->getData());
    }

    public function testDefaultStoreViewLeavesNothingToVary(): void
    {
        $context = new HttpContext([], [], new Json());
        $default = $this->store('default', 'USD');
        $this->plugin($context, $default, $default, null)->beforeExecute($this->createStub(ActionInterface::class));

        $this->assertSame([], $context->getData());
    }

    public function testSessionCurrencyWins(): void
    {
        $context = new HttpContext([], [], new Json());
        $default = $this->store('default', 'USD');
        $this->plugin($context, $default, $default, 'EUR')->beforeExecute($this->createStub(ActionInterface::class));

        $this->assertSame('EUR', $context->getValue(HttpContext::CONTEXT_CURRENCY));
    }

    public function testLeavesWhatCoreAlreadySet(): void
    {
        $context = new HttpContext([], [], new Json());
        $context->setValue(StoreManagerInterface::CONTEXT_STORE, 'europe_eur', 'default');
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->expects($this->never())->method('getStore');

        (new ApplyStoreContext(
            $context,
            $storeManager,
            $this->createStub(SessionManagerInterface::class),
            $this->createStub(Http::class)
        ))->beforeExecute($this->createStub(ActionInterface::class));

        $this->assertSame('europe_eur', $context->getValue(StoreManagerInterface::CONTEXT_STORE));
    }

    private function plugin(HttpContext $context, Store $current, Store $default, ?string $sessionCurrency): ApplyStoreContext
    {
        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($current);
        $storeManager->method('getDefaultStoreView')->willReturn($default);
        // getCurrencyCode() is a magic getter of the session storage
        $session = $this->getMockBuilder(Generic::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['__call'])
            ->getMock();
        $session->method('__call')->willReturnCallback(
            static fn (string $method) => $method === 'getCurrencyCode' ? $sessionCurrency : null
        );

        return new ApplyStoreContext($context, $storeManager, $session, $this->createStub(Http::class));
    }

    private function store(string $code, string $currency): Store
    {
        $store = $this->createStub(Store::class);
        $store->method('getCode')->willReturn($code);
        $store->method('getDefaultCurrencyCode')->willReturn($currency);
        $store->method('isUseStoreInUrl')->willReturn(false);

        return $store;
    }
}
