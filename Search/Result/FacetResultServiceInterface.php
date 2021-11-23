<?php

namespace MakairaConnect\Search\Result;

use Makaira\Result;
use Shopware\Bundle\SearchBundle\Criteria;
use Shopware\Bundle\StoreFrontBundle\Struct\ShopContextInterface;

interface FacetResultServiceInterface
{
    /**
     * @param array                   $facets
     * @param Result                  $result
     * @param Criteria                $criteria
     * @param ShopContextInterface $context
     */
    public function parseFacets(
        array &$facets,
        Result $result,
        Criteria $criteria,
        ShopContextInterface $context
    ): void;
}
