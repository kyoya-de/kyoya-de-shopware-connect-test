<?php

namespace MakairaConnect\Search\Result;

use Makaira\Result;
use Shopware\Bundle\SearchBundle\Criteria;
use Shopware\Bundle\StoreFrontBundle\Struct\ProductContextInterface;

interface FacetResultServiceInterface
{
    /**
     * @param array                   $facets
     * @param Result                  $result
     * @param Criteria                $criteria
     * @param ProductContextInterface $context
     */
    public function parseFacets(
        array &$facets,
        Result $result,
        Criteria $criteria,
        ProductContextInterface $context
    ): void;
}
