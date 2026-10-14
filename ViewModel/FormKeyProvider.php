<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Core\ViewModel;

use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * The current `form_key` for security/formkey.phtml. A wrapper because layout
 * object arguments must implement ArgumentInterface, which FormKey doesn't.
 */
class FormKeyProvider implements ArgumentInterface
{
    /**
     * @param FormKey $formKey
     */
    public function __construct(
        private readonly FormKey $formKey
    ) {
    }

    /**
     * The real, current form_key value.
     *
     * @return string
     */
    public function getFormKey(): string
    {
        return $this->formKey->getFormKey();
    }
}
