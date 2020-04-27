<?php

namespace MakairaConnect\Search\FacetHandler;

use MakairaConnect\Search\Facet\MakairaFacet;
use Shopware\Bundle\SearchBundle\Criteria;
use Shopware\Bundle\SearchBundle\FacetInterface;
use Shopware\Bundle\SearchBundle\FacetResultInterface;
use Shopware\Bundle\SearchBundleDBAL\PartialFacetHandlerInterface;
use Shopware\Bundle\StoreFrontBundle\Struct\ShopContextInterface;

class DBALFacetHandler implements PartialFacetHandlerInterface
{
    /**
     * @return FacetResultInterface|FacetResultInterface[]|null
     */
    public function generatePartialFacet(
        FacetInterface $facet,
        Criteria $reverted,
        Criteria $criteria,
        ShopContextInterface $context
    ) {
        // This is only used for DB filtering. An empty array disables the filters.
        return [];
    }

    /**
     * Checks if the provided facet can be handled by this class.
     *
     * @return bool
     */
    public function supportsFacet(FacetInterface $facet)
    {
        return $facet instanceof MakairaFacet;
    }

}
