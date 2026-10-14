<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Core\Model\Social\Source;

use Magento\Catalog\Helper\Data as CatalogHelper;
use Magento\Framework\App\Request\Http as HttpRequest;
use Zero\Core\Model\Social\SourceInterface;
use Zero\Core\Model\Social\Text;

/**
 * A category page: its name, description and image (the default image when
 * it has none: the product list isn't loaded yet when the head is built).
 */
class Category implements SourceInterface
{
    public function __construct(
        private readonly CatalogHelper $catalogHelper,
        private readonly HttpRequest $request
    ) {
    }

    public function describe(): ?array
    {
        if ($this->request->getFullActionName() !== 'catalog_category_view') {
            return null;
        }
        $category = $this->catalogHelper->getCategory();
        if (!$category || !$category->getId()) {
            return null;
        }

        return [
            'title' => (string) ($category->getData('meta_title') ?: $category->getName()),
            'description' => Text::first([(string) $category->getData('meta_description'), (string) $category->getData('description')]),
            'image' => (string) ($category->getImageUrl() ?: ''),
            'image_alt' => (string) $category->getName(),
            'url' => (string) $category->getUrl(),
        ];
    }
}
