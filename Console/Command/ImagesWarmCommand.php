<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Core\Console\Command;

use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\App\Emulation;
use Magento\Store\Model\StoreManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Zero\Core\Model\Image\ProductPicture;

/**
 * bin/magento zero:images:warm [--store=code]
 *
 * Writes the JPEG and WebP copies the Zero theme's product cards and galleries
 * use (Model\Image\ProductPicture) for every enabled product, so the first
 * visitor of a page doesn't wait for them. Copies that are up to date are
 * kept. Run it after a product import, before a launch, or from a deploy.
 */
class ImagesWarmCommand extends Command
{
    private const PAGE_SIZE = 200;

    public function __construct(
        private readonly CollectionFactory $collectionFactory,
        private readonly ImageHelper $imageHelper,
        private readonly ProductPicture $picture,
        private readonly Emulation $emulation,
        private readonly StoreManagerInterface $storeManager,
        private readonly State $state
    ) {
        parent::__construct();
    }

    /**
     * @inheritDoc
     */
    protected function configure(): void
    {
        $this->setName('zero:images:warm')
            ->setDescription("Makes the Zero theme's product image copies (JPEG and WebP) ahead of the first visit")
            ->addOption('store', null, InputOption::VALUE_REQUIRED, 'Store view code (default: the default store view)');
    }

    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->state->setAreaCode(Area::AREA_FRONTEND);
        } catch (LocalizedException $e) {
            // Already set.
        }
        $store = $this->storeManager->getStore((string) ($input->getOption('store') ?: $this->storeManager->getDefaultStoreView()?->getCode()));
        $this->emulation->startEnvironmentEmulation((int) $store->getId(), Area::AREA_FRONTEND, true);
        try {
            [$products, $images] = $this->warm($output);
        } finally {
            $this->emulation->stopEnvironmentEmulation();
        }
        $output->writeln(sprintf(
            '<info>%d products, %d images: %d copies written, the rest were up to date.</info>',
            $products,
            $images,
            $this->picture->written()
        ));

        return Command::SUCCESS;
    }

    /**
     * @return array{0: int, 1: int} products seen, images seen
     */
    private function warm(OutputInterface $output): array
    {
        $products = 0;
        $images = 0;
        $card = $gallery = null;
        $page = 1;
        do {
            $collection = $this->collectionFactory->create()
                ->addAttributeToSelect(['small_image', 'image'])
                ->addAttributeToFilter('status', Status::STATUS_ENABLED)
                ->addMediaGalleryData()
                ->setPageSize(self::PAGE_SIZE)
                ->setCurPage($page);
            /** @var Product $product */
            foreach ($collection as $product) {
                // The roles' frames come from the theme's view.xml, the same for every product.
                $card ??= $this->frame($product, 'category_page_grid');
                $gallery ??= $this->frame($product, 'product_page_image_medium');
                $products++;
                if ($file = (string) $product->getData('small_image')) {
                    $images++;
                    $this->picture->getSrcsets($this->url($file), $card[0], $card[1], ProductPicture::CARD_WIDTHS);
                }
                foreach ($product->getMediaGalleryImages() ?? [] as $image) {
                    if ($image->getData('media_type') === 'image' && ($file = (string) $image->getFile()) !== '') {
                        $images++;
                        $this->picture->getSrcsets($this->url($file), $gallery[0], $gallery[1], ProductPicture::GALLERY_WIDTHS);
                    }
                }
            }
            $output->write(sprintf("\r%d products, %d copies written", $products, $this->picture->written()));
            $page++;
        } while ($collection->count() === self::PAGE_SIZE);
        $output->writeln('');

        return [$products, $images];
    }

    /**
     * @return array{0: int, 1: int} width and height of an image role
     */
    private function frame(Product $product, string $role): array
    {
        $image = $this->imageHelper->init($product, $role);

        return [(int) $image->getWidth(), (int) $image->getHeight()];
    }

    /**
     * A URL of the shape ProductPicture reads the original's path from.
     */
    private function url(string $file): string
    {
        return '/media/catalog/product/' . ltrim($file, '/');
    }
}
