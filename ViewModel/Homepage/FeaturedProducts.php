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
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Catalog\Model\ResourceModel\Product\Collection as ProductCollection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Framework\Pricing\Helper\Data as PricingHelper;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\StoreManagerInterface;
use Zero\Core\Model\Homepage\Settings;
use Zero\Core\Model\Pricing\CachedPrices;

/**
 * Products for the homepage's "The shortlist" and "New, in orbit", plus the
 * promotional sections' images and links, per Model\Homepage\Settings.
 * - Shortlist: one product per department (a flat sort gave four alike);
 *   "Featured", since bestseller data is often empty.
 * - New arrivals: created_at DESC, minus the excluded categories.
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

    /** @var array<string, string> category ids => the most specific one's name, avoids re-loading per product */
    private array $categoryNameCache = [];

    /** @var array<int, array>|null the shortlist's tiles, once built */
    private ?array $shortlist = null;

    /** @var array<int, array>|null the new arrivals' tiles, once built */
    private ?array $newArrivals = null;

    /**
     * @param ProductCollectionFactory $productCollectionFactory
     * @param CategoryCollectionFactory $categoryCollectionFactory
     * @param ImageHelper $imageHelper
     * @param PricingHelper $pricingHelper
     * @param ImageFactory $productImageFactory
     * @param StoreManagerInterface $storeManager
     * @param Visibility $visibility
     * @param CachedPrices $prices
     * @param Settings $settings
     */
    public function __construct(
        private readonly ProductCollectionFactory $productCollectionFactory,
        private readonly CategoryCollectionFactory $categoryCollectionFactory,
        private readonly ImageHelper $imageHelper,
        private readonly PricingHelper $pricingHelper,
        private readonly ImageFactory $productImageFactory,
        private readonly StoreManagerInterface $storeManager,
        private readonly Visibility $visibility,
        private readonly CachedPrices $prices,
        private readonly Settings $settings
    ) {
    }

    /**
     * Whether Zero's own promotional sections show (off unless switched on).
     */
    public function showPromo(): bool
    {
        return $this->settings->showPromo();
    }

    /**
     * Where the promotional sections' "explore" links go: the first department,
     * or the homepage when there is none.
     */
    public function getPrimaryDepartmentUrl(): string
    {
        $departments = $this->settings->departments();

        return $departments ? (string) $departments[0]->getUrl() : $this->storeManager->getStore()->getBaseUrl();
    }

    /**
     * The new arrivals' "View all" link, or null for none.
     */
    public function getNewArrivalsUrl(): ?string
    {
        return $this->settings->newArrivalsUrl();
    }

    /**
     * Resized `src` fallback for the "Made to move." tile.
     */
    public function getSystemSpreadImageUrl(): string
    {
        $widths = self::SRCSET_WIDTHS;
        $path = $this->promoImagePath('spread', 0);

        return $path === '' ? '' : $this->resizedImageUrl($path, (int) end($widths));
    }

    /**
     * Real, resized `srcset` candidates for the "Made to move." tile.
     */
    public function getSystemSpreadImageSrcset(): string
    {
        $path = $this->promoImagePath('spread', 0);

        return $path === '' ? '' : $this->buildSrcset($path);
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
        $path = $this->promoImagePath('quote', 1);

        return $path === '' ? '' : $this->resizedImageUrl($path, (int) end($widths));
    }

    /**
     * Real, resized `srcset` candidates for the editorial-quote image.
     */
    public function getEditorialQuoteImageSrcset(): string
    {
        $path = $this->promoImagePath('quote', 1);

        return $path === '' ? '' : $this->buildSrcset($path);
    }

    /**
     * Resized `src` fallback for a theme's third editorial picture (Zero/usa's
     * second journal card); the parent theme doesn't show it.
     */
    public function getJournalImageUrl(): string
    {
        $widths = self::SRCSET_WIDTHS;
        $path = $this->promoImagePath('journal', 2);

        return $path === '' ? '' : $this->resizedImageUrl($path, (int) end($widths));
    }

    /**
     * Real, resized `srcset` candidates for the journal picture.
     */
    public function getJournalImageSrcset(): string
    {
        $path = $this->promoImagePath('journal', 2);

        return $path === '' ? '' : $this->buildSrcset($path);
    }

    /**
     * A promotional section's image: the configured catalog image path, or else
     * a featured product's image (the shortlist's, then the new arrivals'),
     * $index picking which one so the two sections differ. '' when there is none.
     */
    private function promoImagePath(string $which, int $index): string
    {
        $path = $this->settings->promoImage($which);
        if ($path !== '') {
            return $path;
        }
        $images = [];
        foreach (array_merge($this->getShortlist(), $this->getNewArrivals()) as $tile) {
            $image = (string) $tile['product']->getData('small_image');
            if ($image !== '' && $image !== 'no_selection') {
                $images[] = $image;
            }
        }

        return $images[$index] ?? $images[0] ?? '';
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
        if ($this->shortlist !== null) {
            return $this->shortlist;
        }
        $products = [];
        foreach ($this->settings->departments() as $department) {
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

        return $this->shortlist = $this->buildTiles($products, static fn (int $index): string => __('Featured')->render());
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
        if ($this->newArrivals !== null) {
            return $this->newArrivals;
        }
        $collection = $this->baseCollection()
            ->setOrder('created_at', ProductCollection::SORT_ORDER_DESC)
            ->setPageSize(self::SLIDER_SIZE);

        return $this->newArrivals = $this->buildTiles(
            $collection->getItems(),
            static fn (int $index): string => sprintf('%02d', $index + 1)
        );
    }

    /**
     * Shared base: enabled, visible, simple/configurable, minus the categories
     * set to stay out of the new arrivals (Stores > Configuration > Zero > Homepage).
     *
     * The type filter also keeps out virtual/downloadable e2e fixtures from another
     * project on this install (newer created_at, no photos) without a SKU blocklist.
     *
     * @return ProductCollection
     */
    private function baseCollection(): ProductCollection
    {
        $collection = $this->productTypeCollection();
        $excluded = $this->settings->newArrivalsExcluded();
        if ($excluded) {
            $collection->addCategoriesFilter(['nin' => $excluded]);
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
        // The current store's website only: the other websites (Beautiful Ukraine,
        // Kids Karamel) have their own, newer catalogs.
        // Enabled and visible-in-catalog through the category product index (as
        // core's product lists and "New Products" widget do), not EAV filters on
        // status and visibility: those scanned the whole catalog with a filesort,
        // 55-120 ms per query, 4-5 queries per homepage render (2,000 products).
        return $this->productCollectionFactory->create()
            ->addStoreFilter()
            ->setVisibility($this->visibility->getVisibleInCatalogIds())
            // The special price with its dates: CachedPrices computes the final price from
            // these products and keeps it for the badges and every other list (a tile without
            // them cached the regular price as final: no Sale badge anywhere for a day).
            ->addAttributeToSelect(['name', 'price', 'special_price', 'special_from_date', 'special_to_date', 'small_image'])
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
                // The price box's amount from the block cache: a configurable's
                // own final price is worked out from its variants, cold, ~10 ms.
                'price' => $this->pricingHelper->currency($this->prices->get($product)['final'], true, false),
                'url' => $product->getProductUrl(),
            ];
        }

        return $tiles;
    }

    /**
     * The name of the product's most specific category, cached per set of ids (at most 8
     * products per render).
     *
     * @param ProductInterface $product
     * @return string
     */
    private function firstCategoryName(ProductInterface $product): string
    {
        /** @var \Magento\Catalog\Model\Product $product */
        $categoryIds = array_map('intval', $product->getCategoryIds());
        if (!$categoryIds) {
            return '';
        }
        $key = implode(',', $categoryIds);
        if (!isset($this->categoryNameCache[$key])) {
            // The most specific one ("Jackets"), not the first by id: that is often
            // the store's root or a catch-all such as "What's New".
            $category = $this->categoryCollectionFactory->create()
                ->addAttributeToSelect('name')
                ->addFieldToFilter('entity_id', ['in' => $categoryIds])
                ->addFieldToFilter('level', ['gt' => 1])
                ->setOrder('level', 'DESC')
                ->setOrder('entity_id', 'ASC')
                ->setPageSize(1)
                ->getFirstItem();
            $this->categoryNameCache[$key] = (string) $category->getName();
        }

        return $this->categoryNameCache[$key];
    }
}
