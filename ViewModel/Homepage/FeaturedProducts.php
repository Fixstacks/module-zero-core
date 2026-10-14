<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Core\ViewModel\Homepage;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\Product\ImageFactory;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Catalog\Model\ResourceModel\Product\Collection as ProductCollection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Framework\Pricing\Helper\Data as PricingHelper;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Catalog data for the homepage's "The shortlist" and "New, in orbit" sections,
 * plus the two curated editorial images.
 *
 * - Shortlist: one product per top-level department (DEPARTMENT_URL_KEYS, lowest
 *   entity_id). Labeled "Featured", not "Bestseller" — the bestseller
 *   aggregation table is empty here. A flat sort would return four near-identical
 *   Gear bags.
 * - New arrivals: created_at DESC.
 * - Both exclude the Fine Jewelry subtree (it sits right after the apparel
 *   category tiles).
 */
class FeaturedProducts implements ArgumentInterface
{
    private const GRID_SIZE = 4;

    /**
     * "New, in orbit" is a swipeable slider, so it shows more than fits on screen:
     * 8 = two lg:grid-cols-4 screens. Separate from GRID_SIZE (the static shortlist).
     */
    private const SLIDER_SIZE = 8;
    private const IMAGE_DISPLAY_AREA = 'category_page_grid';
    private const EXCLUDED_ROOT_URL_KEY = 'fine-jewelry';
    private const DEPARTMENT_URL_KEYS = ['women', 'men', 'gear', 'training'];

    /**
     * Hand-picked images for the "system spread" and "field note" sections, chosen
     * for composition rather than by a query.
     */
    private const SYSTEM_SPREAD_IMAGE = '/w/t/wt06-blue_main_1.jpg';
    private const EDITORIAL_QUOTE_IMAGE = '/w/t/wt09-white_main_1.jpg';

    /**
     * srcset widths for the two half-width editorial tiles (md:grid-cols-2 inside
     * max-w-6xl). 640 and 750 close gaps Lighthouse mobile runs hit at a ~609px
     * displayed width, which otherwise jumped straight to 960.
     *
     * @var int[]
     */
    private const SRCSET_WIDTHS = [480, 640, 750, 960, 1200];

    /**
     * Half width from md (768px), full width below where the tiles stack.
     */
    private const SRCSET_SIZES = '(min-width: 768px) 50vw, 100vw';

    /** @var array<int, string> category-id => name, avoids re-loading the same category per product */
    private array $categoryNameCache = [];

    /**
     * @param ProductCollectionFactory $productCollectionFactory
     * @param CategoryCollectionFactory $categoryCollectionFactory
     * @param ImageHelper $imageHelper
     * @param PricingHelper $pricingHelper
     * @param ImageFactory $productImageFactory
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        private readonly ProductCollectionFactory $productCollectionFactory,
        private readonly CategoryCollectionFactory $categoryCollectionFactory,
        private readonly ImageHelper $imageHelper,
        private readonly PricingHelper $pricingHelper,
        private readonly ImageFactory $productImageFactory,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * Resized `src` fallback for the "Made to move." tile.
     */
    public function getSystemSpreadImageUrl(): string
    {
        $widths = self::SRCSET_WIDTHS;
        return $this->resizedImageUrl(self::SYSTEM_SPREAD_IMAGE, (int) end($widths));
    }

    /**
     * Real, resized `srcset` candidates for the "Made to move." tile.
     */
    public function getSystemSpreadImageSrcset(): string
    {
        return $this->buildSrcset(self::SYSTEM_SPREAD_IMAGE);
    }

    /**
     * `sizes` shared by both editorial tiles.
     */
    public function getImageSizes(): string
    {
        return self::SRCSET_SIZES;
    }

    /**
     * Resized `src` fallback for the "Field Note 001" image.
     */
    public function getEditorialQuoteImageUrl(): string
    {
        $widths = self::SRCSET_WIDTHS;
        return $this->resizedImageUrl(self::EDITORIAL_QUOTE_IMAGE, (int) end($widths));
    }

    /**
     * Real, resized `srcset` candidates for the editorial-quote image.
     */
    public function getEditorialQuoteImageSrcset(): string
    {
        return $this->buildSrcset(self::EDITORIAL_QUOTE_IMAGE);
    }

