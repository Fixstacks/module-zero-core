<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Zero\Core\ViewModel;

use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Sales\CustomerData\LastOrderedItems as LastOrderedItemsSection;

/**
 * "Recently Ordered" sidebar data. Stock's reorder/sidebar.phtml is pure
 * Knockout, filled from the lastOrderedItems customer-data section, so it is
 * empty without JS.
 *
 * Calls the stock section source (Sales\CustomerData\LastOrderedItems) directly:
 * getSectionData()['items'] is its only public surface and already does the
 * last-order lookup, status filtering, salable check and URLs.
 */
class LastOrderedItems implements ArgumentInterface
{
    public function __construct(
        private readonly LastOrderedItemsSection $lastOrderedItemsSection,
        private readonly FormKey $formKey
    ) {
    }

    /**
     * Last-order items, or [] if the customer has no eligible last order.
     *
     * @return array<int, array{id: mixed, name: string, url: string|null, is_saleable: bool, product_id: mixed}>
     */
    public function getItems(): array
    {
        return $this->lastOrderedItemsSection->getSectionData()['items'] ?? [];
    }

    /**
     * CSRF form-key value for the plain POST "Add to Cart" form.
     *
     * @return string
     */
    public function getFormKey(): string
    {
        return $this->formKey->getFormKey();
    }
}
