<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Core\ViewModel;

use Magento\Framework\UrlInterface;
use Magento\Framework\Url\EncoderInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * The current page URL, encoded like Customer\Model\Url::getLoginUrlParams().
 *
 * The account links are an HTMX fragment, so their "return here after login"
 * referer would resolve to the fragment URL. The cacheable toplinks shell
 * passes this value as the fragment's `referer` param, and AuthorizationLink's
 * getRequestReferrer() picks it up. Safe in a cached page: the URL is the same
 * for every visitor of that page.
 */
class CurrentUrlEncoded implements ArgumentInterface
{
    /**
     * @param UrlInterface $urlBuilder
     * @param EncoderInterface $urlEncoder
     */
    public function __construct(
        private readonly UrlInterface $urlBuilder,
        private readonly EncoderInterface $urlEncoder
    ) {
    }

    /**
     * The current real page's own URL, Magento-encoded for a `referer` query param.
     *
     * @return string
     */
    public function getEncodedCurrentUrl(): string
    {
        return $this->urlEncoder->encode(
            $this->urlBuilder->getUrl('*/*/*', ['_current' => true, '_use_rewrite' => true])
        );
    }
}
