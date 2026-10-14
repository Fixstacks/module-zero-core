<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Core\Plugin\Controller;

use Magento\Framework\App\Response\Http as HttpResponse;
use Zero\Core\Controller\PrivateFragmentActionInterface;

/**
 * Sends no-cache headers for every PrivateFragmentActionInterface controller.
 * The built-in page cache stores any Result\Layout response unless told
 * otherwise, and PageCache\Kernel::process() only caches responses with
 * `Cache-Control: public ... s-maxage=`, so setNoCacheHeaders() is enough.
 */
class PreventFragmentCaching
{
    /**
     * @param HttpResponse $response
     */
    public function __construct(
        private readonly HttpResponse $response
    ) {
    }

    /**
     * Mark the fragment response as not cacheable before the action runs.
     *
     * @param PrivateFragmentActionInterface $subject
     * @return void
     */
    public function beforeExecute(PrivateFragmentActionInterface $subject): void
    {
        $this->response->setNoCacheHeaders();
    }
}
