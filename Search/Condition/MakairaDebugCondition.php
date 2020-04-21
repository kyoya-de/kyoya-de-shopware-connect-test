<?php

namespace MakairaConnect\Search\Condition;

use Shopware\Bundle\SearchBundle\ConditionInterface;

class MakairaDebugCondition implements ConditionInterface
{
    public function getName()
    {
        return 'makaira_debug';
    }
}