    /**
     * srcset for a raw catalog/product image path, resized and cached through
     * Product\Image (as Banner's Carousel does). Helper\Image::init() needs a real
     * product; these are curated paths.
     *
     * @param string $relativeImagePath
     * @return string
     */
    private function buildSrcset(string $relativeImagePath): string
    {
        $candidates = [];
        foreach (self::SRCSET_WIDTHS as $width) {
            $candidates[] = $this->resizedImageUrl($relativeImagePath, $width) . ' ' . $width . 'w';
        }

        return implode(', ', $candidates);
    }

    /**
     * Resizes and caches one width candidate. Height is 1.3 × width, which covers
     * the source images' ~0.81 aspect ratio so width is always the constraint;
     * CSS object-cover does the final crop.
     *
     * @param string $relativeImagePath
     * @param int $width
     * @return string
     */
    private function resizedImageUrl(string $relativeImagePath, int $width): string
    {
        $model = $this->productImageFactory->create();
        $model->setDestinationSubdir('zero_hero');
        // Dimensions must be set before setBaseFile(): it computes the cache
        // path from them (see Zero\Banner\Block\Carousel::resizedImageUrl()).
        // first, fixed here too before it shipped with the same defect).
        $model->setWidth($width);
        $model->setHeight((int) round($width * 1.3));
        $model->setKeepAspectRatio(true);
        $model->setKeepFrame(false);
        $model->setKeepTransparency(true);
        $model->setConstrainOnly(true);
        $model->setBaseFile($relativeImagePath);

        if (!$model->isBaseFilePlaceholder() && !$model->isCached()) {
            $model->resize();
            $model->saveFile();
        }

        return $model->getUrl();
    }

    /**
     * "The shortlist": one product per top-level department.
     *
     * @return array<int, array{product: ProductInterface, badge: string, label: string, image: string,
     *     name: string, price: string, url: string}>
     */
    public function getShortlist(): array
    {
        $products = [];
        foreach (self::DEPARTMENT_URL_KEYS as $urlKey) {
            // Under the current store's root: another website may use the same URL key.
            $department = $this->categoryCollectionFactory->create()
                ->addAttributeToFilter('url_key', $urlKey)
                ->addFieldToFilter('parent_id', (int) $this->storeManager->getStore()->getRootCategoryId())
                ->setPageSize(1)
                ->getFirstItem();

            if (!$department->getId()) {
                continue;
            }

            // Include descendants: the sample catalog assigns products to leaf
            // categories ("Tops-Women"), not the department itself.
            $subtreeIds = [(int) $department->getId()];
            $descendants = $this->categoryCollectionFactory->create()
                ->addAttributeToFilter('path', ['like' => $department->getPath() . '/%'])
                ->addAttributeToFilter('is_active', 1);
            foreach ($descendants as $descendant) {
                $subtreeIds[] = (int) $descendant->getId();
            }

            $product = $this->productTypeCollection()
                ->addCategoriesFilter(['in' => $subtreeIds])
                ->setOrder('entity_id', ProductCollection::SORT_ORDER_ASC)
                ->setPageSize(1)
                ->getFirstItem();

            if ($product->getId()) {
                $products[] = $product;
            }

            if (count($products) >= self::GRID_SIZE) {
                break;
            }
        }

        // Real department coverage can legitimately fall short of GRID_SIZE — "Training" carries
        // zero real product assignments on this install (confirmed directly against
        // catalog_category_product, not a query bug) — backfilled with additional real, honestly
        // -sorted catalog products rather than shipping a visibly gappy 3-of-4 grid.
        if (count($products) < self::GRID_SIZE) {
            $usedIds = array_map(static fn ($product): int => (int) $product->getId(), $products);
            $backfill = $this->baseCollection()
                ->addFieldToFilter('entity_id', ['nin' => $usedIds ?: [0]])
                ->setOrder('entity_id', ProductCollection::SORT_ORDER_ASC)
                ->setPageSize(self::GRID_SIZE - count($products));
            foreach ($backfill as $product) {
                $products[] = $product;
            }
        }

        return $this->buildTiles($products, static fn (int $index): string => __('Featured')->render());
    }

