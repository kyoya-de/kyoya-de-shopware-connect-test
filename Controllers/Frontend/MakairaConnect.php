<?php

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\Query\Expr\Join;
use Makaira\Signing\Hash\Sha256;
use MakairaConnect\Mapper;
use MakairaConnect\Models\MakRevision;
use MakairaConnect\Repositories\MakRevisionRepository;
use Shopware\Bundle\StoreFrontBundle\Service\Core\CategoryService;
use Shopware\Bundle\StoreFrontBundle\Service\Core\ContextService;
use Shopware\Bundle\StoreFrontBundle\Service\Core\ManufacturerService;
use Shopware\Bundle\StoreFrontBundle\Service\Core\ProductService;
use Shopware\Bundle\StoreFrontBundle\Service\Core\PropertyService;
use Shopware\Bundle\StoreFrontBundle\Struct\Category;
use Shopware\Bundle\StoreFrontBundle\Struct\Product;
use Shopware\Components\CSRFWhitelistAware;
use Shopware\Components\Model\ModelManager;
use Shopware\Components\Plugin\Configuration\ReaderInterface;
use Shopware\Models\Article\Detail;
use Shopware\Models\Shop\Locale;
use Shopware\Models\Shop\Repository;
use Shopware\Models\Shop\Shop;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * This file is part of a marmalade GmbH project
 * It is not Open Source and may not be redistributed.
 * For contact information please visit http://www.marmalade.de
 *
 * @version    0.1
 * @author     Stefan Krenz <krenz@marmalade.de>
 * @link       http://www.marmalade.de
 */
class Shopware_Controllers_Frontend_MakairaConnect extends Enlight_Controller_Action implements CSRFWhitelistAware
{
    /**
     * List of possible actions to be called from makaira
     */
    public const POSSIBLE_ACTIONS = [
        'getUpdates',
        'listLanguages',
        'getReplicationStatus',
    ];

    /**
     * @var Request
     */
    private $makairaRequest;

    /**
     * @var EntityManager
     */
    private $em;

    /** @var array */
    private $config = [];

    private $productContext;

    /**
     * @throws Enlight_Controller_Exception
     * @throws Exception
     */
    public function preDispatch()
    {
        $this->container->get('plugin_manager')->Controller()->ViewRenderer()->setNoRender();

        $this->em = $this->container->get('models');

        $this->makairaRequest = Request::createFromGlobals();

        if (null !== ($decoded = json_decode($this->makairaRequest->getContent(), true))) {
            $this->makairaRequest->request->replace($decoded);
        }

        $configReader = $this->container->get(ReaderInterface::class);
        $this->config = $configReader->getByPluginName('MakairaConnect');

        $this->verifySignature($this->config['makaira_connect_secret']);

        /** @var ContextService $contextService */
        $contextService = $this->get('shopware_storefront.context_service');

        /** @var Shop $shop */
        $shop   = $this->get('shop');
        $shopId = $shop->getId();
        if (null !== ($mainShop = $shop->getMain())) {
            $shopId = $mainShop->getId();
        }

        /** @var Repository $shopRepo */
        $shopRepo = $this->get('models')->getRepository(Shop::class);
        $qb       = $shopRepo->createQueryBuilder('s');
        $qb->select('s.id')
            ->leftJoin(Locale::class, 'l', Join::WITH, 's.locale = l.id')
            ->where(
                $qb->expr()->andX(
                    $qb->expr()->eq('l.locale', '?1'),
                    $qb->expr()->orX(
                        $qb->expr()->eq('s.id', '?2'),
                        $qb->expr()->eq('s.mainId', '?2')
                    )
                )
            )
            ->setParameter(1, $this->makairaRequest->request->get('language', 'de_DE'))
            ->setParameter(2, $shopId);

        // TODO Add try-catch for \Doctrine\ORM\NoResultException!
        $query = $qb->getQuery();
        try {
            $contextShopId = (int) $query->getSingleScalarResult();
        } catch (NoResultException $noResult) {
            (new JsonResponse(['error' => 'Unknown language.'], Response::HTTP_NOT_FOUND))->send();
            exit(0);
        }

        $this->productContext = $contextService->createProductContext($contextShopId);
    }

