<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Core\Controller\Captcha;

use Magento\Captcha\Helper\Data as CaptchaHelper;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\View\Result\Layout;
use Magento\Framework\View\Result\LayoutFactory;
use Zero\Core\Controller\PrivateFragmentActionInterface;

/**
 * "Reload captcha" as an HTMX fragment (stock's data-mage-init widget doesn't
 * run here): re-renders the DefaultCaptcha block for the form_id, which makes
 * a new image. Private, never page-cached; unknown form_ids render nothing.
 */
class Refresh implements PrivateFragmentActionInterface
{
    private const BLOCK_NAME = 'zero.captcha.refresh';

    /**
     * @param LayoutFactory $resultLayoutFactory
     * @param RequestInterface $request
     * @param CaptchaHelper $captchaHelper
     */
    public function __construct(
        private readonly LayoutFactory $resultLayoutFactory,
        private readonly RequestInterface $request,
        private readonly CaptchaHelper $captchaHelper
    ) {
    }

    /**
     * Render the captcha image/reload fragment for the requested form.
     *
     * @return Layout
     */
    public function execute()
    {
        $formId = (string) $this->request->getParam('form_id');

        $resultLayout = $this->resultLayoutFactory->create();
        $resultLayout->addDefaultHandle();

        if (in_array($formId, $this->allowedFormIds(), true)) {
            $layout = $resultLayout->getLayout();
            $block = $layout->getBlock(self::BLOCK_NAME);
            if ($block !== false) {
                $block->setFormId($formId);
                $imgHeight = $this->request->getParam('img_height');
                if ($imgHeight !== null && is_numeric($imgHeight)) {
                    $block->setImgHeight((float) $imgHeight);
                }
            }
            $layout->addOutputElement(self::BLOCK_NAME);
        }

        return $resultLayout;
    }

    /**
     * Form ids with CAPTCHA configured (`customer/captcha/forms`, comma-separated).
     *
     * @return string[]
     */
    private function allowedFormIds(): array
    {
        $forms = (string) $this->captchaHelper->getConfig('forms');

        return array_filter(array_map('trim', explode(',', $forms)));
    }
}
