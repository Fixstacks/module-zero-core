<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Core\ViewModel\Product;

use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * Product videos (Images and Videos > Add Video) as embeddable players for
 * the gallery: YouTube (privacy-enhanced youtube-nocookie.com) and Vimeo,
 * the hosts csp_whitelist.xml allows in frames. Any other URL isn't
 * embedded; the gallery links to it instead.
 */
class GalleryVideo implements ArgumentInterface
{
    /**
     * The player URL for a video page URL, or null when it isn't YouTube or Vimeo.
     */
    public function embedUrl(string $url): ?string
    {
        $url = trim($url);
        if ($url === '' || !preg_match('#^https?://#i', $url)) {
            return null;
        }
        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));
        $path = (string) ($parts['path'] ?? '');
        parse_str((string) ($parts['query'] ?? ''), $query);

        $youtube = null;
        if (in_array($host, ['youtu.be', 'www.youtu.be'], true)) {
            $youtube = trim($path, '/');
        } elseif (preg_match('/(^|\.)youtube(-nocookie)?\.com$/', $host)) {
            $youtube = is_string($query['v'] ?? null)
                ? $query['v']
                : (preg_match('#^/(embed|shorts|live|v)/([^/?]+)#', $path, $m) ? $m[2] : null);
        }
        if ($youtube !== null && preg_match('/^[A-Za-z0-9_-]{6,20}$/', $youtube)) {
            return 'https://www.youtube-nocookie.com/embed/' . $youtube . '?autoplay=1&rel=0&playsinline=1';
        }

        if (preg_match('/(^|\.)vimeo\.com$/', $host) && preg_match('#/(?:video/)?(\d{5,12})(?:/|$)#', $path, $m)) {
            return 'https://player.vimeo.com/video/' . $m[1] . '?autoplay=1';
        }

        return null;
    }
}
