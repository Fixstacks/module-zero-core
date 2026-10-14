<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Core\Model\Config\Source;

/**
 * TopCategories with an empty first option, for a single optional choice.
 */
class TopCategoriesOptional extends TopCategories
{
    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function toOptionArray(): array
    {
        return array_merge([['value' => '', 'label' => (string) __('-- None --')]], parent::toOptionArray());
    }
}