    /**
     * @param string $secret
     *
     * @throws Enlight_Controller_Exception
     */
    private function verifySignature(string $secret)
    {
        if (!$this->makairaRequest->headers->has('x-makaira-nonce') ||
            !$this->makairaRequest->headers->has('x-makaira-hash') ||
            !in_array($this->makairaRequest->request->get('action'), self::POSSIBLE_ACTIONS, true) ||
            !method_exists($this, $this->makairaRequest->request->get('action'))) {
            throw new Enlight_Controller_Exception('Unauthorized', 401);
        }

        $signer = new Sha256();

        $expected = $signer->hash(
            $this->makairaRequest->headers->get('x-makaira-nonce'),
            $this->makairaRequest->getContent(),
            $secret
        );

        $current = $this->makairaRequest->headers->get('x-makaira-hash');

        if (!hash_equals($expected, $current)) {
            throw new Enlight_Controller_Exception('Forbidden', 403);
        }
    }

    /**
     * invalid action -> redirect to index site
     *
     * @throws Exception
     */
    public function indexAction()
    {
        $this->redirect('index');
    }

    /**
     * @return array|string[]
     */
    public function getWhitelistedCSRFActions(): array
    {
        return [
            'import',
        ];
    }

    /**
     * import action for makaira to connect to
     * See list of possible actions/methods in 'SELF::POSSIBLE_ACTIONS'
     */
    public function importAction()
    {
        $this->container->get('plugin_manager')->Controller()->ViewRenderer()->setNoRender();
        $this->{$this->makairaRequest->request->get('action')}();
    }

    /**
     *
     */
    public function getReplicationStatus()
    {
        $repo    = $this->em->getRepository(MakRevision::class);
        $indices = $this->makairaRequest->get('indices');
        foreach ($indices as $indexName => $index) {
            $indices[$indexName]['openChanges'] = $repo->countSince((int) $index['lastRevision']);
        }

        (new JsonResponse($indices))->send();
    }

    /**
     * @param MakRevision[] $revisions
     * @param string[]      $productIds
     * @param string[]      $loadedIds
     *
     * @return array
     */
    protected function getDeletedChanges(array $revisions, array $productIds, array $loadedIds): array
    {
        $deletedIds     = array_diff($productIds, $loadedIds);
        $deletedChanges = [];
        foreach ($deletedIds as $deletedId) {
            $revision = $revisions[$deletedId];
            $revision->setId($revision->getEntityId());
            $deletedChanges[] = $this->buildChangesHead($revision, null, true);
        }
        return $deletedChanges;
    }

    private function listLanguages()
    {
        /** @var Shop $shop */
        $shop   = $this->get('shop');
        $shopId = $shop->getId();
        if (null !== ($mainShop = $shop->getMain())) {
            $shopId = $mainShop->getId();
        }

        /** @var Repository $shopRepo */
        $shopRepo = $this->get('models')->getRepository(Locale::class);
        $qb       = $shopRepo->createQueryBuilder('l');
        $qb->select('l.locale')
            ->leftJoin(Shop::class, 's', Join::WITH, 'l.id = s.locale')
            ->where(
                $qb->expr()->orX(
                    $qb->expr()->eq('s.id', '?1'),
                    $qb->expr()->eq('s.mainId', '?1')
                )
            )
            ->setParameter(1, $shopId);

        $query = $qb->getQuery();
        (new JsonResponse(array_map('reset', $query->getScalarResult())))->send();
    }

