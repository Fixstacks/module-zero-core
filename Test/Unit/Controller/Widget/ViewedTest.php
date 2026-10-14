<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Core\Test\Unit\Controller\Widget;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\View\Element\FormKey;
use Magento\Framework\View\LayoutFactory;
use Magento\Framework\View\LayoutInterface;
use Magento\Reports\Block\Product\Widget\Viewed as ViewedBlock;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Zero\Core\Controller\Widget\Viewed;

class ViewedTest extends TestCase
{
    /**
     * @return array<string, array{array<string, string>, string, int}>
     */
    public static function requests(): array
    {
        return [
            'list template' => [['template' => 'list', 'page_size' => '8'], 'Magento_Reports::widget/viewed/content/viewed_list.phtml', 8],
            'unknown template falls back to grid' => [['template' => '../../../etc/env.php'], 'Magento_Reports::widget/viewed/content/viewed_grid.phtml', 5],
            'page size at least 1' => [['page_size' => '-4'], 'Magento_Reports::widget/viewed/content/viewed_grid.phtml', 1],
        ];
    }

    /**
     * @param array<string, string> $params
     */
    #[DataProvider('requests')]
    public function testTemplateIsTakenFromAllowList(array $params, string $expectedTemplate, int $expectedPageSize): void
    {
        $request = $this->createStub(RequestInterface::class);
        $request->method('getParam')->willReturnCallback(
            static fn (string $key, $default = null) => $params[$key] ?? $default
        );

        $block = $this->createMock(ViewedBlock::class);
        $block->expects($this->once())->method('setTemplate')->with($expectedTemplate);
        $block->expects($this->once())->method('setData')->with('page_size', $expectedPageSize);
        $block->method('toHtml')->willReturn('<ol></ol>');

        $layout = $this->createStub(LayoutInterface::class);
        $layout->method('createBlock')->willReturnCallback(
            fn (string $class) => $class === ViewedBlock::class ? $block : $this->createStub(FormKey::class)
        );
        $layoutFactory = $this->createStub(LayoutFactory::class);
        $layoutFactory->method('create')->willReturn($layout);

        $raw = $this->createMock(Raw::class);
        $raw->expects($this->once())->method('setContents')->with('<ol></ol>');
        $resultFactory = $this->createStub(ResultFactory::class);
        $resultFactory->method('create')->willReturn($raw);

        (new Viewed($request, $resultFactory, $layoutFactory))->execute();
    }
}
