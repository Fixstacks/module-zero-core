<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Core\Model\Compat;

/**
 * Which compat modules adapt which modules to the Zero theme. Registered in
 * the compat module's di.xml:
 *
 *     <type name="Zero\Core\Model\Compat\Registry">
 *         <arguments>
 *             <argument name="compatModules" xsi:type="array">
 *                 <item name="Vendor_Module" xsi:type="array">
 *                     <item name="Vendor_ZeroCompat" xsi:type="string">Vendor_ZeroCompat</item>
 *                 </item>
 *             </argument>
 *         </arguments>
 *     </type>
 *
 * See Plugin\...\UseCompatTemplates for the template order.
 */
class Registry
{
    /**
     * @param array<string, array<string, string>> $compatModules Original module => its compat modules
     * @param string[] $themes Theme paths whose descendants use compat templates
     */
    public function __construct(
        private readonly array $compatModules = [],
        private readonly array $themes = ['Zero/default']
    ) {
    }

    /**
     * Compat modules for a module, in registration order.
     *
     * @param string $moduleName
     * @return string[]
     */
    public function getCompatModules(string $moduleName): array
    {
        return array_values(array_filter((array) ($this->compatModules[$moduleName] ?? [])));
    }

    /**
     * Every original module that has a compat module.
     *
     * @return array<string, string[]>
     */
    public function getAll(): array
    {
        $all = [];
        foreach (array_keys($this->compatModules) as $moduleName) {
            $compat = $this->getCompatModules((string) $moduleName);
            if ($compat) {
                $all[(string) $moduleName] = $compat;
            }
        }

        return $all;
    }

    /**
     * Theme paths that compat templates apply to, with their child themes.
     *
     * @return string[]
     */
    public function getThemes(): array
    {
        return array_values($this->themes);
    }
}
