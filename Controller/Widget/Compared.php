<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Core\Controller\Widget;

use Zero\Core\Controller\PrivateFragmentActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\View\LayoutFactory;
use Magento\Reports\Block\Product\Widget\Compared as ComparedBlock;

/**
 * Live "Recently Compared Products" content; same approach as Widget\Viewed.
 */
class Compared implements PrivateFragmentActionInterface
{
    private const TEMPLATE_MAP = [
        'grid' => 'Magento_Reports::widget/compared/content/compared_grid.phtml',
        'list' => 'Magento_Reports::widget/compared/content/compared_list.phtml',
        'default_list' => 'Magento_Reports::widget/compared/column/compared_default_list.phtml',
        'names_list' => 'Magento_Reports::widget/compared/column/compared_names_list.phtml',
        'images_list' => 'Magento_Reports::widget/compared/column/compared_images_list.phtml',
    ];

    /**
     * @param RequestInterface $request
     * @param ResultFactory $resultFactory
     * @param LayoutFactory $layoutFactory
     */
    public function __construct(
        private readonly RequestInterface $request,
        private readonly ResultFactory $resultFactory,
        private readonly LayoutFactory $layoutFactory
    ) {
    }

    /**
     * Render the real, live "Recently Compared" fragment.
     *
     * @return Raw
     */
    public function execute()
    {
        $templateKey = (string) $this->request->getParam('template', 'grid');
        $template = self::TEMPLATE_MAP[$templateKey] ?? self::TEMPLATE_MAP['grid'];
        $pageSize = max(1, (int) $this->request->getParam('page_size', 5));

        $layout = $this->layoutFactory->create();
        // Same as Widget\Viewed: register the 'formkey' child block, or the
        // forms render without a form key.
        $layout->createBlock(\Magento\Framework\View\Element\FormKey::class, 'formkey');
        $block = $layout->createBlock(ComparedBlock::class);
        $block->setTemplate($template);
        $block->setData('page_size', $pageSize);

        /** @var Raw $result */
        $result = $this->resultFactory->create(ResultFactory::TYPE_RAW);
        $result->setContents($block->toHtml());

        return $result;
    }
}
