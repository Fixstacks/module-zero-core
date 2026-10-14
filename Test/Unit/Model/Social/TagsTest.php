<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Core\Test\Unit\Model\Social;

use Magento\Framework\UrlInterface;
use Magento\Framework\View\Page\Config as PageConfig;
use Magento\Framework\View\Page\Title;
use PHPUnit\Framework\TestCase;
use Zero\Core\Model\Social\Config;
use Zero\Core\Model\Social\SourceInterface;
use Zero\Core\Model\Social\Tags;
use Zero\Core\Model\Social\Text;

class TagsTest extends TestCase
{
    private function tags(array $sources, bool $enabled = true, string $defaultImage = 'https://s.test/media/zero/social/default/share.jpg'): Tags
    {
        $config = $this->createStub(Config::class);
        $config->method('isEnabled')->willReturn($enabled);
        $config->method('defaultImage')->willReturn($defaultImage);
        $config->method('siteName')->willReturn('Zero Store');
        $config->method('locale')->willReturn('en_US');
        $config->method('twitterSite')->willReturn('@zerostore');
        $title = $this->createStub(Title::class);
        $title->method('get')->willReturn(' Page title ');
        $page = $this->createStub(PageConfig::class);
        $page->method('getTitle')->willReturn($title);
        $page->method('getDescription')->willReturn('<p>Meta  description</p>');
        $url = $this->createStub(UrlInterface::class);
        $url->method('getCurrentUrl')->willReturn('https://s.test/some/page?utm_source=x');

        return new Tags($config, $page, $url, $sources);
    }

    private function source(?array $answer): SourceInterface
    {
        $source = $this->createStub(SourceInterface::class);
        $source->method('describe')->willReturn($answer);

        return $source;
    }

    public function testAnyPageGetsItsTitleDescriptionUrlAndTheDefaultImage(): void
    {
        $tags = $this->tags(['10_none' => $this->source(null)]);
        $this->assertSame([
            'og:site_name' => 'Zero Store',
            'og:type' => 'website',
            'og:title' => 'Page title',
            'og:description' => 'Meta description',
            'og:url' => 'https://s.test/some/page',
            'og:locale' => 'en_US',
            'og:image' => 'https://s.test/media/zero/social/default/share.jpg',
            'og:image:alt' => 'Page title',
        ], $tags->openGraph());
        $this->assertSame([
            'twitter:card' => 'summary_large_image',
            'twitter:site' => '@zerostore',
            'twitter:title' => 'Page title',
            'twitter:description' => 'Meta description',
            'twitter:image' => 'https://s.test/media/zero/social/default/share.jpg',
            'twitter:image:alt' => 'Page title',
        ], $tags->twitter());
    }

    public function testTheFirstSourceThatKnowsThePageWinsInKeyOrder(): void
    {
        $tags = $this->tags([
            '30_category' => $this->source(['title' => 'Category']),
            '20_product' => $this->source(['type' => 'product', 'title' => 'Bottle', 'image' => 'https://s.test/b.jpg',
                'image_alt' => 'A bottle', 'url' => 'https://s.test/bottle.html', 'extra' => ['product:availability' => 'in stock']]),
        ])->openGraph();
        $this->assertSame('product', $tags['og:type']);
        $this->assertSame('Bottle', $tags['og:title']);
        $this->assertSame('https://s.test/b.jpg', $tags['og:image']);
        $this->assertSame('A bottle', $tags['og:image:alt']);
        $this->assertSame('https://s.test/bottle.html', $tags['og:url']);
        $this->assertSame('in stock', $tags['product:availability']);
        // Filled in from the page where the source had nothing.
        $this->assertSame('Meta description', $tags['og:description']);
    }

    public function testNoImageAtAllMakesASmallCard(): void
    {
        $tags = $this->tags([], true, '');
        $this->assertArrayNotHasKey('og:image', $tags->openGraph());
        $this->assertArrayNotHasKey('og:image:alt', $tags->openGraph());
        $this->assertSame('summary', $tags->twitter()['twitter:card']);
    }

    public function testOffMeansNoTags(): void
    {
        $tags = $this->tags(['20_product' => $this->source(['title' => 'Bottle'])], false);
        $this->assertSame([], $tags->openGraph());
        $this->assertSame([], $tags->twitter());
    }

    public function testTextIsPlainAndShort(): void
    {
        $this->assertSame('First real text', Text::first(['', '  ', '<p>First</p><p>real {{widget type="x"}} text</p>', 'second']));
        $this->assertSame('Tom &amp; Jerry', htmlspecialchars(Text::first(['Tom &amp; Jerry'])));
        $long = str_repeat('word ', 100);
        $short = Text::first([$long]);
        $this->assertLessThanOrEqual(200, mb_strlen($short));
        $this->assertStringEndsWith('word…', $short);
    }
}
