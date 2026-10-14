<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Core\Test\Unit\Plugin\Controller;

use Magento\Framework\App\Response\Http as HttpResponse;
use PHPUnit\Framework\TestCase;
use Zero\Core\Controller\PrivateFragmentActionInterface;
use Zero\Core\Plugin\Controller\PreventFragmentCaching;

class PreventFragmentCachingTest extends TestCase
{
    public function testPrivateFragmentsAreNeverCached(): void
    {
        $response = $this->createMock(HttpResponse::class);
        $response->expects($this->once())->method('setNoCacheHeaders');

        (new PreventFragmentCaching($response))->beforeExecute($this->createStub(PrivateFragmentActionInterface::class));
    }
}
