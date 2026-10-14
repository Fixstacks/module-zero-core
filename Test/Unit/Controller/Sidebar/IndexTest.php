<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Core\Test\Unit\Controller\Sidebar;

use Magento\Catalog\Helper\Product\Compare as CompareHelper;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\LayoutFactory;
use Magento\Framework\View\LayoutInterface;
use Magento\Wishlist\Block\Customer\Sidebar as WishlistSidebar;
use Magento\Wishlist\ViewModel\WishlistData;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Zero\Core\Controller\PrivateFragmentActionInterface;
use Zero\Core\Controller\Sidebar\Index;

#[AllowMockObjectsWithoutExpectations]
class IndexTest extends TestCase
{
    private ?string $contents = null;

    public function testIsAPrivateFragment(): void
    {
        $this->assertInstanceOf(PrivateFragmentActionInterface::class, $this->controller(0, false, false));
    }

    public function testNothingWhenBothAreEmpty(): void
    {
        $this->controller(0, true, false)->execute();

        $this->assertSame('', $this->contents);
    }

    public function testCompareForAnyVisitorWithItems(): void
    {
        $this->controller(2, false, true)->execute();

        $this->assertSame('<compare/>', $this->contents, 'no wish list for guests');
    }

    public function testWishListOnlyWithItems(): void
    {
        $this->controller(0, true, true)->execute();
        $this->assertSame('<wishlist/>', $this->contents);

        $this->controller(0, true, false)->execute();
        $this->assertSame('', $this->contents);
    }

    private function controller(int $compareCount, bool $loggedIn, bool $wishlistItems): Index
    {
        $compareBlock = $this->createStub(Template::class);
        $compareBlock->method('setTemplate')->willReturnSelf();
        $compareBlock->method('toHtml')->willReturn('<compare/>');
        $wishlistBlock = $this->createStub(WishlistSidebar::class);
        $wishlistBlock->method('setTemplate')->willReturnSelf();
        $wishlistBlock->method('hasWishlistItems')->willReturn($wishlistItems);
        $wishlistBlock->method('toHtml')->willReturn('<wishlist/>');

        $layout = $this->createStub(LayoutInterface::class);
        $layout->method('createBlock')->willReturnCallback(
            fn ($type) => match ($type) {
                Template::class => $compareBlock,
                WishlistSidebar::class => $wishlistBlock,
                default => $this->createStub(\Magento\Framework\View\Element\AbstractBlock::class),
            }
        );
        $layoutFactory = $this->createStub(LayoutFactory::class);
        $layoutFactory->method('create')->willReturn($layout);

        $raw = $this->createStub(Raw::class);
        $raw->method('setContents')->willReturnCallback(function ($c) use ($raw) {
            $this->contents = $c;
            return $raw;
        });
        $resultFactory = $this->createStub(ResultFactory::class);
        $resultFactory->method('create')->willReturn($raw);

        $compare = $this->createStub(CompareHelper::class);
        $compare->method('getItemCount')->willReturn($compareCount);
        $session = $this->createStub(CustomerSession::class);
        $session->method('isLoggedIn')->willReturn($loggedIn);

        return new Index($resultFactory, $layoutFactory, $compare, $session, $this->createStub(WishlistData::class));
    }
}
