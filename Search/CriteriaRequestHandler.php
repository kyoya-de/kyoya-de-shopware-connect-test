<?php

namespace MakairaConnect\Search;

use Assert\AssertionFailedException;
use Enlight_Controller_Request_RequestHttp as Request;
use MakairaConnect\Search\Condition\MakairaCondition;
use MakairaConnect\Search\Condition\MakairaDebugCondition;
use MakairaConnect\Search\Facet\MakairaFacet;
use Shopware\Bundle\SearchBundle\Criteria;
use Shopware\Bundle\SearchBundle\CriteriaRequestHandlerInterface;
use Shopware\Bundle\StoreFrontBundle\Gateway\CustomFacetGatewayInterface;
use Shopware\Bundle\StoreFrontBundle\Struct\ShopContextInterface;

class CriteriaRequestHandler implements CriteriaRequestHandlerInterface
{
    /**
     * @var CustomFacetGatewayInterface
     */
    private $gateway;

    /**
     * @var array
     */
    private $config;

    /**
     * CriteriaRequestHandler constructor.
     *
     * @param CustomFacetGatewayInterface $gateway
     * @param array                       $config
     */
    public function __construct(CustomFacetGatewayInterface $gateway, array $config)
    {
        $this->gateway = $gateway;
        $this->config  = $config;
    }

    /**
     * @param Request              $request
     * @param Criteria             $criteria
     * @param ShopContextInterface $context
     *
     * @throws AssertionFailedException
     */
    public function handleRequest(Request $request, Criteria $criteria, ShopContextInterface $context)
    {
        if (!$this->isMakairaActive($criteria)) {
            return;
        }

        $customFacets = $this->gateway->getAllCategoryFacets($context);

        foreach ($customFacets as $customFacet) {
            $facet = $customFacet->getFacet();
            if (!$facet || !$facet instanceof MakairaFacet) {
                continue;
            }

            $criteria->addFacet($facet);

            $this->handleMakairaFacet($request, $criteria, $facet);
        }

        if ($request->has('mak_debug') || $request->headers->has('x-makaira-debug')) {
            $criteria->addCondition(new MakairaDebugCondition());
        }
    }

    /**
     * @param Request      $request
     * @param Criteria     $criteria
     * @param MakairaFacet $facet
     *
     * @throws AssertionFailedException
     */
    private function handleMakairaFacet(Request $request, Criteria $criteria, MakairaFacet $facet): void
    {
        $facetParam = $request->get($facet->getFormFieldName());
        if (null === $facetParam) {
            return;
        }

        $criteria->addCondition(new MakairaCondition($facet->getKey(), $facetParam, $facet->getType()));
    }

    /**
     * @param Criteria $criteria
     *
     * @return bool
     */
    private function isMakairaActive(Criteria $criteria): bool
    {
        $hasSearch       = $criteria->hasBaseCondition('search');
        $hasManufacturer = $criteria->hasBaseCondition('manufacturer');
        $hasCategory     = $criteria->hasBaseCondition('category');

        $isSearch       = $hasSearch;
        $isManufacturer = $hasManufacturer && !$hasSearch;
        $isCategory     = $hasCategory && !$hasSearch && !$hasManufacturer;

        return ($isSearch && $this->config['makaira_search']) ||
            ($isManufacturer && $this->config['makaira_manufacturer']) ||
            ($isCategory && $this->config['makaira_category']);
    }
}