    /**
     * "New, in orbit": SLIDER_SIZE newest products. The collection is still
     * unloaded here, so setPageSize() overrides baseCollection()'s GRID_SIZE.
     *
     * @return array<int, array{product: ProductInterface, badge: string, label: string, image: string,
     *     name: string, price: string, url: string}>
     */
    public function getNewArrivals(): array
    {
        $collection = $this->baseCollection()
            ->setOrder('created_at', ProductCollection::SORT_ORDER_DESC)
            ->setPageSize(self::SLIDER_SIZE);

        return $this->buildTiles(
            $collection->getItems(),
            static fn (int $index): string => sprintf('%02d', $index + 1)
        );
    }

    /**
     * Shared base: enabled, visible, simple/configurable, in the catalog root minus
     * Fine Jewelry, images joined.
     *
     * The type filter also keeps out virtual/downloadable e2e fixtures from another
     * project on this install (newer created_at, no photos) without a SKU blocklist.
     *
     * @return ProductCollection
     */
    private function baseCollection(): ProductCollection
    {
        $excludedRoot = $this->categoryCollectionFactory->create()
            ->addAttributeToFilter('url_key', self::EXCLUDED_ROOT_URL_KEY)
            ->setPageSize(1)
            ->getFirstItem();

        $collection = $this->productTypeCollection();

        if ($excludedRoot->getId()) {
            $collection->addCategoriesFilter(['nin' => [(int) $excludedRoot->getId()]]);
        }

        return $collection;
    }

    /**
     * baseCollection() without the category filter: getShortlist() adds its own
     * single department filter, and addCategoriesFilter() only ANDs correctly when
     * called once per collection.
     *
     * @return ProductCollection
     */
    private function productTypeCollection(): ProductCollection
    {
        $notVisible = \Magento\Catalog\Model\Product\Visibility::VISIBILITY_NOT_VISIBLE;

        // The current store's website only: the other websites (Beautiful Ukraine,
        // Kids Karamel) have their own, newer catalogs.
        return $this->productCollectionFactory->create()
            ->addStoreFilter()
            ->addAttributeToSelect(['name', 'price', 'small_image'])
            ->addAttributeToFilter('status', \Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED)
            ->addAttributeToFilter('visibility', ['neq' => $notVisible])
            ->addAttributeToFilter('type_id', ['in' => ['simple', 'configurable']])
            ->setPageSize(self::GRID_SIZE);
    }

    /**
     * Real tiles for one grid, one row per given product.
     *
     * @param iterable<ProductInterface> $products
     * @param callable $badgeResolver receives the zero-based row index, returns the badge string
     * @return array<int, array{product: ProductInterface, badge: string, label: string, image: string,
     *     name: string, price: string, url: string}>
     */
    private function buildTiles(iterable $products, callable $badgeResolver): array
    {
        $tiles = [];
        foreach (array_values(is_array($products) ? $products : iterator_to_array($products)) as $index => $product) {
            /** @var ProductInterface&\Magento\Catalog\Model\Product $product */
            $tiles[] = [
                'product' => $product,
                'badge' => $badgeResolver($index),
                'label' => $this->firstCategoryName($product),
                'image' => $this->imageHelper->init($product, self::IMAGE_DISPLAY_AREA)->getUrl(),
                'name' => (string) $product->getName(),
                'price' => $this->pricingHelper->currency($product->getFinalPrice(), true, false),
                'url' => $product->getProductUrl(),
            ];
        }

        return $tiles;
    }

    /**
     * The product's first category name, cached per category id (at most 8
     * products per render).
     *
     * @param ProductInterface $product
     * @return string
     */
    private function firstCategoryName(ProductInterface $product): string
    {
        /** @var \Magento\Catalog\Model\Product $product */
        $categoryIds = $product->getCategoryIds();
        $categoryId = $categoryIds[0] ?? null;

        if ($categoryId === null) {
            return '';
        }

        $categoryId = (int) $categoryId;
        if (!isset($this->categoryNameCache[$categoryId])) {
            /** @var Category $category */
            $category = $this->categoryCollectionFactory->create()
                ->addAttributeToSelect('name')
                ->addFieldToFilter('entity_id', $categoryId)
                ->setPageSize(1)
                ->getFirstItem();
            $this->categoryNameCache[$categoryId] = (string) $category->getName();
        }

        return $this->categoryNameCache[$categoryId];
    }
}
