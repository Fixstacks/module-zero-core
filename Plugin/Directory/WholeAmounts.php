<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Core\Plugin\Directory;

use Magento\Directory\Model\Currency;

/**
 * Amounts without minor units when there are none, for the currencies listed:
 * "4 800 ₴", not "4 800,00 ₴", as Ukrainian shops show prices. Amounts with
 * kopecks keep them. Storefront only (etc/frontend/di.xml). A module adds a
 * currency in di.xml:
 *
 *     <type name="Zero\Core\Plugin\Directory\WholeAmounts">
 *         <arguments>
 *             <argument name="currencies" xsi:type="array">
 *                 <item name="UAH" xsi:type="string">UAH</item>
 *             </argument>
 *         </arguments>
 *     </type>
 */
class WholeAmounts
{
    /** @var string[] */
    private readonly array $currencies;

    /**
     * @param string[] $currencies
     */
    public function __construct(array $currencies = [])
    {
        $this->currencies = array_values(array_filter(array_map('strval', $currencies)));
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function beforeFormatTxt(Currency $subject, $price, $options = []): array
    {
        if (in_array($subject->getCode(), $this->currencies, true) && is_numeric($price)
            && abs((float) $price - round((float) $price)) < 0.005
        ) {
            $options['precision'] = 0;
        }

        return [$price, $options];
    }
}
