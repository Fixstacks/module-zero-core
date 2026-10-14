<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Core\ViewModel\Directory;

use Magento\Directory\Model\ResourceModel\Country\CollectionFactory as CountryCollectionFactory;
use Magento\Directory\Model\ResourceModel\Region\CollectionFactory as RegionCollectionFactory;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * Quote-independent country/region data for the region `<select>` fragment
 * (Controller\Checkout\Regions via zero_checkout_regions.xml), used by the
 * customer address form as well as checkout.
 *
 * Stock's address form ships an empty region select and a disabled
 * free-text field until JS fills them, so a US/CA state couldn't be entered.
 * Lives in Zero_Core so the shared endpoint doesn't depend on Zero_Checkout.
 */
class CountryRegionProvider implements ArgumentInterface
{
    public function __construct(
        private readonly CountryCollectionFactory $countryCollectionFactory,
        private readonly RegionCollectionFactory $regionCollectionFactory
    ) {
    }

    /**
     * @return array<string, string> country_id => name
     */
    public function getCountries(): array
    {
        $collection = $this->countryCollectionFactory->create()->loadByStore();
        $options = [];
        foreach ($collection as $country) {
            $options[$country->getCountryId()] = $country->getName();
        }

        return $options;
    }

    /**
     * @return array<int, string> region_id => name
     */
    public function getRegionsForCountry(string $countryId): array
    {
        $collection = $this->regionCollectionFactory->create()->addCountryFilter($countryId);
        $options = [];
        foreach ($collection as $region) {
            $options[$region->getRegionId()] = $region->getName();
        }

        return $options;
    }
}
