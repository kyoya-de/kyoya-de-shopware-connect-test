<?php

namespace MakairaConnect\Service;

use Doctrine\ORM\EntityManagerInterface;
use Makaira\Constraints;
use Makaira\Query;
use Makaira\Result;
use Makaira\ResultItem;
use MakairaConnect\Client\ApiInterface;
use MakairaConnect\Search\Condition\ConditionParserInterface;
use MakairaConnect\Search\Result\FacetResultServiceInterface;
use MakairaConnect\Search\Sorting\SortingParserInterface;
use Psr\Log\LoggerInterface;
use Shopware\Bundle\SearchBundle\Criteria;
use Shopware\Bundle\StoreFrontBundle\Service\ListProductServiceInterface;
use Shopware\Bundle\StoreFrontBundle\Struct\ShopContextInterface;
use Shopware\Components\Model\QueryBuilder;
use Shopware\Models\Category\Category;
use Throwable;
use Traversable;
use function array_map;
use function get_class;
use function reset;

class SearchService
{
    /**
     * @var array
     */
    private $config;

    /**
     * @var ApiInterface
     */
    private $api;

    /**
     * @var ListProductServiceInterface
     */
    private $productService;

    /**
     * @var EntityManagerInterface
     */
    private $em;

    /**
     * @var FacetResultServiceInterface[]
     */
    private $facetResultServices;

    /**
     * @var ConditionParserInterface[]
     */
    private $conditionParser;

    /**
     * @var SortingParserInterface[]
     */
    private $sortingParser;
    
    /**
     * @var array
     */
    private $completeResult = [];

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * SearchService constructor.
     *
     * @param array                       $config
     * @param ApiInterface                $makairaApi
     * @param ListProductServiceInterface $productService
     * @param EntityManagerInterface      $entityManager
     * @param Traversable                 $facetResultServices
     * @param Traversable                 $conditionParser
     * @param Traversable                 $sortingParser
     * @param LoggerInterface             $logger
     */
    public function __construct(
        array $config,
        ApiInterface $makairaApi,
        ListProductServiceInterface $productService,
        EntityManagerInterface $entityManager,
        Traversable $facetResultServices,
        Traversable $conditionParser,
        Traversable $sortingParser,
        LoggerInterface $logger
    ) {
        $this->config              = $config;
        $this->api                 = $makairaApi;
        $this->productService      = $productService;
        $this->facetResultServices = $facetResultServices;
        $this->conditionParser     = $conditionParser;
        $this->sortingParser       = $sortingParser;
        $this->em                  = $entityManager;
        $this->logger              = $logger;
    }

