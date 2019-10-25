<?php

use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\QueryBuilder;
use Makaira\Signing\Hash\Sha256;
use MakairaConnect\Mapper;
use MakairaConnect\Models\MakRevision as MakRevisionModel;
use MakairaConnect\Repositories\MakRevisionRepository;
use Shopware\Components\CSRFWhitelistAware;
use Shopware\Models\Article\Article as ArticleModel;
use Shopware\Models\Article\Supplier as SupplierModel;
use Shopware\Models\Category\Category as CategoryModel;
use Shopware\Models\Category\Repository;
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
     * Attribute maps
     */
    public const MAP_CATEGORY = [
        //      [Makaira Attribute      => Shopware Attribute]
        //      Fields that can be mapped
        'mak_category_title'   => 'name',
        'mak_sort'             => 'position',
        'mak_longdesc'         => 'cmsText',
        'mak_meta_keywords'    => 'metaKeywords',
        'mak_meta_description' => 'metaDescription',
        'id'                   => 'id',
        'mak_active'           => 'active',
        'active'               => 'active',
        'shop'                 => 'shops',

        //      fields which require additional logic besides simple mapping
        'timestamp'            => 'changed',   //changed->date
        'depth'                => 'path',      //count | -1
        'url'                  => 'id',        //implode by path->name

        //      fields that cannot be mapped and their fore are left empty
        'mak_shortdesc'        => '',
        'hierarchy'            => '',
        'subcategories'        => '',
    ];

    /**
     * @var Request
     */
    private $makairaRequest;

    /** @var EntityRepository */
    private $productRepository;

    /** @var EntityRepository */
    private $manufacturerRepository;

    /** @var Repository */
    private $categoryRepository;

    /** @var array */
    private $config = [];

    /**
     * @throws Enlight_Controller_Exception
     */
    public function preDispatch()
    {
        $this->container->get('plugin_manager')->Controller()->ViewRenderer()->setNoRender();

        $em = $this->container->get('models');

        $this->productRepository      = $em->getRepository(ArticleModel::class);
        $this->categoryRepository     = $em->getRepository(CategoryModel::class);
        $this->manufacturerRepository = $em->getRepository(SupplierModel::class);

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
    private function verifySignature($secret)
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
     */
    private function listLanguages()
    {
    }

    /**
     * Will be called from 'importAction'
     *
     * @throws NoResultException
     * @throws NonUniqueResultException
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

        /** @var array $result */
        $response = $this->buildResponseHead(count($revisions));

        /** @var Mapper\MapperPool $mapper */
        $mapper = $this->get('makaira_connect.mapper');

        $data = [];

        foreach ($revisions as $revision) {
            switch ($revision->getType()) {
                case 'product':
                    $data = $this->fetchProducts($revision->getId(), true);
                    break;

                case 'variant':
                    $data = $this->fetchProducts($revision->getId(), false);
                    break;

                case 'category':
                    $data = $this->fetchCategory($revision->getId());
                    break;

                case 'manufacturer':
                    $data = $this->fetchManufacturer($revision->getId());
                    break;
            }

            $changes = $this->buildChangesHead($revision);

            //enable data set for makaira
            if (count($data)) {
                $changes['deleted'] = false;
                $changes['data']    = $mapper->mapDocument($revision->getType(), $data);
            }

            $response['changes'][] = $changes;
        }

        $jsonResponse = new JsonResponse();
        $jsonResponse->setData($response);
        $jsonResponse->send();
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
     * @param $revisionCount int
     *
     * @return array
     */
    private function buildResponseHead($revisionCount): array
    {
        return [
            'type'           => null,
            'since'          => $this->makairaRequest->request->get('since'),
            'indexName'      => null,
            'count'          => $revisionCount,
            'requestedCount' => $this->makairaRequest->request->get('count'),
            'active'         => null,
            'language'       => 'de',    //logic to be implemented
            'highLoad'       => false,   //logic to be implemented
            //'ok'                => true, ?
            'changes'        => [],
        ];
    }

    /**
     * @param      $id int
     * @param bool $getAllProducts
     *
     * @return mixed
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    private function fetchProducts($id, $getAllProducts = false)
    {
        if ($getAllProducts) {
            $table = 'article';
        } else {
            $table = 'details';
        }

        /** @var QueryBuilder $shopQuery */
        $shopQuery = $this->productRepository->createQueryBuilder('article')->select('article, details')->innerJoin(
            'article.details',
            'details'
        )->where($table . '.id = :id')->setParameter('id', $id);

        return $shopQuery->getQuery()->getSingleResult(AbstractQuery::HYDRATE_ARRAY);
    }

    /**
     * @param $id int
     *
     * @return array
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    private function fetchCategory($id): array
    {
        /** @var QueryBuilder $shopQuery */
        $shopQuery =
            $this->categoryRepository->createQueryBuilder('c')->select('c')->where('c.id = :id')->setParameter(
                    'id',
                    $id
                )->setMaxResults(1);

        $rawChanges = $shopQuery->getQuery()->getSingleResult(AbstractQuery::HYDRATE_ARRAY);
        $changes    = $this->fetchMappedChanges(self::MAP_CATEGORY, $rawChanges);

        $depth            = substr_count($changes['depth'], '|');
        $changes['depth'] = $depth ? $depth - 1 : '';
        $changes['url']   = $this->getPath($changes['url']);

        return $changes;
    }

    /**
     * @param $map         array
     * @param $rawChanges  array
     *
     * @return array
     */
    private function fetchMappedChanges($map, $rawChanges): array
    {
        if (count($rawChanges) !== 1) {
            return [];
        }

        $mappedChanges                   = [];
        $mappedChanges['additionalData'] = $rawChanges;

        foreach ($map as $makaira => $shopware) {
            $mappedChanges[$makaira] = $rawChanges[$shopware];
        }

        $mappedChanges['timestamp'] = $mappedChanges['timestamp']->format('Y-m-d H:i:s');

        return $mappedChanges;
    }

    /**
     * The first path entry is the language, that may seem logically correct but:
     * -> if that link would be called, shopware could not interpret it correctly
     * -> the language never occurs within the browser link
     * -> their fore we have to cut it off to get the real link to the category
     *
     * @param $id
     *
     * @return string
     */
    private function getPath($id): string
    {
        $path = $this->categoryRepository->getPathById($id);
        array_shift($path);

        return strtolower(implode('/', $path)) . '/';
    }

    /**
     * @param $id int
     *
     * @return array
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    private function fetchManufacturer($id): array
    {
        /** @var QueryBuilder $shopQuery */
        $shopQuery = $this->manufacturerRepository->createQueryBuilder('s')
            ->select('s')
            ->where('s.id = :id')
            ->setParameter('id', $id)
            ->setMaxResults(1);

        $rawChanges = $shopQuery->getQuery()->getSingleResult(AbstractQuery::HYDRATE_ARRAY);

        return $this->fetchMappedChanges(self::MAP_CATEGORY, $rawChanges);
    }

    /**
     * id       => revision->id         (data object id)
     * sequence => revision->sequence   (revision id)
     * deleted  => as long assumed to be deleted as the object data set was not saved
     * type     => revision->type
     * data     => object data set
     *
     * @param $revision MakRevisionModel
     *
     * @return array
     */
    private function buildChangesHead($revision): array
    {
        return [
            'id'       => $revision->getId(),
            'sequence' => $revision->getSequence(),
            'deleted'  => true,
            'type'     => $revision->getType(),
            'data'     => [],
        ];
    }

    /**
     * @param $data
     * @param $changeResult &array
     */
    private function saveObjectData($data, &$changeResult)
    {
        if ($changeResult['type'] === 'product') {
            $changeResult['data']['id'] = $changeResult['data']['articleID'];
        } else if ($changeResult['type'] === 'variant') {
            $changeResult['data']['parent'] = $changeResult['data']['articleID'];
        }
    }
}
