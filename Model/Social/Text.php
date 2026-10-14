<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Core\Model\Social;

/**
 * Plain, short texts for share previews.
 */
class Text
{
    public const DESCRIPTION_LENGTH = 200;

    /**
     * The first non-empty text, without markup, Magento directives or extra
     * spaces, cut at a word to $length.
     *
     * @param string[] $candidates
     */
    public static function first(array $candidates, int $length = self::DESCRIPTION_LENGTH): string
    {
        foreach ($candidates as $text) {
            $text = preg_replace('/\{\{[^}]*\}\}/', ' ', $text) ?? $text;
            $text = html_entity_decode(strip_tags(str_replace('<', ' <', $text)), ENT_QUOTES | ENT_HTML5);
            $text = trim((string) preg_replace('/\s+/u', ' ', $text));
            if ($text !== '') {
                return self::shorten($text, $length);
            }
        }

        return '';
    }

    public static function shorten(string $text, int $length): string
    {
        if (mb_strlen($text) <= $length) {
            return $text;
        }
        $cut = mb_substr($text, 0, $length - 1);
        $space = mb_strrpos($cut, ' ');

        return rtrim($space !== false && $space > $length * 0.6 ? mb_substr($cut, 0, $space) : $cut, ' ,.;:') . '…';
    }
}
