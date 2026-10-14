<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Core\Model\Social;

/**
 * What a page is when its link is shared: the first source (Model\Social\Tags,
 * `sources` in di.xml, by sort key) that knows the current page answers.
 * Empty values are filled in from the page's title, meta description, URL
 * and the default image.
 */
interface SourceInterface
{
    /**
     * @return array{type?: string, title?: string, description?: string, image?: string, image_alt?: string,
     *     url?: string, extra?: array<string, string>}|null null: not this source's page
     */
    public function describe(): ?array;
}
