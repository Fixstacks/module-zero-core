<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Core\Model\Catalog;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Stores > Configuration > Zero > Theme > Product Badges, for the current
 * store view: whether cards and pages show New and Sale (the group's other
 * fields come with Zero_ProductLabels).
 */
class BadgeSettings
{
    private const PATH = 'zero_theme/badges/';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    public function showsNew(): bool
    {
        return $this->flag('new');
    }

    public function showsSale(): bool
    {
        return $this->flag('sale');
    }

    private function flag(string $field): bool
    {
        return $this->scopeConfig->isSetFlag(self::PATH . $field, ScopeInterface::SCOPE_STORE);
    }
}
