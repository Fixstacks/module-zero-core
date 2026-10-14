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
 * Magento's 404 page ("no-route") says "Go back to the previous page" with
 * `onclick="history.go(-1)"`: an inline script, which the storefront's CSP
 * blocks (the link did nothing; Firefox reports a violation on every 404).
 * That item becomes a plain link to the home page. Only pages that still
 * have that exact link change; edited ones are left alone.
 */
class RemoveInlineScriptFrom404 implements DataPatchInterface
{
    private const PATTERN = '#<a href="\#" onclick="history\.go\(-1\); return false;">.*?</a>.*?</li>#su';

    public function __construct(private readonly ModuleDataSetupInterface $moduleDataSetup)
    {
    }

    public function apply(): void
    {
        $connection = $this->moduleDataSetup->getConnection();
        $table = $this->moduleDataSetup->getTable('cms_page');
        $pages = $connection->fetchPairs(
            $connection->select()->from($table, ['page_id', 'content'])
                ->where('identifier = ?', 'no-route')
                ->where('content LIKE ?', '%onclick="history.go(-1)%')
        );
        foreach ($pages as $pageId => $content) {
            $text = preg_match('/\p{Arabic}/u', (string) $content) ? 'العودة إلى الصفحة الرئيسية' : 'Go to the home page';
            $fixed = preg_replace(self::PATTERN, '<a href="{{store url=""}}">' . $text . '</a>.</li>', (string) $content);
            if (is_string($fixed) && $fixed !== $content) {
                $connection->update($table, ['content' => $fixed], ['page_id = ?' => (int) $pageId]);
            }
        }
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