    /**
     * @param Criteria             $criteria
     * @param ShopContextInterface $context
     *
     * @return array|false
     */
    public function search(Criteria $criteria, ShopContextInterface $context)
    {
        if (!$this->isMakairaActive($criteria)) {
            return false;
        }

        try {
            return $this->doSearch($context, $criteria);
        } catch (Throwable $t) {
            do {
                $this->logger->error(
                    "Makaira search failed: {$t->getMessage()}",
                    [
                        'class'   => get_class($t),
                        'message' => $t->getMessage(),
                        'code'    => $t->getCode(),
                        'file'    => $t->getFile(),
                        'line'    => $t->getLine(),
                        'trace'   => $t->getTraceAsString(),
                    ]
                );
                $t = $t->getPrevious();
            } while (null !== $t);
        }

        return false;
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

    /**
     * @param Criteria                $criteria
     * @param ShopContextInterface $context
     *
     * @return array
     */
    private function mapSorting(Criteria $criteria, ShopContextInterface $context): array
    {
        $sort = [];

        foreach ($criteria->getSortings() as $sorting) {
            foreach ($this->sortingParser as $sortingParser) {
                $sortingParser->parseSorting($sort, $sorting, $criteria, $context);
            }
        }

        return $sort;
    }

    /**
     * @param Criteria                $criteria
     * @param Query                   $query
     * @param ShopContextInterface $context
     */
    protected function mapConditions(Criteria $criteria, Query $query, ShopContextInterface $context): void
    {
        foreach ($criteria->getConditions() as $condition) {
            foreach ($this->conditionParser as $conditionParser) {
                $conditionParser->parseCondition($query, $condition, $criteria, $context);
            }
        }
    }

    /**
     * @param Result                  $product
     * @param Criteria                $criteria
     * @param ShopContextInterface $context
     *
     * @return array
     */
    protected function mapFacets(Result $product, Criteria $criteria, ShopContextInterface $context): array
    {
        $facets = [];

        foreach ($this->facetResultServices as $facetResultService) {
            $facetResultService->parseFacets($facets, $product, $criteria, $context);
        }

        return $facets;
    }
    
    /**
     * @return array
     */
    public function getCompleteResult()
    {
        return $this->completeResult;
    }
    
    /**
     * @return array
     */
    protected function prepareManufacturerItem($doc)
    {
        if (empty($doc->fields['manufacturer_title'])) {
            return [];
        }

        $item['name']   = $doc->fields['manufacturer_title'];
        $item['id']     = $doc->id;

        return $item;
    }
    
    /**
     * @return array
     */
    protected function prepareCategoryItem($doc)
    {
        if (empty($doc->fields['category_title'])) {
            return [];
        }
      
        $item['name']   = $doc->fields['category_title'];
        $item['id']     = $doc->id;

        return $item;
    }
    
    /**
     * @return array
     */
    protected function prepareLinkItem($doc)
    {
        if (empty($doc->fields['title'])) {
            return [];
        }

        $item['name'] = $doc->fields['title'];
        $item['link'] = $doc->fields['url'];

        return $item;
    }

    /**
     * @return array
     */
    protected function getSubCategoryIds($categoryId): array
    {
        $repo = $this->em->getRepository(Category::class);

        /** @var QueryBuilder $qb */
        $qb        = $repo->createQueryBuilder('c');
        $queryPath = $qb->select('c.path')
                        ->where($qb->expr()->eq('c.id', ':id'))
                        ->setParameter('id', $categoryId)->getQuery();

        $path = $queryPath->getSingleScalarResult();

        $qb    = $repo->createQueryBuilder('c');
        $query = $qb->select('c.id')
                    ->where($qb->expr()->like('c.path', ':path'))
                    ->setParameter('path', str_replace('||', '|', "%|{$categoryId}|{$path}|"))
                    ->getQuery();

        return array_map(
            static function ($id) {
                return (int) $id;
            },
            array_column($query->getScalarResult(), 'id')
        );
    }

    /**
     * @param ShopContextInterface $context
     * @param Criteria             $criteria
     *
     * @return array
     */
    private function doSearch(ShopContextInterface $context, Criteria $criteria): array
    {
        $query              = new Query();
        $query->fields      = ['id', 'ean', 'makaira-product'];
        $query->constraints = [
            Constraints::SHOP     => $context->getShop()->getId(),
            Constraints::LANGUAGE => $context->getShop()->getLocale()->getLocale(),
        ];
        $query->offset      = $criteria->getOffset();
        $query->count       = $criteria->getLimit();
        $query->isSearch    = $criteria->hasBaseCondition('search');
        $query->sorting     = $this->mapSorting($criteria, $context);

        if ($criteria->hasBaseCondition('search')) {
            $searchCondition     = $criteria->getBaseCondition('search');
            $query->searchPhrase = $searchCondition->getTerm();
        }

        if ($criteria->hasBaseCondition('category')) {
            $categoryCondition = $criteria->getBaseCondition('category');
            $categoryIds       = $categoryCondition->getCategoryIds();

            if ($this->config['makaira_subcategory_products']) {
                $categoryId    = reset($categoryIds);
                $categoryIds   = $this->getSubCategoryIds($categoryId);
                $categoryIds[] = $categoryId;
            }

            $query->constraints[Constraints::CATEGORY] = array_map(
                static function ($categoryId) {
                    return (string) $categoryId;
                },
                $categoryIds
            );
        }

        if ($criteria->hasBaseCondition('manufacturer')) {
            $ManufacturerCondition                         = $criteria->getBaseCondition('manufacturer');
            $manufacturerIds                               = $ManufacturerCondition->getManufacturerIds();
            $query->constraints[Constraints::MANUFACTURER] = reset($manufacturerIds);
        }

        $this->mapConditions($criteria, $query, $context);

        $result = $this->api->search($query, $criteria->hasCondition('makaira_debug') ? 'true' : '');

        $this->completeResult = $result;

        // get manufacturer results
        $manufacturers = [];
        if ($this->completeResult['manufacturer']) {
            foreach ($this->completeResult['manufacturer']->items as $document) {
                $manufacturers[] = $this->prepareManufacturerItem($document);
            }
        }
        // filter out empty values
        $manufacturers                        = array_filter($manufacturers);
        $this->completeResult['manufacturer'] = $manufacturers;

        // get category results
        $categories = [];
        if ($result['category']) {
            foreach ($result['category']->items as $document) {
                $categories[] = $this->prepareCategoryItem($document);
            }
        }
        // filter out empty values
        $categories                       = array_filter($categories);
        $this->completeResult['category'] = $categories;


        // get searchable links results
        $links = [];
        if ($result['links']) {
            foreach ($result['links']->items as $document) {
                $links[] = $this->prepareLinkItem($document);
            }
        }
        // filter out empty values
        $links                         = array_filter($links);
        $this->completeResult['links'] = $links;

        $numbers  = array_map(
            static function (ResultItem $item) {
                return $item->fields['ean'];
            },
            $result['product']->items
        );
        $products = $this->productService->getList($numbers, $context);

        $facets = $this->mapFacets($result['product'], $criteria, $context);

        return [$products, $result, $facets];
    }
}
