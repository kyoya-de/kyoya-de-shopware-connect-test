<?php

namespace MakairaConnect\Search;

use MakairaConnect\Service\SearchService;
use Shopware\Bundle\SearchBundle\Criteria;
use Shopware\Bundle\SearchBundle\ProductSearchInterface;
use Shopware\Bundle\SearchBundle\ProductSearchResult;
use Shopware\Bundle\StoreFrontBundle\Struct\ProductContextInterface;

class ProductSearch implements ProductSearchInterface
{
    /**
     * @var SearchService
     */
    private $search;

    /**
     * @var ProductSearchInterface
     */
    private $innerService;

    /**
     * ProductSearch constructor.
     *
     * @param SearchService          $searchService
     * @param ProductSearchInterface $innerService
     */
    public function __construct(SearchService $searchService, ProductSearchInterface $innerService)
    {
        $this->search       = $searchService;
        $this->innerService = $innerService;
    }

    /**
     * Creates a search request on the internal search gateway to
     * get the product result for the passed criteria object.
     *
     * @param Criteria                $criteria
     * @param ProductContextInterface $context
     *
     * @return ProductSearchResult
     */
    public function search(Criteria $criteria, ProductContextInterface $context)
    {
        $result = $this->search->search($criteria, $context);
        if (false === $result) {
            return $this->innerService->search($criteria, $context);
        }

        [$products, $result, $facets] = $result;

        return new ProductSearchResult($products, $result['product']->total, $facets, $criteria, $context);
    }

}
