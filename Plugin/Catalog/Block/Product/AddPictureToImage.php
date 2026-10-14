<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Core\Plugin\Catalog\Block\Product;

use Magento\Catalog\Block\Product\Image;
use Magento\Catalog\Block\Product\ImageFactory;
use Zero\Core\Model\Image\ProductPicture;

/**
 * Product image blocks (cards in listings, widgets, related products) are
 * made by ImageFactory, not by layout, so their template can't be given a
 * view model there. This hands it ProductPicture as `zero_picture`; the
 * theme's Magento_Catalog::product/image_with_borders.phtml renders <picture>.
 */
class AddPictureToImage
{
    public function __construct(
        private readonly ProductPicture $picture
    ) {
    }

    /**
     * @param ImageFactory $subject
     * @param Image $result
     * @return Image
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterCreate(ImageFactory $subject, Image $result): Image
    {
        $result->setData('zero_picture', $this->picture);

        return $result;
    }
}
