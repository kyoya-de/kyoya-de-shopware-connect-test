<?php

namespace MakairaConnect\Search\Sorting;

use Shopware\Bundle\SearchBundle\Criteria;
use Shopware\Bundle\SearchBundle\Sorting;
use Shopware\Bundle\SearchBundle\SortingInterface;
use Shopware\Bundle\StoreFrontBundle\Struct\ProductContextInterface;

interface SortingParserInterface
{
    /**
     * @param array                   $sortResult
     * @param SortingInterface        $sorting
     * @param Criteria                $criteria
     * @param ProductContextInterface $context
     */
    public function parseSorting(
        array &$sortResult,
        SortingInterface $sorting,
        Criteria $criteria,
        ProductContextInterface $context
    ): void;
}