    /**
     * Will be called from 'importAction'
     *
     * @throws Exception
     */
    private function getUpdates()
    {
        /** @var MakRevisionRepository $makRevisionRepo */
        $makRevisionRepo = $this->container->get('models')->getRepository(MakRevision::class);

        /** @var MakRevision[] $revisions */
        $revisions = $makRevisionRepo->getRevisions(
            $this->makairaRequest->request->get('since'),
            $this->makairaRequest->request->get('count')
        );

        /** @var MakRevision[][] $updates */
        $updates = [];

        foreach ($revisions as $revision) {
            $updates[$revision->getType()][$revision->getId()] = $revision;
        }

        $changes = [];

        if (isset($updates['variant'])) {
            $changes[] = $this->fetchVariants($updates['variant']);
        }

        if (isset($updates['product'])) {
            $changes[] = $this->fetchProducts($updates['product']);
        }

        if (isset($updates['category'])) {
            $changes[] = $this->fetchCategories($updates['category']);
        }

        if (isset($updates['manufacturer'])) {
            $changes[] = $this->fetchManufacturer($updates['manufacturer']);
        }

        /** @var array $result */
        $response = $this->buildResponseHead((array) array_merge(...$changes));

        $jsonResponse = new JsonResponse();
        $jsonResponse->setData($response);
        $jsonResponse->send();
    }

    /**
     * @param MakRevision[] $revisions
     *
     * @return array
     * @throws Exception
     */
    protected function fetchVariants(array $revisions): array
    {
        $productIds = $this->extractIds($revisions);

        /** @var ProductService $productService */
        $productService = $this->get('shopware_storefront.product_service');
        /** @var PropertyService $propertyService */
        $propertyService = $this->get('shopware_storefront.property_service');

        $products = $productService->getList($productIds, $this->productContext);

        $loadedIds = $this->extractIds(
            $products,
            static function ($entity) {
                return $entity->getNumber();
            }
        );

        /** @var Mapper\EntityMapper $mapper */
        $mapper = $this->get('makaira_connect.mapper');

        $changes = [
            array_map(
                function (Product $detail) use ($revisions, $mapper, $propertyService) {
                    return $this->buildChangesHead(
                        $revisions[$detail->getNumber()],
                        $mapper->mapVariant(
                            $detail,
                            $this->productContext,
                            $propertyService->getList([$detail], $this->productContext)
                        )
                    );
                },
                array_values($products)
            ),
        ];

        $changes[] = $this->getDeletedChanges($revisions, $productIds, $loadedIds);

        return (array) array_merge(...$changes);
    }

    /**
     * @param array         $entities
     * @param null|callable $extractFnc
     *
     * @return array
     */
    private function extractIds(array $entities, $extractFnc = null): array
    {
        if (null === $extractFnc || !is_callable($extractFnc)) {
            $extractFnc = static function ($entity) {
                return method_exists($entity, 'getId') ? $entity->getId() : $entity;
            };
        }

        return array_map($extractFnc, $entities);
    }

    /**
     * id       => revision->id         (data object id)
     * sequence => revision->sequence   (revision id)
     * deleted  => as long assumed to be deleted as the object data set was not saved
     * type     => revision->type
     * data     => object data set
     *
     * @param MakRevision $revision
     * @param array|null  $data
     * @param bool        $deleted
     *
     * @return array
     */
    private function buildChangesHead(MakRevision $revision, ?array $data = [], bool $deleted = false): array
    {
        return [
            'id'       => (int) (!$deleted ? $data['id'] : $revision->getId()),
            'sequence' => $revision->getSequence(),
            'deleted'  => $deleted,
            'type'     => $revision->getType(),
            'data'     => $data,
        ];
    }

    /**
     * @param Product $product
     * @return Product[]
     * @throws Exception
     */
    private function getVariants(Product $product): array
    {
        /**@var ModelManager $doctrine */
        $doctrine = $this->get('models');
        $variantRepo = $doctrine->getRepository(Detail::class);
        $variants = $variantRepo->findBy([
            'articleId' => $product->getId()
        ]);

        $variantOrderNumbers = [];
        foreach ($variants as $variant) {
            $variantOrderNumbers[] = $variant->getNumber();
        }

        /** @var ProductService $productService */
        $productService = $this->get('shopware_storefront.product_service');
        return $productService->getList($variantOrderNumbers, $this->productContext);
    }

