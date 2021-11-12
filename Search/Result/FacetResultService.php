<?php

namespace MakairaConnect\Search\Result;

use Makaira\Aggregation;
use Makaira\Result;
use MakairaConnect\Search\Condition\MakairaCondition;
use Shopware\Bundle\SearchBundle\Criteria;
use Shopware\Bundle\SearchBundle\Facet\CategoryFacet;
use Shopware\Bundle\SearchBundle\FacetResult\BooleanFacetResult;
use Shopware\Bundle\SearchBundle\FacetResult\CategoryTreeFacetResultBuilder;
use Shopware\Bundle\SearchBundle\FacetResult\RadioFacetResult;
use Shopware\Bundle\SearchBundle\FacetResult\RangeFacetResult;
use Shopware\Bundle\SearchBundle\FacetResult\TreeFacetResult;
use Shopware\Bundle\SearchBundle\FacetResult\ValueListFacetResult;
use Shopware\Bundle\SearchBundle\FacetResult\ValueListItem;
use Shopware\Bundle\SearchBundle\FacetResultInterface;
use Shopware\Bundle\StoreFrontBundle\Service\CategoryServiceInterface;
use Shopware\Bundle\StoreFrontBundle\Struct\ShopContextInterface;
use function array_flip;
use function array_values;
use function in_array;
use function is_array;
use function reset;
use function strpos;

class FacetResultService implements FacetResultServiceInterface
{
    /**
     * @const string[]
     */
    private const TYPES_CATEGORY_TREE = [
        'categorytree',
        'categorysubtree',
        'fullcategorytree',
        'fullcategorysubtree',
    ];

    /**
     * @var CategoryServiceInterface
     */
    private $categoryService;

    /**
     * @var CategoryTreeFacetResultBuilder
     */
    private $categoryTreeFacetResultBuilder;

    public function __construct(
        CategoryServiceInterface $categoryService,
        CategoryTreeFacetResultBuilder $categoryTreeFacetResultBuilder
    ) {
        $this->categoryService = $categoryService;

        $this->categoryTreeFacetResultBuilder = $categoryTreeFacetResultBuilder;
    }

    /**
     * @param FacetResultInterface[] $facets
     * @param Criteria               $criteria
     * @param Result                 $result
     * @param ShopContextInterface   $context
     */
    public function parseFacets(
        array &$facets,
        Result $result,
        Criteria $criteria,
        ShopContextInterface $context
    ): void {
        foreach ($result->aggregations as $aggregation) {
            $condition      = $criteria->getCondition("makaira_{$aggregation->key}");
            $conditionValue = $condition instanceof MakairaCondition ? $condition->getValue() : null;
            $isActive       = null !== $condition;
            $formFieldName  = "makairaFilter_{$aggregation->key}";

            $facet = null;

            if (0 === strpos($aggregation->type, 'range_slider')) {
                $facet = $this->buildRangeFacet($aggregation, $formFieldName, $isActive, $conditionValue);
            }

            if ('list' === $aggregation->type || 0 === strpos($aggregation->type, 'list_')) {
                $facet = $this->buildListFacet($aggregation, $formFieldName, $isActive);
            }

            if ('script' === $aggregation->type) {
                $facet = $this->buildScriptFacet($aggregation, $formFieldName);
            }

            if (in_array($aggregation->type, self::TYPES_CATEGORY_TREE, true)) {
                $facet = $this->buildCategoryTreeFacet($aggregation, $criteria, $context);
            }

            if (null !== $facet) {
                $facets[$aggregation->key] = $facet;
            }
        }
    }

    /**
     * @param Aggregation $aggregation
     * @param string      $formFieldName
     * @param bool        $active
     * @param array|null  $conditionValue
     *
     * @return RangeFacetResult
     */
    protected function buildRangeFacet(
        Aggregation $aggregation,
        string $formFieldName,
        bool $active,
        ?array $conditionValue
    ): RangeFacetResult {
        return new RangeFacetResult(
            $aggregation->key,
            $active,
            $aggregation->title,
            $aggregation->min,
            $aggregation->max,
            $conditionValue['min'] ?? $aggregation->min,
            $conditionValue['max'] ?? $aggregation->max,
            "{$formFieldName}[min]",
            "{$formFieldName}[max]"
        );
    }

    /**
     * @param Aggregation $aggregation
     * @param string      $formFieldName
     * @param bool        $isActive
     *
     * @return FacetResultInterface
     */
    protected function buildListFacet(Aggregation $aggregation, string $formFieldName, bool $isActive)
    {
        $facetResultClass = RadioFacetResult::class;
        if (0 === strpos($aggregation->type, 'list_multiselect')) {
            $facetResultClass = ValueListFacetResult::class;
        }

        $selectedValues = (array) $aggregation->selectedValues;
        $values         = [];

        foreach ($aggregation->values as $title => $value) {
            $values[] = new ValueListItem(
                $value['key'], $title, in_array($value['key'], $selectedValues, true)
            );
        }

        return new $facetResultClass(
            $aggregation->key, $isActive, $aggregation->title, $values, $formFieldName
        );
    }

    /**
     * @param Aggregation $aggregation
     * @param string      $formFieldName
     *
     * @return BooleanFacetResult
     */
    protected function buildScriptFacet(Aggregation $aggregation, string $formFieldName): BooleanFacetResult
    {
        $selectedValue = (int) (is_array($aggregation->selectedValues) ? reset($aggregation->selectedValues) : 0);

        return new BooleanFacetResult(
            $aggregation->key, $formFieldName, 1 === $selectedValue, $aggregation->title
        );
    }

    /**
     * @param Aggregation          $aggregation
     * @param Criteria             $criteria
     * @param ShopContextInterface $context
     *
     * @return TreeFacetResult|null
     */
    protected function buildCategoryTreeFacet(
        Aggregation $aggregation,
        Criteria $criteria,
        ShopContextInterface $context
    ): ?TreeFacetResult {
        $catIds = [];
        $this->flattenCategoryIds($aggregation->values, $catIds);
        $categories        = $this->categoryService->getList($catIds, $context);
        $currentCategories = $criteria->getBaseCondition('category')->getCategoryIds();

        return $this->categoryTreeFacetResultBuilder->buildFacetResult(
            $categories,
            $catIds,
            reset($currentCategories),
            new CategoryFacet()
        );
    }

    /**
     * @param array $categories
     * @param array $result
     */
    private function flattenCategoryIds(array $categories, array &$result)
    {
        foreach ($categories as $categoryId => $category) {
            $result[] = $categoryId;
            if (isset($category['subtree']) && 0 < count($category['subtree'])) {
                $this->flattenCategoryIds($category['subtree'], $result);
            }
        }
        $result = array_values(array_flip(array_flip($result)));
    }
}
