<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Core\Model\Config\Source;

use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Framework\Data\OptionSourceInterface;

/**
 * Top-level categories (children of every root category) for the homepage
 * settings, labelled "Root / Category" as each website may have its own root.
 */
class TopCategories implements OptionSourceInterface
{
    public function __construct(
        private readonly CategoryCollectionFactory $categoryCollectionFactory
    ) {
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function toOptionArray(): array
    {
        $roots = [];
        foreach ($this->categoryCollectionFactory->create()->addAttributeToSelect('name')
            ->addFieldToFilter('level', 1) as $root) {
            $roots[(int) $root->getId()] = (string) $root->getName();
        }
        $options = [];
        foreach ($this->categoryCollectionFactory->create()->addAttributeToSelect('name')
            ->addFieldToFilter('level', 2)->setOrder('path', 'ASC') as $category) {
            $root = $roots[(int) $category->getParentId()] ?? '';
            $options[] = [
                'value' => (string) $category->getId(),
                'label' => ($root !== '' ? $root . ' / ' : '') . $category->getName(),
            ];
        }

        return $options;
    }
}
