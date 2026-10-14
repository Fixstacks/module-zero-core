<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Core\Model\Social;

use Magento\Framework\UrlInterface;
use Magento\Framework\View\Page\Config as PageConfig;

/**
 * The page's Open Graph and Twitter card tags. Sources (di.xml) describe
 * products, categories, CMS pages (Zero_Cms); any other page uses its title,
 * meta description, URL and the default image. Off: no tags at all.
 */
class Tags
{
    /** @var array<string, string>|null */
    private ?array $openGraph = null;

    /**
     * @param array<string, SourceInterface> $sources Tried in key order.
     */
    public function __construct(
        private readonly Config $config,
        private readonly PageConfig $pageConfig,
        private readonly UrlInterface $url,
        private readonly array $sources = []
    ) {
    }

    /**
     * og:* and product:* properties => content.
     *
     * @return array<string, string>
     */
    public function openGraph(): array
    {
        if ($this->openGraph !== null) {
            return $this->openGraph;
        }
        if (!$this->config->isEnabled()) {
            return $this->openGraph = [];
        }
        $page = [];
        $sources = $this->sources;
        ksort($sources);
        foreach ($sources as $source) {
            $described = $source->describe();
            if ($described !== null) {
                $page = $described;
                break;
            }
        }
        $title = trim((string) ($page['title'] ?? '')) ?: trim((string) $this->pageConfig->getTitle()->get());
        $description = (string) ($page['description'] ?? '') ?: Text::first([(string) $this->pageConfig->getDescription()]);
        $image = (string) ($page['image'] ?? '') ?: $this->config->defaultImage();
        $tags = [
            'og:site_name' => $this->config->siteName(),
            'og:type' => (string) ($page['type'] ?? 'website'),
            'og:title' => $title,
            'og:description' => $description,
            'og:url' => (string) ($page['url'] ?? '') ?: strtok($this->url->getCurrentUrl(), '?'),
            'og:locale' => $this->config->locale(),
            'og:image' => $image,
            'og:image:alt' => $image !== '' ? trim((string) ($page['image_alt'] ?? '')) ?: $title : '',
        ];

        return $this->openGraph = array_filter($tags + (array) ($page['extra'] ?? []), static fn ($v) => $v !== '');
    }

    /**
     * twitter:* names => content.
     *
     * @return array<string, string>
     */
    public function twitter(): array
    {
        $og = $this->openGraph();
        if (!$og) {
            return [];
        }

        return array_filter([
            'twitter:card' => isset($og['og:image']) ? 'summary_large_image' : 'summary',
            'twitter:site' => $this->config->twitterSite(),
            'twitter:title' => $og['og:title'] ?? '',
            'twitter:description' => $og['og:description'] ?? '',
            'twitter:image' => $og['og:image'] ?? '',
            'twitter:image:alt' => $og['og:image:alt'] ?? '',
        ], static fn ($v) => $v !== '');
    }
}
