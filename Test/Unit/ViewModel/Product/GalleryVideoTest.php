<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Core\Test\Unit\ViewModel\Product;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Zero\Core\ViewModel\Product\GalleryVideo;

class GalleryVideoTest extends TestCase
{
    public static function urls(): array
    {
        $yt = 'https://www.youtube-nocookie.com/embed/abcDEF12345?autoplay=1&rel=0&playsinline=1';
        return [
            'watch' => ['https://www.youtube.com/watch?v=abcDEF12345&t=10', $yt],
            'short link' => ['https://youtu.be/abcDEF12345', $yt],
            'embed' => ['https://www.youtube.com/embed/abcDEF12345', $yt],
            'shorts' => ['https://youtube.com/shorts/abcDEF12345', $yt],
            'mobile' => ['https://m.youtube.com/watch?v=abcDEF12345', $yt],
            'vimeo' => ['https://vimeo.com/123456789', 'https://player.vimeo.com/video/123456789?autoplay=1'],
            'vimeo player' => ['https://player.vimeo.com/video/123456789', 'https://player.vimeo.com/video/123456789?autoplay=1'],
            'other host' => ['https://example.com/watch?v=abcDEF12345', null],
            'lookalike host' => ['https://notyoutube.com/watch?v=abcDEF12345', null],
            'bad id' => ['https://www.youtube.com/watch?v=abc"><script>', null],
            'javascript' => ['javascript:alert(1)', null],
            'empty' => ['', null],
        ];
    }

    #[DataProvider('urls')]
    public function testEmbedUrl(string $url, ?string $expected): void
    {
        $this->assertSame($expected, (new GalleryVideo())->embedUrl($url));
    }
}
