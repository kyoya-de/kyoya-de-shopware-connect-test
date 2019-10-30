<?php

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\QueryBuilder;
use Makaira\Signing\Hash\Sha256;
use MakairaConnect\Mapper;
use MakairaConnect\Models\MakRevision as MakRevisionModel;
use MakairaConnect\Repositories\MakRevisionRepository;
use Shopware\Components\CSRFWhitelistAware;
use Shopware\Models\Article\Article as ArticleModel;
use Shopware\Models\Article\Detail;
use Shopware\Models\Article\Supplier as SupplierModel;
use Shopware\Models\Category\Category as CategoryModel;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

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
    public const POSSIBLE_ACTIONS = ['getUpdates', 'listLanguages'];

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

    /**
     * @throws Enlight_Controller_Exception
     */
    public function preDispatch()
    {
        $this->container->get('plugin_manager')->Controller()->ViewRenderer()->setNoRender();

        $this->em = $this->container->get('models');

        $this->makairaRequest = Request::createFromGlobals();

        if ('json' === $this->makairaRequest->getContentType()) {
            $body = $this->makairaRequest->getContent();
            $decoded = json_decode($body, true);
            $this->makairaRequest->request->replace($decoded);
        }

        $configReader = $this->container->get('shopware.plugin.config_reader');
        $this->config = $configReader->getByPluginName('MakairaConnect');

        $this->verifySignature($this->config['makaira_connect_secret']);
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
     * Will be called from 'importAction'
     *
     * @throws Exception
     */
    private function getUpdates()
    {
        /** @var MakRevisionRepository $makRevisionRepo */
        $makRevisionRepo = $this->container->get('models')->getRepository(MakRevisionModel::class);

        /** @var MakRevisionModel[] $revisions */
        $revisions = $makRevisionRepo->getRevisions(
            $this->makairaRequest->request->get('since'),
            $this->makairaRequest->request->get('count')
        );

        /** @var MakRevisionModel[][] $updates */
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
        $response = $this->buildResponseHead(array_merge(...$changes));

        $jsonResponse = new JsonResponse();
        $jsonResponse->setData($response);
        $jsonResponse->send();
    }

    /**
     * @param MakRevisionModel[] $revisions
     *
     * @return array
     * @throws Exception
     */
    protected function fetchProducts(array $revisions): array
    {
        $repo = $this->em->getRepository(ArticleModel::class);

        $productIds = $this->extractIds($revisions);

        /** @var QueryBuilder $qb */
        $qb = $repo->createQueryBuilder('article');
        $query = $qb
            ->select('article, details')
            ->innerJoin('article.details', 'details')
            ->where($qb->expr()->in('article.id', $productIds))
            ->getQuery();

        /** @var ArticleModel[] $products */
        $products = $query->getResult();

        $loadedIds = $this->extractIds($products);

        /** @var Mapper\EntityMapper $mapper */
        $mapper = $this->get('makaira_connect.mapper');

        $changes = [];
        $changes[] = array_map(
            function (ArticleModel $product) use ($revisions, $mapper) {
                return $this->buildChangesHead($revisions[$product->getId()], $mapper->mapProduct($product));
            },
            $products
        );

        $deletedIds = array_diff($productIds, $loadedIds);
        $changes[] = array_map(
            function ($deletedId) use ($revisions) {
                return $this->buildChangesHead($revisions[$deletedId]);
            },
            $deletedIds
        );

        return (array) array_merge(...$changes);
    }

    /**
     * @param MakRevisionModel[] $revisions
     *
     * @return array
     * @throws Exception
     */
    protected function fetchVariants(array $revisions): array
    {
        $repo = $this->em->getRepository(Detail::class);

        $productIds = $this->extractIds($revisions);

        /** @var QueryBuilder $qb */
        $qb = $repo->createQueryBuilder('details');
        $query = $qb
            ->select('details')
            ->innerJoin('details.article', 'article')
            ->where($qb->expr()->in('details.id', $productIds))
            ->getQuery();

        /** @var Detail[] $details */
        $details = $query->getResult();

        $loadedIds = $this->extractIds($details);

        /** @var Mapper\EntityMapper $mapper */
        $mapper = $this->get('makaira_connect.mapper');

        $changes = [];
        $changes[] = array_map(
            function (Detail $detail) use ($revisions, $mapper) {
                return $this->buildChangesHead($revisions[$detail->getId()], $mapper->mapVariant($detail));
            },
            $details
        );

        $deletedIds = array_diff($productIds, $loadedIds);
        $changes[] = array_map(
            function ($deletedId) use ($revisions) {
                return $this->buildChangesHead($revisions[$deletedId]);
            },
            $deletedIds
        );

        return (array) array_merge(...$changes);
    }

    /**
     * @param MakRevisionModel[] $revisions
     *
     * @return array
     * @throws Exception
     */
    private function fetchCategories(array $revisions): array
    {
        $repo = $this->em->getRepository(CategoryModel::class);
        $qb = $repo->createQueryBuilder('c');

        $categoryIds = $this->extractIds($revisions);

        $shopQuery  = $qb
            ->select('c')
            ->where($qb->expr()->in('c.id', $categoryIds))
            ->getQuery();

        /** @var CategoryModel[] $categories */
        $categories = $shopQuery->getResult();

        /** @var Mapper\EntityMapper $mapper */
        $mapper = $this->get('makaira_connect.mapper');

        $changes = [];
        $changes[] = array_map(
            function (CategoryModel $category) use ($revisions, $mapper) {
                return $this->buildChangesHead(
                    $revisions[$category->getId()],
                    $mapper->mapCategory($category)
                );
            },
            $categories
        );

        $loadedIds = $this->extractIds($categories);

        $changes[] = array_map(
            function ($deletedId) use ($revisions) {
                return $this->buildChangesHead($revisions[$deletedId]);
            },
            array_diff($categoryIds, $loadedIds)
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

    /**
     * @param MakRevisionModel[] $revisions
     *
     * @return array
     * @throws Exception
     */
    private function fetchManufacturer(array $revisions): array
    {
        $ids = $this->extractIds($revisions);

        $repo = $this->em->getRepository(SupplierModel::class);

        $qb = $repo->createQueryBuilder('s');
        $shopQuery = $qb
            ->select('s')
            ->where($qb->expr()->in('s.id', $ids))
            ->getQuery();

        $suppliers = $shopQuery->getResult();

        /** @var Mapper\EntityMapper $mapper */
        $mapper = $this->get('makaira_connect.mapper');

        $changes = [];
        $changes[] = array_map(
            function (SupplierModel $supplier) use ($revisions, $mapper) {
                return $this->buildChangesHead($revisions[$supplier->getId()], $mapper->mapManufacturer($supplier));
            },
            $suppliers
        );

        $loadedIds = $this->extractIds($suppliers);

        $changes[] = array_map(
            function ($deletedId) use ($revisions) {
                return $this->buildChangesHead($revisions[$deletedId]);
            },
            array_diff($ids, $loadedIds)
        );

        return (array) array_merge(...$changes);
    }

    /**
     * id       => revision->id         (data object id)
     * sequence => revision->sequence   (revision id)
     * deleted  => as long assumed to be deleted as the object data set was not saved
     * type     => revision->type
     * data     => object data set
     *
     * @param MakRevisionModel $revision
     * @param array            $data
     *
     * @return array
     */
    private function buildChangesHead(MakRevisionModel $revision, array $data = []): array
    {
        return [
            'id'       => $revision->getId(),
            'sequence' => $revision->getSequence(),
            'deleted'  => [] === $data,
            'type'     => $revision->getType(),
            'data'     => $data,
        ];
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
}
