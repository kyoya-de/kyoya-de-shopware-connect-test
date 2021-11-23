<?php

namespace MakairaConnect\Search\Condition;

use Makaira\Query;
use Shopware\Bundle\SearchBundle\ConditionInterface;
use Shopware\Bundle\SearchBundle\Criteria;
use Shopware\Bundle\StoreFrontBundle\Struct\ShopContextInterface;
use function explode;
use function strpos;

class ConditionParser implements ConditionParserInterface
{
    /**
     * @param Query                   $query
     * @param ConditionInterface      $condition
     * @param Criteria                $criteria
     * @param ShopContextInterface $context
     */
    public function parseCondition(
        Query $query,
        ConditionInterface $condition,
        Criteria $criteria,
        ShopContextInterface $context
    ): void {
        if (!$condition instanceof MakairaCondition) {
            return;
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
}
