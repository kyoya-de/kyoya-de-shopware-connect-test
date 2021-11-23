<?php

namespace MakairaConnect\Search\Sorting;

use Shopware\Bundle\SearchBundle\Criteria;
use Shopware\Bundle\SearchBundle\SortingInterface;
use Shopware\Bundle\StoreFrontBundle\Struct\ShopContextInterface;

interface SortingParserInterface
{
    /**
     * @param array                $sortResult
     * @param SortingInterface     $sorting
     * @param Criteria             $criteria
     * @param ShopContextInterface $context
     */
    public function parseSorting(
        array &$sortResult,
        SortingInterface $sorting,
        Criteria $criteria,
        ShopContextInterface $context
    ): void;
}
