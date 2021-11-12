<?php

namespace MakairaConnect\Search;

use MakairaConnect\Service\SearchService;
use Shopware\Bundle\SearchBundle\Criteria;
use Shopware\Bundle\SearchBundle\ProductNumberSearchInterface;
use Shopware\Bundle\SearchBundle\ProductNumberSearchResult;
use Shopware\Bundle\StoreFrontBundle\Struct;

class ProductNumberSearch implements ProductNumberSearchInterface
{
    /**
     * @var SearchService
     */
    private $search;

    /**
     * @var ProductNumberSearchInterface
     */
    private $innerService;

    /**
     * ProductSearch constructor.
     *
     * @param SearchService                $searchService
     * @param ProductNumberSearchInterface $innerService
     */
    public function __construct(SearchService $searchService, ProductNumberSearchInterface $innerService)
    {
        $this->search       = $searchService;
        $this->innerService = $innerService;
    }

    /**
     * Creates a product search result for the passed criteria object.
     * The criteria object contains different core conditions and plugin conditions.
     * This conditions has to be handled over the different condition handlers.
     * The search gateway has to implement an event which plugin can be listened to,
     * to add their own handler classes.
     *
     * @param Criteria                    $criteria
     * @param Struct\ShopContextInterface $context
     *
     * @return ProductNumberSearchResult
     */
    public function search(Criteria $criteria, Struct\ShopContextInterface $context)
    {
        $result = $this->search->search($criteria, $context);
        if (false === $result) {
            return $this->innerService->search($criteria, $context);
        }

        [$products, $result, $facets] = $result;

        return new ProductNumberSearchResult($products, $result['product']->total, $facets);
    }

}
