<?php

namespace MakairaConnect\Service;

use Makaira\Constraints;
use Makaira\Query;
use Makaira\Result;
use Makaira\ResultItem;
use MakairaConnect\Client\ApiInterface;
use MakairaConnect\Search\Condition\MakairaCondition;
use Shopware\Bundle\SearchBundle\Condition\CategoryCondition;
use Shopware\Bundle\SearchBundle\Criteria;
use Shopware\Bundle\SearchBundle\FacetResult\BooleanFacetResult;
use Shopware\Bundle\SearchBundle\FacetResult\RadioFacetResult;
use Shopware\Bundle\SearchBundle\FacetResult\RangeFacetResult;
use Shopware\Bundle\SearchBundle\FacetResult\ValueListFacetResult;
use Shopware\Bundle\SearchBundle\FacetResult\ValueListItem;
use Shopware\Bundle\SearchBundle\ProductSearchInterface;
use Shopware\Bundle\SearchBundle\ProductSearchResult;
use Shopware\Bundle\SearchBundle\Sorting;
use Shopware\Bundle\StoreFrontBundle\Service\ListProductServiceInterface;
use Shopware\Bundle\StoreFrontBundle\Struct\ProductContextInterface;
use function array_map;
use function explode;
use function in_array;
use function reset;
use function strpos;

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
            $manufacturerIds                               = $ManufacturerCondition->getManufacturerIds();
            $query->constraints[Constraints::MANUFACTURER] = reset($manufacturerIds);
        }

        foreach ($criteria->getConditions() as $condition) {
            if (!$condition instanceof MakairaCondition) {
                continue;
            }

            if (0 === strpos($condition->getType(), 'range_slider')) {
                $minKey = "{$condition->getField()}_from";
                $maxKey = "{$condition->getField()}_to";

                if ('range_slider_price' === $condition->getType()) {
                    $minKey .= '_price';
                    $maxKey .= '_price';
                }

                if (isset($condition->getValue()['min'])) {
                    $query->aggregations[$minKey] = $condition->getValue()['min'];
                }

                if (isset($condition->getValue()['max'])) {
                    $query->aggregations[$maxKey] = $condition->getValue()['max'];
                }
            } elseif (0 === strpos($condition->getType(), 'list_multiselect')) {
                $query->aggregations[$condition->getField()] = explode('|', $condition->getValue());
            } else {
                $query->aggregations[$condition->getField()] = [$condition->getValue()];
            }
        }

        $result = $this->api->search($query, 'true');

        $numbers  = array_map(
            static function (ResultItem $item) {
                return $item->fields['ean'];
            },
            $result['product']->items
        );
        $products = $this->productService->getList($numbers, $context);

        $facets = $this->parseFacets($criteria, $result['product']);

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
        $isManufacturer = $hasManufacturer && !$hasSearch;
        $isCategory     = $hasCategory && !$hasSearch && !$hasManufacturer;

        return ($isSearch && $this->config['makaira_search']) ||
            ($isManufacturer && $this->config['makaira_manufacturer']) ||
            ($isCategory && $this->config['makaira_category']);
    }

    private function parseFacets(Criteria $criteria, Result $result)
    {
        $facets = [];

        foreach ($result->aggregations as $aggregation) {
            $condition = $criteria->getCondition("makaira_{$aggregation->key}");
            $conditionValue = $condition instanceof MakairaCondition ? $condition->getValue() : null;
            $isActive = null !== $condition;

            $facet = null;

            if (0 === strpos($aggregation->type, 'range_slider')) {
                $facet = new RangeFacetResult(
                    $aggregation->key,
                    null !== $condition,
                    $aggregation->title,
                    $aggregation->min,
                    $aggregation->max,
                    $conditionValue['min'] ?? $aggregation->min,
                    $conditionValue['max'] ?? $aggregation->max,
                    "makairaFilter_{$aggregation->key}[min]",
                    "makairaFilter_{$aggregation->key}[max]"
                );
            }

            if (0 === strpos($aggregation->type, 'list_multiselect')) {
                $selectedValues = (array) $aggregation->selectedValues;
                $values = [];

                foreach ($aggregation->values as $title => $value) {
                    $values[] = new ValueListItem(
                        $value['key'],
                        $title,
                        in_array($value['key'], $selectedValues, true)
                    );
                }

                $facet = new ValueListFacetResult(
                    $aggregation->key,
                    $isActive,
                    $aggregation->title,
                    $values,
                    "makairaFilter_{$aggregation->key}"
                );
            } else if ('list' === $aggregation->type || 0 === strpos($aggregation->type, 'list_')) {
                $values = [];

                foreach ($aggregation->values as $title => $value) {
                    $values[] = new ValueListItem(
                        $value['key'],
                        $title,
                        in_array($value['key'], $aggregation->selectedValues, true)
                    );
                }

                $facet = new RadioFacetResult(
                    $aggregation->key,
                    $isActive,
                    $aggregation->title,
                    $values,
                    "makairaFilter_{$aggregation->key}"
                );
            }

            if ('script' === $aggregation->type) {
                $selectedValue = (int) (is_array($aggregation->selectedValues) ?
                    reset($aggregation->selectedValues) :
                    0);

                $facet = new BooleanFacetResult(
                    $aggregation->key,
                    "makairaFilter_{$aggregation->key}",
                    1 === $selectedValue,
                    $aggregation->title
                );
            }

            if (null !== $facet) {
                $facets[$aggregation->key] = $facet;
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
