<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Core\Test\Unit\Controller\Captcha;

use Magento\Captcha\Block\Captcha\DefaultCaptcha;
use Magento\Captcha\Helper\Data as CaptchaHelper;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\TestFramework\Unit\Helper\MockCreationTrait;
use Magento\Framework\View\LayoutInterface;
use Magento\Framework\View\Result\Layout;
use Magento\Framework\View\Result\LayoutFactory;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Zero\Core\Controller\Captcha\Refresh;

#[AllowMockObjectsWithoutExpectations]
class RefreshTest extends TestCase
{
    use MockCreationTrait;

    private LayoutInterface&MockObject $layout;
    private DefaultCaptcha&MockObject $block;

    protected function setUp(): void
    {
        $this->block = $this->createPartialMockWithReflection(DefaultCaptcha::class, ['setFormId', 'setImgHeight']);
        $this->layout = $this->createMock(LayoutInterface::class);
        $this->layout->method('getBlock')->willReturn($this->block);
    }

    public function testConfiguredFormIsRendered(): void
    {
        $this->block->expects($this->once())->method('setFormId')->with('user_forgotpassword');
        $this->block->expects($this->once())->method('setImgHeight')->with(50.0);
        $this->layout->expects($this->once())->method('addOutputElement')->with('zero.captcha.refresh');

        $this->controller(['form_id' => 'user_forgotpassword', 'img_height' => '50'])->execute();
    }

    public function testUnknownFormRendersNothing(): void
    {
        $this->block->expects($this->never())->method('setFormId');
        $this->layout->expects($this->never())->method('addOutputElement');

        $this->controller(['form_id' => 'admin_login'])->execute();
    }

    public function testNonNumericHeightIsIgnored(): void
    {
        $this->block->expects($this->never())->method('setImgHeight');

        $this->controller(['form_id' => 'user_login', 'img_height' => '50px"onload'])->execute();
    }

    /**
     * @param array<string, string> $params
     */
    private function controller(array $params): Refresh
    {
        $request = $this->createStub(RequestInterface::class);
        $request->method('getParam')->willReturnCallback(static fn (string $key) => $params[$key] ?? null);
        $helper = $this->createStub(CaptchaHelper::class);
        $helper->method('getConfig')->willReturn('user_login, user_forgotpassword,user_create');
        $result = $this->createStub(Layout::class);
        $result->method('getLayout')->willReturn($this->layout);
        $factory = $this->createStub(LayoutFactory::class);
        $factory->method('create')->willReturn($result);

        return new Refresh($factory, $request, $helper);
    }
}
