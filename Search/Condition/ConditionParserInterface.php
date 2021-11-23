<?php

namespace MakairaConnect\Search\Condition;

use Makaira\Query;
use Shopware\Bundle\SearchBundle\ConditionInterface;
use Shopware\Bundle\SearchBundle\Criteria;
use Shopware\Bundle\StoreFrontBundle\Struct\ShopContextInterface;

interface ConditionParserInterface
{
    public function parseCondition(
        Query $query,
        ConditionInterface $condition,
        Criteria $criteria,
        ShopContextInterface $context
    ): void;
}
