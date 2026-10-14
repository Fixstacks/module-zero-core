<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Core\ViewModel;

use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Catalog\Model\Product\Visibility as ProductVisibility;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as FulltextCollectionFactory;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Search\Helper\Data as SearchHelper;
use Magento\Search\Model\AutocompleteInterface;
use Magento\Search\Model\QueryFactory;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Data for the header search dropdown (Controller\Search\Suggest).
 * - getSuggestions(): AutocompleteInterface, rendered as HTML.
 *   search_suggestion_enabled is locked to 0 in env.php: the OpenSearch
 *   suggester ranked unrelated terms first ("tank" → "track").
 * - getMatchingProducts(): the results page's addSearchFilter() (search
 *   history alone had nothing for new terms), gated by the min query length.
 */
class SearchSuggest implements ArgumentInterface
{
    /**
     * Kept small: a preview, not the results page.
     */
    private const PRODUCT_LIMIT = 4;

    /**
     * Same image id as MiniCart (`mini_cart_product_thumbnail`) for one compact
     * product-row size site-wide.
     */
    private const IMAGE_DISPLAY_AREA = 'mini_cart_product_thumbnail';

    /**
     * $fulltextCollectionFactory is typed as the real Product\CollectionFactory:
     * the Fulltext CollectionFactory is a virtualType, which di:compile can't
     * reflect. etc/frontend/di.xml injects the virtual instance by name.
     *
     * @param AutocompleteInterface $autocomplete
     * @param SearchHelper $searchHelper
     * @param QueryFactory $queryFactory
     * @param FulltextCollectionFactory $fulltextCollectionFactory
     * @param ImageHelper $imageHelper
     * @param PriceCurrencyInterface $priceCurrency
     * @param ProductVisibility $productVisibility
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        private readonly AutocompleteInterface $autocomplete,
        private readonly SearchHelper $searchHelper,
        private readonly QueryFactory $queryFactory,
        private readonly FulltextCollectionFactory $fulltextCollectionFactory,
        private readonly ImageHelper $imageHelper,
        private readonly PriceCurrencyInterface $priceCurrency,
        private readonly ProductVisibility $productVisibility,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * The query text (the `q` param, same one the data providers and the search
     * field use).
     */
    public function getQueryText(): string
    {
        return (string) $this->queryFactory->get()->getQueryText();
    }

    /**
     * Suggested terms. title/num_results is the shape both possible data providers
     * return, whichever search_suggestion_enabled selects.
     *
     * @return array<int, array{title: string, numResults: int, url: string}>
     */
    public function getSuggestions(): array
    {
        // Same gate as getMatchingProducts(): below min_query_length the dropdown stays empty.
        if ($this->isQueryTooShort()) {
            return [];
        }

        $suggestions = [];
        foreach ($this->autocomplete->getItems() as $item) {
            $title = (string) $item->getTitle();
            if ($title === '') {
                continue;
            }

            // toArray(), not getData('num_results') directly — ItemInterface itself only
            // guarantees getTitle()/toArray(), not a generic getter; the concrete Item class
            // happens to extend DataObject too, but coding against the interface's own real
            // contract is what actually stays correct if a custom data provider ever swaps in
            // a different ItemInterface implementation.
            $data = $item->toArray();
            $suggestions[] = [
                'title' => $title,
                'numResults' => (int) ($data['num_results'] ?? 0),
                'url' => $this->searchHelper->getResultUrl($title),
            ];
        }

        return $suggestions;
    }

    /**
     * "View all results" URL: the search form's own submit target.
     */
    public function getAllResultsUrl(): string
    {
        return $this->searchHelper->getResultUrl($this->getQueryText());
    }

    /**
     * Below catalog/search/min_query_length the template renders nothing: the
     * results page would redirect, so "View all results" would mislead.
     */
    public function isQueryTooShort(): bool
    {
        return $this->searchHelper->isMinQueryLength();
    }

    /**
     * Products matching the query (see the class docblock).
     *
     * @return array<int, array{
     *     name: string,
     *     url: string,
     *     image: string,
     *     price: string,
     * }>
     */
    public function getMatchingProducts(): array
    {
        if ($this->searchHelper->isMinQueryLength()) {
            return [];
        }

        // The virtualType's runtime type is Fulltext\Collection.
        // `quick_search_container`: the default container doesn't filter at all.
        // Store/visibility/price as Layer\Search\CollectionFilter does (else
        // no results, or 0.00 prices). `thumbnail`: IMAGE_DISPLAY_AREA's type.
        // The price info's final price is already in the display currency
        // (getFinalPrice() isn't, for simple products), so it's only formatted.
        $collection = $this->fulltextCollectionFactory->create([
            'searchRequestName' => 'quick_search_container',
        ]);
        $collection->setStore($this->storeManager->getStore());
        $collection->addStoreFilter();
        $collection->addMinimalPrice();
        $collection->addFinalPrice();
        $collection->addTaxPercents();
        $collection->setVisibility($this->productVisibility->getVisibleInSearchIds());
        $collection->addSearchFilter($this->getQueryText());
        $collection->addAttributeToSelect(['name', 'thumbnail']);
        $collection->setPageSize(self::PRODUCT_LIMIT);

        $products = [];
        foreach ($collection as $product) {
            $products[] = [
                'name' => (string) $product->getName(),
                'url' => (string) $product->getProductUrl(),
                'image' => $this->imageHelper->init($product, self::IMAGE_DISPLAY_AREA)->getUrl(),
                'price' => $this->priceCurrency->format(
                    $product->getPriceInfo()->getPrice('final_price')->getAmount()->getValue(),
                    false
                ),
            ];
        }

        return $products;
    }
}
