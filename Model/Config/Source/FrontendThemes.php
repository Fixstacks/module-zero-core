<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Core\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Magento\Theme\Model\ResourceModel\Theme\CollectionFactory;

/**
 * Registered storefront themes by path (Magento/luma), which stays the same
 * from one installation to the next, unlike theme IDs.
 */
class FrontendThemes implements OptionSourceInterface
{
    public function __construct(
        private readonly CollectionFactory $collectionFactory
    ) {
    }

    /**
     * @inheritDoc
     */
    public function toOptionArray(): array
    {
        $options = [];
        $themes = $this->collectionFactory->create()
            ->addFieldToFilter('area', 'frontend')
            ->setOrder('theme_path', 'ASC');
        foreach ($themes as $theme) {
            $options[] = [
                'value' => $theme->getThemePath(),
                'label' => sprintf('%s (%s)', $theme->getThemeTitle(), $theme->getThemePath()),
            ];
        }

        return $options;
    }
}
