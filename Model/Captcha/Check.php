<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Core\Model\Captcha;

use Magento\Captcha\Helper\Data as CaptchaHelper;
use Magento\Captcha\Observer\CaptchaStringResolver;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\LocalizedException;

/**
 * A form's CAPTCHA, checked on submit (Stores > Configuration > Customers >
 * Customer Configuration > CAPTCHA > Forms says which forms ask for one).
 * The form shows it with Block\Captcha\Field.
 */
class Check
{
    public function __construct(
        private readonly CaptchaHelper $captchaHelper,
        private readonly CaptchaStringResolver $captchaStringResolver
    ) {
    }

    /**
     * @throws LocalizedException when the form asks for a CAPTCHA and the answer is wrong
     */
    public function verify(RequestInterface $request, string $formId): void
    {
        $captcha = $this->captchaHelper->getCaptcha($formId);
        if ($captcha->isRequired() && !$captcha->isCorrect($this->captchaStringResolver->resolve($request, $formId))) {
            throw new LocalizedException(__('Incorrect CAPTCHA.'));
        }
    }
}
