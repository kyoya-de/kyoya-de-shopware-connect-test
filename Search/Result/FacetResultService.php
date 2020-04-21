<?php

namespace MakairaConnect\Search\Result;

use Makaira\Aggregation;
use Makaira\Result;
use MakairaConnect\Search\Condition\MakairaCondition;
use Shopware\Bundle\SearchBundle\ConditionInterface;
use Shopware\Bundle\SearchBundle\Criteria;
use Shopware\Bundle\SearchBundle\FacetResult\BooleanFacetResult;
use Shopware\Bundle\SearchBundle\FacetResult\RadioFacetResult;
use Shopware\Bundle\SearchBundle\FacetResult\RangeFacetResult;
use Shopware\Bundle\SearchBundle\FacetResult\ValueListFacetResult;
use Shopware\Bundle\SearchBundle\FacetResult\ValueListItem;
use Shopware\Bundle\SearchBundle\FacetResultInterface;
use Shopware\Bundle\StoreFrontBundle\Struct\ProductContextInterface;
use function in_array;
use function is_array;
use function reset;
use function strpos;

class FacetResultService implements FacetResultServiceInterface
{
    /**
     * @param FacetResultInterface[]  $facets
     * @param Criteria                $criteria
     * @param Result                  $result
     * @param ProductContextInterface $context
     */
    public function parseFacets(
        array &$facets,
        Result $result,
        Criteria $criteria,
        ProductContextInterface $context
    ): void {
        foreach ($result->aggregations as $aggregation) {
            $condition      = $criteria->getCondition("makaira_{$aggregation->key}");
            $conditionValue = $condition instanceof MakairaCondition ? $condition->getValue() : null;
            $isActive       = null !== $condition;
            $formFieldName  = "makairaFilter_{$aggregation->key}";

            $facet = null;

            if (0 === strpos($aggregation->type, 'range_slider')) {
                $facet = $this->buildRangeFacet($aggregation, $formFieldName, $condition, $conditionValue);
            }

            if ('list' === $aggregation->type || 0 === strpos($aggregation->type, 'list_')) {
                $facet = $this->buildListFacet($aggregation, $formFieldName, $isActive);
            }

            if ('script' === $aggregation->type) {
                $facet = $this->buildScriptFacet($aggregation, $formFieldName);
            }

            if (null !== $facet) {
                $facets[$aggregation->key] = $facet;
            }
        }
    }

    /**
     * @param Aggregation        $aggregation
     * @param string             $formFieldName
     * @param ConditionInterface $condition
     * @param int|null           $conditionValue
     *
     * @return RangeFacetResult
     */
    protected function buildRangeFacet(
        $aggregation,
        string $formFieldName,
        $condition,
        $conditionValue
    ): RangeFacetResult {
        return new RangeFacetResult(
            $aggregation->key,
            null !== $condition,
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
     * @return mixed
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

        $facet = new $facetResultClass(
            $aggregation->key, $isActive, $aggregation->title, $values, $formFieldName
        );

        return $facet;
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

        $facet = new BooleanFacetResult(
            $aggregation->key, $formFieldName, 1 === $selectedValue, $aggregation->title
        );

        return $facet;
    }
}
