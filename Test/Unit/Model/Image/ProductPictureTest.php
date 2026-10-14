<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Core\Test\Unit\Model\Image;

use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\WriteInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Zero\Core\Model\Image\ProductPicture;

class ProductPictureTest extends TestCase
{
    private string $media;

    protected function setUp(): void
    {
        if (!function_exists('imagewebp')) {
            $this->markTestSkipped('GD without WebP');
        }
        $this->media = sys_get_temp_dir() . '/zero-picture-' . uniqid();
        mkdir($this->media . '/catalog/product/w/j', 0777, true);
        // A 400x500 (4:5) photo, red.
        $image = imagecreatetruecolor(400, 500);
        imagefill($image, 0, 0, (int) imagecolorallocate($image, 255, 0, 0));
        imagejpeg($image, $this->media . '/catalog/product/w/j/jacket.jpg');
    }

    protected function tearDown(): void
    {
        if (isset($this->media)) {
            exec('rm -rf ' . escapeshellarg($this->media));
        }
    }

    public function testFindsTheOriginalBehindMagentoUrls(): void
    {
        $picture = $this->picture();
        $this->assertSame('w/j/jacket.jpg', $picture->originalFile(
            'https://shop.test/media/catalog/product/cache/7d5e430f90a6051cd5412efd7dd57c9c/w/j/jacket.jpg'
        ));
        $this->assertSame('w/j/jacket.jpg', $picture->originalFile('https://shop.test/media/catalog/product/w/j/jacket.jpg'));
        $this->assertNull($picture->originalFile(
            'https://shop.test/static/frontend/Zero/default/en_US/Magento_Catalog/images/product/placeholder/small_image.jpg'
        ));
    }

    public function testWritesJpegAndWebpPerWidthInTheRoleFrame(): void
    {
        $srcsets = $this->picture()->getSrcsets(
            'https://shop.test/media/catalog/product/cache/7d5e430f90a6051cd5412efd7dd57c9c/w/j/jacket.jpg',
            240,
            300,
            [300, 600]
        );

        // 600 would upscale the 400 px original, so it is left out.
        $this->assertSame([
            'webp' => 'https://shop.test/media/zero/product/300x375/w/j/jacket.webp 300w',
            'jpeg' => 'https://shop.test/media/zero/product/300x375/w/j/jacket.jpg 300w',
        ], $srcsets);
        $this->assertSame([300, 375], array_slice(getimagesize($this->media . '/zero/product/300x375/w/j/jacket.webp'), 0, 2));
        $this->assertSame([300, 375], array_slice(getimagesize($this->media . '/zero/product/300x375/w/j/jacket.jpg'), 0, 2));
    }

    public function testFitsTheWholePhotoOnWhiteInsteadOfCropping(): void
    {
        $this->picture()->getSrcsets('https://shop.test/media/catalog/product/w/j/jacket.jpg', 300, 300, [300]);

        $square = imagecreatefromjpeg($this->media . '/zero/product/300x300/w/j/jacket.jpg');
        $edge = imagecolorsforindex($square, imagecolorat($square, 2, 150));
        $centre = imagecolorsforindex($square, imagecolorat($square, 150, 150));
        $this->assertGreaterThan(240, $edge['green'], 'left edge is white padding');
        $this->assertLessThan(40, $centre['green'], 'centre is the red photo');
    }

    public function testRewritesOnlyWhenTheOriginalChanges(): void
    {
        $url = 'https://shop.test/media/catalog/product/w/j/jacket.jpg';
        $copy = $this->media . '/zero/product/300x375/w/j/jacket.webp';
        $this->picture()->getSrcsets($url, 240, 300, [300]);
        touch($copy, time() - 100);
        touch($this->media . '/catalog/product/w/j/jacket.jpg', time() - 200);
        clearstatcache();

        $this->picture()->getSrcsets($url, 240, 300, [300]);
        clearstatcache();
        $this->assertSame(time() - 100, filemtime($copy), 'up to date: kept');

        touch($this->media . '/catalog/product/w/j/jacket.jpg', time());
        clearstatcache();
        $this->picture()->getSrcsets($url, 240, 300, [300]);
        clearstatcache();
        $this->assertGreaterThanOrEqual(time() - 1, filemtime($copy), 'original changed: rewritten');
    }

    public function testMissingOriginalKeepsThePlainImage(): void
    {
        $this->assertNull($this->picture()->getSrcsets('https://shop.test/media/catalog/product/x/y/gone.jpg', 240, 300, [300]));
    }

    private function picture(): ProductPicture
    {
        $media = $this->createStub(WriteInterface::class);
        $media->method('getAbsolutePath')->willReturnCallback(fn ($path = null) => $this->media . '/' . $path);
        $media->method('create')->willReturnCallback(function ($path) {
            @mkdir($this->media . '/' . $path, 0777, true);

            return true;
        });
        $filesystem = $this->createStub(Filesystem::class);
        $filesystem->method('getDirectoryWrite')->willReturn($media);
        $store = $this->createStub(Store::class);
        $store->method('getBaseUrl')->willReturn('https://shop.test/media/');
        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);

        return new ProductPicture($filesystem, $storeManager, $this->createStub(LoggerInterface::class));
    }
}
