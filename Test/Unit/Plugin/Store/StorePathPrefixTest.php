<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Core\Test\Unit\Plugin\Store;

use Magento\Framework\App\Request\Http;
use Magento\Framework\App\Request\PathInfo;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Api\StoreRepositoryInterface;
use Magento\Store\Api\StoreResolverInterface;
use Magento\Store\App\Request\PathInfoProcessor;
use Magento\Store\App\Request\StorePathInfoValidator;
use Magento\Store\Model\Store;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Zero\Core\Plugin\Store\StorePathPrefix;

/**
 * The store views `ua` at /ua/, `ua_en` at /ua/en/ and `kids` at /kids/: their
 * links carry their path, a path starting with it selects them, and nothing
 * else changes.
 */
#[AllowMockObjectsWithoutExpectations]
class StorePathPrefixTest extends TestCase
{
    private const PATHS = ['ua' => 'ua', 'kids' => '/kids/', 'ua_en' => 'ua/en'];

    private Http&MockObject $request;
    private PathInfo&MockObject $pathInfo;
    private StoreRepositoryInterface&MockObject $storeRepository;

    protected function setUp(): void
    {
        $this->request = $this->createMock(Http::class);
        $this->pathInfo = $this->createMock(PathInfo::class);
        $this->storeRepository = $this->createMock(StoreRepositoryInterface::class);
        $this->storeRepository->method('getActiveStoreByCode')->willReturnCallback(function (string $code) {
            $store = $this->createMock(Store::class);
            $store->method('getId')->willReturn(['ua' => 5, 'ua_en' => 6, 'kids' => 7][$code]);

            return $store;
        });
    }

    public function testOnlyTheListedStoreViewsGetTheirCodeInLinks(): void
    {
        $plugin = $this->plugin();

        $this->assertTrue($plugin->afterIsUseStoreInUrl($this->store('ua'), false));
        $this->assertTrue($plugin->afterIsUseStoreInUrl($this->store('ua_en'), false));
        $this->assertTrue($plugin->afterIsUseStoreInUrl($this->store('kids'), false));
        $this->assertFalse($plugin->afterIsUseStoreInUrl($this->store('default'), false));
        // Magento's own setting still applies to the others.
        $this->assertTrue($plugin->afterIsUseStoreInUrl($this->store('default'), true));
    }

    public function testNoCodeWhereALinkAsksForNone(): void
    {
        $store = $this->store('ua');
        $store->setData('disable_store_in_url', true);

        $this->assertFalse($this->plugin()->afterIsUseStoreInUrl($store, false));
    }

    public function testAPathStartingWithTheCodeSelectsIt(): void
    {
        $validator = $this->createMock(StorePathInfoValidator::class);
        $plugin = $this->plugin();
        $code = fn (string $path, ?string $found = null) => $plugin->afterGetValidStoreCode(
            $validator,
            $found,
            $this->request,
            $path
        );

        $this->assertSame('ua', $code('/ua/odiah.html'));
        $this->assertSame('ua', $code('ua'));
        $this->assertSame('ua_en', $code('/ua/en/odiah.html'));
        $this->assertSame('ua_en', $code('/ua_en/odiah.html'));
        $this->assertSame('ua', $code('/ua/enter.html'));
        $this->assertSame('kids', $code('/kids/za-vikom.html'));
        $this->assertNull($code('/uah/odiah.html'));
        $this->assertNull($code('/kidswear.html'));
        $this->assertNull($code('/women/ua.html'));
        // A store Magento already found stays.
        $this->assertSame('de', $code('/ua/', 'de'));
    }

    public function testAnInactiveStoreViewIsNotSelected(): void
    {
        $repository = $this->createMock(StoreRepositoryInterface::class);
        $repository->method('getActiveStoreByCode')->willThrowException(new NoSuchEntityException());
        $plugin = new StorePathPrefix($this->request, $this->pathInfo, $repository, self::PATHS);
        $validator = $this->createMock(StorePathInfoValidator::class);

        $this->assertNull($plugin->afterGetValidStoreCode($validator, null, $this->request, '/ua/'));
    }

    public function testTheResolverPicksThemForTheirPathsOnly(): void
    {
        $resolver = $this->createMock(StoreResolverInterface::class);
        $this->request->method('getRequestUri')
            ->willReturnOnConsecutiveCalls('/ua/kontakty', '/ua/en/', '/kids/', '/men.html');
        $this->request->method('getBaseUrl')->willReturn('');
        $this->pathInfo->method('getPathInfo')->willReturnArgument(0);
        $plugin = $this->plugin();

        $this->assertSame(5, $plugin->afterGetCurrentStoreId($resolver, '1'));
        $this->assertSame(6, $plugin->afterGetCurrentStoreId($resolver, '1'));
        $this->assertSame(7, $plugin->afterGetCurrentStoreId($resolver, '1'));
        $this->assertSame('1', $plugin->afterGetCurrentStoreId($resolver, '1'));
    }

    public function testLinksUseThePathNotTheCode(): void
    {
        $plugin = $this->plugin();
        $english = $this->store('ua_en');

        $this->assertSame('https://shop.test/ua/en/', $plugin->afterGetBaseUrl($english, 'https://shop.test/ua_en/'));
        $this->assertSame('https://shop.test/media/', $plugin->afterGetBaseUrl($english, 'https://shop.test/media/'));
        $this->assertSame('https://shop.test/ua/', $plugin->afterGetBaseUrl($this->store('ua'), 'https://shop.test/ua/'));
    }

    public function testAPathReachesMagentoAsTheCode(): void
    {
        $processor = $this->createMock(PathInfoProcessor::class);
        $plugin = $this->plugin();
        $path = fn (string $path) => $plugin->beforeProcess($processor, $this->request, $path)[1];

        $this->assertSame('/ua_en/odiah.html', $path('/ua/en/odiah.html'));
        $this->assertSame('/ua_en', $path('/ua/en'));
        $this->assertSame('/ua/odiah.html', $path('/ua/odiah.html'));
        $this->assertSame('/kids/za-vikom.html', $path('/kids/za-vikom.html'));
        $this->assertSame('/women.html', $path('/women.html'));
    }

    public function testWithoutPathsNothingChanges(): void
    {
        $plugin = new StorePathPrefix($this->request, $this->pathInfo, $this->storeRepository);
        $validator = $this->createMock(StorePathInfoValidator::class);

        $this->assertFalse($plugin->afterIsUseStoreInUrl($this->store('ua'), false));
        $this->assertNull($plugin->afterGetValidStoreCode($validator, null, $this->request, '/ua/'));
    }

    private function store(string $code): Store&MockObject
    {
        $store = $this->createPartialMock(Store::class, ['getCode']);
        $store->method('getCode')->willReturn($code);

        return $store;
    }

    private function plugin(): StorePathPrefix
    {
        return new StorePathPrefix($this->request, $this->pathInfo, $this->storeRepository, self::PATHS);
    }
}