    /**
     * @param MakRevision[] $revisions
     *
     * @return array
     * @throws Exception
     */
    protected function fetchProducts(array $revisions): array
    {
        $productIds = $this->extractIds($revisions);

        /** @var ProductService $productService */
        $productService = $this->get('shopware_storefront.product_service');

        $products = $productService->getList($productIds, $this->productContext);

        $loadedIds = $this->extractIds(
            $products,
            static function (Product $entity) {
                return $entity->getNumber();
            }
        );

        /** @var Mapper\EntityMapper $mapper */
        $mapper = $this->get('makaira_connect.mapper');

        $changes = [
            array_map(
                function (Product $product) use ($revisions, $mapper) {
                    return $this->buildChangesHead(
                        $revisions[$product->getNumber()],
                        $mapper->mapProduct(
                            $product,
                            $this->getVariants($product),
                            $this->productContext
                        )
                    );
                },
                array_values($products)
            ),
        ];

        $changes[] = $this->getDeletedChanges($revisions, $productIds, $loadedIds);

        return (array) array_merge(...$changes);
    }

    /**
     * @param MakRevision[] $revisions
     *
     * @return array
     * @throws Exception
     */
    protected function fetchCategories(array $revisions): array
    {
        $categoryIds = $this->extractIds($revisions);

        /** @var CategoryService $categoryService */
        $categoryService = $this->get('shopware_storefront.category_service');
        $categories      = $categoryService->getList($categoryIds, $this->productContext);

        /** @var Mapper\EntityMapper $mapper */
        $mapper = $this->get('makaira_connect.mapper');

        $changes = [
            array_map(
                function (Category $category) use ($revisions, $mapper) {
                    return $this->buildChangesHead(
                        $revisions[$category->getId()],
                        $mapper->mapCategory($category, $this->productContext)
                    );
                },
                $categories
            ),
        ];

        $loadedIds = $this->extractIds($categories);

        $changes[] = array_map(
            function ($deletedId) use ($revisions) {
                return $this->buildChangesHead($revisions[$deletedId], null, true);
            },
            array_diff($categoryIds, $loadedIds)
        );

        return (array) array_merge(...$changes);
    }

    /**
     * @param MakRevision[] $revisions
     *
     * @return array
     * @throws Exception
     */
    protected function fetchManufacturer(array $revisions): array
    {
        $ids = $this->extractIds($revisions);

        /** @var ManufacturerService $categoryService */
        $categoryService = $this->get('shopware_storefront.manufacturer_service');
        $suppliers       = $categoryService->getList($ids, $this->productContext);

        /** @var Mapper\EntityMapper $mapper */
        $mapper = $this->get('makaira_connect.mapper');

        $changes   = [];
        $changes[] = array_map(
            function (Product\Manufacturer $supplier) use ($revisions, $mapper) {
                return $this->buildChangesHead(
                    $revisions[$supplier->getId()],
                    $mapper->mapManufacturer($supplier, $this->productContext)
                );
            },
            $suppliers
        );

        $loadedIds = $this->extractIds($suppliers);

        $changes[] = array_map(
            function ($deletedId) use ($revisions) {
                return $this->buildChangesHead($revisions[$deletedId], null, true);
            },
            array_diff($ids, $loadedIds)
        );

        return (array) array_merge(...$changes);
    }

    /**
     * type             => always null
     * since            => request->since
     * indexName        => always null
     * count            => response->revisionCount
     * requestedCount   => request->count
     * active           => always null
     * language         => response->shop(Data)Language // should be the same as in request->language
     * highLoad         => response->shopLoad -> false by default -> true when current shop is at high load
     * changes          => response->data // product, variation. category, manufacturer
     *
     * @param array $changes
     *
     * @return array
     */
    private function buildResponseHead(array $changes = []): array
    {
        return [
            'type'           => null,
            'since'          => $this->makairaRequest->request->get('since'),
            'indexName'      => null,
            'count'          => count($changes),
            'requestedCount' => $this->makairaRequest->request->get('count'),
            'active'         => null,
            'language'       => 'de',    //logic to be implemented
            'highLoad'       => false,   //logic to be implemented
            //'ok'                => true, ?
            'changes'        => $changes,
        ];
    }
}
