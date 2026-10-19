<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Core\Api;

use Magento\Catalog\Model\Product;

/**
 * More badges for a product's card and page (ViewModel\ProductBadges),
 * registered in a module's etc/frontend/di.xml:
 *
 *     <type name="Zero\Core\ViewModel\ProductBadges">
 *         <arguments>
 *             <argument name="providers" xsi:type="array">
 *                 <item name="acme" xsi:type="object">Acme\Labels\Model\Badges</item>
 *             </argument>
 *         </arguments>
 *     </type>
 *
 * Badges sort by `sort` (New is 20, Sale 30); `code` becomes the class
 * product-badge-<code>, which the module styles in its zero/tailwind.css.
 * Cards are cached (for up to an hour), so a badge must not depend on the
 * visitor.
 */
interface ProductBadgeProviderInterface
{
    /**
     * @return array<int, array{code: string, label: string, sort: int}>
     */
    public function getBadges(Product $product): array;
}
