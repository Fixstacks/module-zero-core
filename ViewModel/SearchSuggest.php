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
 * Data for the header search box's live dropdown (see Controller\Search\Suggest).
 *
 * - getSuggestions(): AutocompleteInterface directly — stock's
 *   Ajax\Suggest controller only JSON-wraps it, and this renders HTML.
 *   `catalog/search/search_suggestion_enabled` is locked to 0 in env.php:
 *   the OpenSearch suggester ranked unrelated logged terms above exact matches
 *   ("tank" → "track"); 0 is a plain prefix match on search_query.
 * - getMatchingProducts(): search_query is only past search history, so a term
 *   nobody searched yet got no suggestions at all. This uses the same
 *   Fulltext\Collection::addSearchFilter() the search results page uses
 *   (OpenSearch relevance), gated by the min query length like stock.
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

        // @var \Magento\CatalogSearch\Model\ResourceModel\Fulltext\Collection $collection
        // (the virtualType's runtime type, see the constructor docblock).
        //
        // `quick_search_container` is required: the default `catalog_view_container`
        // makes addSearchFilter() not constrain anything. It's the request name the
        // /catalogsearch/result/ page uses.
        //
        // The store/visibility/price calls mirror Layer\Search\CollectionFilter::filter():
        // without store/visibility the search returned nothing, without prices
        // getFinalPrice() was 0.00.
        //
        // `thumbnail`, not `small_image`: IMAGE_DISPLAY_AREA is declared
        // type="thumbnail", and the wrong attribute falls back to the placeholder.
        //
        // The price is the price info's final price, already in the display
        // currency, so it is only formatted: getFinalPrice() is in the base
        // currency for a simple product but already converted for a
        // configurable, and converting it again showed 40x the price in the
        // hryvnia store view.
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
