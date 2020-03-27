<?php

namespace MakairaConnect\Service;

use Makaira\Constraints;
use Makaira\Query;
use Makaira\Result;
use Makaira\ResultItem;
use MakairaConnect\Client\ApiInterface;
use Shopware\Bundle\SearchBundle\Condition\CategoryCondition;
use Shopware\Bundle\SearchBundle\Criteria;
use Shopware\Bundle\SearchBundle\FacetResult\RangeFacetResult;
use Shopware\Bundle\SearchBundle\ProductSearchInterface;
use Shopware\Bundle\SearchBundle\ProductSearchResult;
use Shopware\Bundle\SearchBundle\Sorting;
use Shopware\Bundle\StoreFrontBundle\Service\ListProductServiceInterface;
use Shopware\Bundle\StoreFrontBundle\Struct\ProductContextInterface;
use function reset;

class SearchService implements ProductSearchInterface
{
    private $config;

    private $api;

    private $innerService;

    private $productService;

    /**
     * SearchService constructor.
     *
     * @param array                       $config
     * @param ProductSearchInterface      $innerService
     * @param ApiInterface                $makairaApi
     * @param ListProductServiceInterface $productService
     */
    public function __construct(
        array $config,
        ProductSearchInterface $innerService,
        ApiInterface $makairaApi,
        ListProductServiceInterface $productService
    ) {
        $this->config         = $config;
        $this->innerService   = $innerService;
        $this->api            = $makairaApi;
        $this->productService = $productService;
    }

    /**
     * @param Criteria                $criteria
     * @param ProductContextInterface $context
     *
     * @return ProductSearchResult
     */
    public function search(Criteria $criteria, ProductContextInterface $context)
    {
        if (!$this->isMakairaActive($criteria)) {
            return $this->innerService->search($criteria, $context);
        }

        $query              = new Query();
        $query->fields      = ['id', 'ean', 'makaira-product'];
        $query->constraints = [
            Constraints::SHOP     => $context->getShop()->getId(),
            Constraints::LANGUAGE => $context->getShop()->getLocale()->getLocale(),
        ];
        $query->offset      = $criteria->getOffset();
        $query->count       = $criteria->getLimit();
        $query->isSearch    = $criteria->hasBaseCondition('search');
        $query->sorting     = $this->mapSorting($criteria);

        if ($criteria->hasBaseCondition('search')) {
            $searchCondition     = $criteria->getBaseCondition('search');
            $query->searchPhrase = $searchCondition->getTerm();
        }

        if ($criteria->hasBaseCondition('category')) {
            $categoryCondition                         = $criteria->getBaseCondition('category');
            $query->constraints[Constraints::CATEGORY] = $categoryCondition->getCategoryIds();
        }

        if ($criteria->hasBaseCondition('manufacturer')) {
            $ManufacturerCondition                         = $criteria->getBaseCondition('manufacturer');
            $query->constraints[Constraints::MANUFACTURER] = $ManufacturerCondition->getManufacturerIds();
        }

        $result = $this->api->search($query, 'true');

        $numbers  = array_map(
            static function (ResultItem $item) {
                return $item->fields['ean'];
            },
            $result['product']->items
        );
        $products = $this->productService->getList($numbers, $context);

        $facets = $this->parseFacets($result['product']);

        return new ProductSearchResult($products, $result['product']->total, $facets, $criteria, $context);
    }

    /**
     * @param Criteria $criteria
     *
     * @return bool
     */
    private function isMakairaActive(Criteria $criteria): bool
    {
        $hasSearch       = $criteria->hasBaseCondition('search');
        $hasManufacturer = $criteria->hasBaseCondition('manufacturer');
        $hasCategory     = $criteria->hasBaseCondition('category');

        $isSearch       = $hasSearch;
        $isManufacturer = $hasManufacturer && !$hasCategory && !$hasSearch;
        $isCategory     = $hasCategory && !$hasSearch && !$hasManufacturer;

        return ($isSearch && $this->config['makaira_search']) ||
            ($isManufacturer && $this->config['makaira_manufacturer']) ||
            ($isCategory && $this->config['makaira_category']);
    }

    private function parseFacets(Result $result)
    {
        $facets = [];
        foreach ($result->aggregations as $aggregation) {
            if (0 === strpos($aggregation->type, 'range_slider_price')) {
                $facets[$aggregation->key] = new RangeFacetResult(
                    $aggregation->key,
                    true,
                    $aggregation->title,
                    $aggregation->min,
                    $aggregation->max,
                    null !== $aggregation->selectedValues ? $aggregation->selectedValues[0] : $aggregation->min,
                    null !== $aggregation->selectedValues ? $aggregation->selectedValues[1] : $aggregation->max,
                    "makairaFilter[{$aggregation->key}][min]",
                    "makairaFilter[{$aggregation->key}][max]"
                );
            }
        }

        return $facets;
    }

    /**
     * @param Criteria $criteria
     *
     * @return array
     */
    private function mapSorting(Criteria $criteria): array
    {
        $sort = [];

        foreach ($criteria->getSortings() as $sorting) {
            $name = false;

            if ($sorting instanceof Sorting\PopularitySorting) {
                $name = 'popularity';
            }

            if ($sorting instanceof Sorting\ManualSorting && $criteria->hasBaseCondition('category')) {
                /** @var CategoryCondition $categoryCondition */
                $categoryCondition = $criteria->getBaseCondition('category');
                $categoryIds       = $categoryCondition->getCategoryIds();
                $categoryId        = reset($categoryIds);

                $name = "catSort.cat_{$categoryId}";
            }

            if ($sorting instanceof Sorting\PriceSorting) {
                $name = 'price';
            }

            if ($sorting instanceof Sorting\ProductNameSorting) {
                $name = 'title';
            }

            if ($sorting instanceof Sorting\ProductNumberSorting) {
                $name = 'ean';
            }

            if ($sorting instanceof Sorting\ProductStockSorting) {
                $name = 'stock';
            }

            if ($sorting instanceof Sorting\ReleaseDateSorting) {
                $name = 'releaseDate';
            }

            if (false !== $name) {
                $sort[$name] = $sorting->getDirection();
            }
        }

        return $sort;
    }
}
