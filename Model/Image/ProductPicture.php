<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Core\Model\Image;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * srcset candidates (JPEG + WebP) for a product image, for <picture>.
 * Magento makes one JPEG per role (blurry on high-density screens, no WebP);
 * this writes the image at each width into pub/media/zero/product/, framed
 * as Magento frames it, once, and again only when the original changes;
 * never upscaled. Placeholders and unknown URLs return null (plain <img>).
 *
 * A copy is written to a temporary name and renamed into place, so a visitor
 * never gets a half-written file and parallel requests don't trip over each
 * other. The first view of a page still pays for its copies (~60 ms an image):
 * bin/magento zero:images:warm makes them ahead of time (Console\Command\
 * ImagesWarmCommand), after an import or before a launch.
 */
class ProductPicture implements ArgumentInterface
{
    /** Widths offered for a product card (listings, widgets) and for the product page gallery. */
    public const CARD_WIDTHS = [300, 600];
    public const GALLERY_WIDTHS = [700, 1400];

    private const DIR = 'zero/product';
    private const JPEG_QUALITY = 82;
    private const WEBP_QUALITY = 80;

    /** @var array<string, array{webp: string, jpeg: string}|null> */
    private array $memo = [];
    private int $written = 0;

    public function __construct(
        private readonly Filesystem $filesystem,
        private readonly StoreManagerInterface $storeManager,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * srcset strings for the image at the given widths, or null to keep the plain <img>.
     *
     * @param string $imageUrl URL Magento rendered for the image
     * @param int $frameWidth Width of the image role (aspect ratio of the frame)
     * @param int $frameHeight Height of the image role
     * @param int[] $widths Widths to offer, in pixels
     * @return array{webp: string, jpeg: string}|null
     */
    public function getSrcsets(string $imageUrl, int $frameWidth, int $frameHeight, array $widths): ?array
    {
        $key = implode('|', [$imageUrl, $frameWidth, $frameHeight, implode(',', $widths)]);
        if (!array_key_exists($key, $this->memo)) {
            $this->memo[$key] = $this->build($imageUrl, $frameWidth, $frameHeight, $widths);
        }

        return $this->memo[$key];
    }

    /**
     * @param string $imageUrl
     * @param int $frameWidth
     * @param int $frameHeight
     * @param int[] $widths
     * @return array{webp: string, jpeg: string}|null
     */
    private function build(string $imageUrl, int $frameWidth, int $frameHeight, array $widths): ?array
    {
        $file = $this->originalFile($imageUrl);
        if ($file === null || $frameWidth <= 0 || $frameHeight <= 0 || !function_exists('imagewebp')) {
            return null;
        }
        $media = $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA);
        $source = $media->getAbsolutePath('catalog/product/' . $file);
        $size = @getimagesize($source);
        if (!$size) {
            return null;
        }
        [$sourceWidth, $sourceHeight] = $size;

        $webp = [];
        $jpeg = [];
        sort($widths);
        foreach (array_values(array_unique(array_map('intval', $widths))) as $i => $width) {
            $height = (int) round($width * $frameHeight / $frameWidth);
            $scale = min($width / $sourceWidth, $height / $sourceHeight);
            if ($i > 0 && $scale > 1) {
                break;
            }
            $base = sprintf('%s/%dx%d/%s', self::DIR, $width, $height, preg_replace('/\.[^.\/]+$/', '', $file));
            try {
                if (!$this->write($media, $source, $base, $width, $height)) {
                    return null;
                }
            } catch (\Throwable $e) {
                $this->logger->warning('Zero product picture: ' . $e->getMessage(), ['file' => $file]);

                return null;
            }
            $url = $this->mediaUrl() . $base;
            $webp[] = sprintf('%s.webp %dw', $url, $width);
            $jpeg[] = sprintf('%s.jpg %dw', $url, $width);
        }

        return $webp ? ['webp' => implode(', ', $webp), 'jpeg' => implode(', ', $jpeg)] : null;
    }

    /**
     * Path below media/catalog/product/ of the original behind a Magento product image URL.
     *
     * @param string $imageUrl
     * @return string|null
     */
    public function originalFile(string $imageUrl): ?string
    {
        $path = (string) parse_url($imageUrl, PHP_URL_PATH);
        if (!preg_match('#/catalog/product/(?:cache/[0-9a-f]{32}/)?([^/]/[^/]/[^/]+\.(?:jpe?g|png|gif|webp))$#i', $path, $match)) {
            return null;
        }

        return $match[1];
    }

    /**
     * How many copies this instance wrote (zero:images:warm's progress).
     */
    public function written(): int
    {
        return $this->written;
    }

    /**
     * Writes base.jpg and base.webp unless both are newer than the original.
     *
     * @param \Magento\Framework\Filesystem\Directory\WriteInterface $media
     * @param string $source
     * @param string $base
     * @param int $width
     * @param int $height
     * @return bool
     */
    private function write($media, string $source, string $base, int $width, int $height): bool
    {
        $jpegPath = $media->getAbsolutePath($base . '.jpg');
        $webpPath = $media->getAbsolutePath($base . '.webp');
        $sourceTime = (int) filemtime($source);
        if (is_file($jpegPath) && is_file($webpPath)
            && filemtime($jpegPath) >= $sourceTime && filemtime($webpPath) >= $sourceTime
        ) {
            return true;
        }

        $original = @imagecreatefromstring((string) file_get_contents($source));
        if (!$original) {
            return false;
        }
        $sourceWidth = imagesx($original);
        $sourceHeight = imagesy($original);
        $scale = min($width / $sourceWidth, $height / $sourceHeight);
        $fitWidth = max(1, (int) round($sourceWidth * $scale));
        $fitHeight = max(1, (int) round($sourceHeight * $scale));

        $canvas = imagecreatetruecolor($width, $height);
        imagefill($canvas, 0, 0, (int) imagecolorallocate($canvas, 255, 255, 255));
        imagecopyresampled(
            $canvas,
            $original,
            intdiv($width - $fitWidth, 2),
            intdiv($height - $fitHeight, 2),
            0,
            0,
            $fitWidth,
            $fitHeight,
            $sourceWidth,
            $sourceHeight
        );
        imagedestroy($original);

        $media->create(dirname($base));
        $suffix = '.tmp-' . getmypid();
        $written = imagejpeg($canvas, $jpegPath . $suffix, self::JPEG_QUALITY)
            && imagewebp($canvas, $webpPath . $suffix, self::WEBP_QUALITY)
            && rename($jpegPath . $suffix, $jpegPath)
            && rename($webpPath . $suffix, $webpPath);
        imagedestroy($canvas);
        if (!$written) {
            foreach ([$base . '.jpg' . $suffix, $base . '.webp' . $suffix] as $leftover) {
                if ($media->isExist($leftover)) {
                    $media->delete($leftover);
                }
            }

            return false;
        }
        $this->written++;

        return true;
    }

    private function mediaUrl(): string
    {
        return rtrim($this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA), '/') . '/';
    }
}
