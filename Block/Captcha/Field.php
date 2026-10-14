<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Core\Block\Captcha;

use Magento\Captcha\Helper\Data as CaptchaHelper;
use Magento\Framework\View\Element\Template;

/**
 * A form's CAPTCHA field on a page the page cache may hold: the input, and a
 * placeholder that loads the image from Controller\Captcha\Refresh (a private
 * fragment) once the visitor's session is ready. Prints nothing while the
 * form doesn't ask for a CAPTCHA. A child of the form's block, with its id:
 *
 *     <block class="Zero\Core\Block\Captcha\Field" name="..." as="captcha">
 *         <arguments>
 *             <argument name="form_id" xsi:type="string">zero_blog_comment</argument>
 *         </arguments>
 *     </block>
 *
 * The controller checks the answer with Model\Captcha\Check.
 */
class Field extends Template
{
    protected $_template = 'Zero_Core::captcha/field.phtml';

    public function __construct(
        Template\Context $context,
        private readonly CaptchaHelper $captchaHelper,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getFormId(): string
    {
        return (string) $this->getData('form_id');
    }

    public function getInputName(): string
    {
        return CaptchaHelper::INPUT_NAME_FIELD_VALUE . '[' . $this->getFormId() . ']';
    }

    public function getRefreshUrl(): string
    {
        return $this->getUrl('zero/captcha/refresh', ['_query' => ['form_id' => $this->getFormId()]]);
    }

    /**
     * @inheritDoc
     */
    protected function _toHtml()
    {
        $formId = $this->getFormId();
        if ($formId === '' || !$this->captchaHelper->getCaptcha($formId)->isRequired()) {
            return '';
        }

        return parent::_toHtml();
    }
}
