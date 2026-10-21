<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Core\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * Zero moved to its own home: the site and docs at zerofront.dev, the demo
 * store at demo.zerofront.dev, the module store at marketplace.zerofront.dev,
 * the code at github.com/zerofrontdev and the Composer packages from
 * fixstacks/* to zerofront/*. Stored content still pointing at the old
 * addresses (the homepage hero's link, CMS text, the module store's
 * products, licences listing package names) is rewritten here; logs and
 * CMS revisions keep what they recorded.
 */
class MoveToZerofrontDev implements DataPatchInterface
{
    /** Old => new, most specific first. */
    public const ADDRESSES = [
        'https://fixstacks.com/zero/docs' => 'https://zerofront.dev/docs',
        'https://fixstacks.com/zero/modules' => 'https://zerofront.dev/modules',
        'https://fixstacks.com/zero' => 'https://zerofront.dev',
        'https://zero.fixstacks.com' => 'https://demo.zerofront.dev',
        'zero.fixstacks.com' => 'demo.zerofront.dev',
        'https://marketplace.fixstacks.com' => 'https://marketplace.zerofront.dev',
        'marketplace.fixstacks.com' => 'marketplace.zerofront.dev',
        'github.com/Fixstacks/' => 'github.com/zerofrontdev/',
    ];

    /** Composer package names: vendor fixstacks => zerofront. */
    public const PACKAGES = [
        'fixstacks/module-zero-' => 'zerofront/module-zero-',
        'fixstacks/theme-frontend-zero' => 'zerofront/theme-frontend-zero',
        'fixstacks/suite' => 'zerofront/suite',
        'fixstacks/zero' => 'zerofront/zero',
    ];

    /** table => text columns that may hold them */
    private const COLUMNS = [
        'zero_banner_slide' => ['link_url'],
        'cms_block' => ['content'],
        'cms_page' => ['content'],
        'zero_cms_content' => ['data'],
        'core_config_data' => ['value'],
        'eav_attribute' => ['note'],
        'catalog_product_entity_text' => ['value'],
        'catalog_product_entity_varchar' => ['value'],
        'zero_license' => ['packages'],
    ];

    public function __construct(private readonly ModuleDataSetupInterface $moduleDataSetup)
    {
    }

    public function apply(): self
    {
        $connection = $this->moduleDataSetup->getConnection();
        $pairs = [];
        foreach (self::ADDRESSES + self::PACKAGES as $old => $new) {
            $pairs[$old] = $new;
            // The same text inside JSON written with escaped slashes.
            $pairs[str_replace('/', '\\/', $old)] = str_replace('/', '\\/', $new);
        }
        foreach (self::COLUMNS as $table => $columns) {
            $table = $this->moduleDataSetup->getTable($table);
            if (!$connection->isTableExists($table)) {
                continue;
            }
            foreach ($columns as $column) {
                foreach ($pairs as $old => $new) {
                    $connection->update(
                        $table,
                        [$column => new \Zend_Db_Expr(sprintf(
                            'REPLACE(%s, %s, %s)',
                            $connection->quoteIdentifier($column),
                            $connection->quote($old),
                            $connection->quote($new)
                        ))],
                        [$connection->quoteIdentifier($column) . ' LIKE ?' => '%' . addcslashes($old, '%_\\') . '%']
                    );
                }
            }
        }
        return $this;
    }

    public static function getDependencies(): array
    {
        return [];
    }

    public function getAliases(): array
    {
        return [];
    }
}
