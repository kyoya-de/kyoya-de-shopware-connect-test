<?php

use Elasticsearch\Common\Exceptions\Forbidden403Exception;
use Makaira\Signing\Hash\Sha256;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

use MakairaConnect\Models\MakRevision as MakRevisionModel;

use Shopware\Models\Article\Article as ArticleModel;
use Shopware\Models\Article\Supplier as SupplierModel;
use Shopware\Models\Category\Category as CategoryModel;

use Shopware\Components\CSRFWhitelistAware;

/**
 * This file is part of a marmalade GmbH project
 * It is not Open Source and may not be redistributed.
 * For contact information please visit http://www.marmalade.de
 *
 * @version    0.1
 * @author     Stefan Krenz <krenz@marmalade.de>
 * @link       http://www.marmalade.de
 */
class Shopware_Controllers_Frontend_MakairaConnect extends \Enlight_Controller_Action implements CSRFWhitelistAware {
    /* Hydration mode constants */
    /**
     * Hydrates an object graph. This is the default behavior.
     */
    CONST HYDRATE_OBJECT = 1;

    /**
     * Hydrates an array graph.
     */
    CONST HYDRATE_ARRAY = 2;

    /**
     * List of possible actions to be called from makaira
     */
    CONST POSSIBLE_ACTIONS = ['getUpdates', 'listLanguages'];

    /**
     * Attribute maps
     */
    CONST MAP_CATEGORY = [
//      [Makaira Attribute      => Shopware Attribute]
//      Fields that can be mapped
        'mak_category_title'    => 'name',
        'mak_sort'              => 'position',
        'mak_longdesc'          => 'cmsText',
        'mak_meta_keywords'     => 'metaKeywords',
        'mak_meta_description'  => 'metaDescription',
        'id'                    => 'id',
        'mak_active'            => 'active',
        'active'                => 'active',
        'shop'                  => 'shops',

//      fields which require additional logic besides simple mapping
        'timestamp'             => 'changed',   //changed->date
        'depth'                 => 'path',      //count | -1
        'url'                   => 'id',        //implode by path->name

//      fields that cannot be mapped and their fore are left empty
        'mak_shortdesc'         => '',
        'hierarchy'             => '',
        'subcategories'         => '',
    ];

    /**
     * @var \Symfony\Component\HttpFoundation\Request
     */
    private $makairaRequest;

    /** @var \Doctrine\ORM\EntityRepository */
    private $repo_article;

    /** @var \Doctrine\ORM\EntityRepository */
    private $repo_supplier;

    /** @var \Shopware\Models\Category\Repository */
    private $repo_category;

    /** @var \Shopware\Components\Model\ModelManager */
    private $em;

    /** @var array */
    private $config = [];

    /** @var array */
    private $params = [];

    /**
     * @throws Enlight_Controller_Exception
     */
    public function preDispatch() {
        Shopware()->Plugins()->Controller()->ViewRenderer()->setNoRender();

        $this->em = Shopware()->Models();
        $this->repo_article = $this->em->getRepository(ArticleModel::class);
        $this->repo_category = $this->em->getRepository(CategoryModel::class);
        $this->repo_supplier = $this->em->getRepository(SupplierModel::class);

        $this->makairaRequest = Request::createFromGlobals();
        $this->params = json_decode(file_get_contents('php://input'), true);

        if ('json' === $this->makairaRequest->getContentType()) {
            $this->makairaRequest->request->replace($this->params);
        }

        $configReader = $this->container->get('shopware.plugin.config_reader');
        $this->config = $configReader->getByPluginName('MakairaConnect');

        $this->verifySignature($this->config['makaira_connect_secret']);
    }

    /**
     * @param string $secret
     *
     * @throws \Enlight_Controller_Exception
     */
    private function verifySignature($secret) {
        if (
            !$this->makairaRequest->headers->has('x-makaira-nonce') ||
            !$this->makairaRequest->headers->has('x-makaira-hash') ||
            !in_array($this->params['action'], SELF::POSSIBLE_ACTIONS, true) ||
            !method_exists($this, $this->params['action'])
        ) {
            throw new Enlight_Controller_Exception("Unauthorized", 401);
        }

        $signer = new Sha256();

        $expected = $signer->hash(
            $this->makairaRequest->headers->get('x-makaira-nonce'),
            $this->makairaRequest->getContent(),
            $secret
        );

        $current = $this->makairaRequest->headers->get('x-makaira-hash');

        if (!hash_equals($expected, $current)) {
            throw new Enlight_Controller_Exception("Forbidden", 403);
        }
    }

    /**
     * invalid action -> redirect to index site
     * @throws Exception
     */
    public function indexAction() {
        $this->redirect('index');
    }

    /**
     * @return array|string[]
     */
    public function getWhitelistedCSRFActions() {
        return [
            'import',
        ];
    }

    /**
     * import action for makaira to connect to
     * See list of possible actions/methods in 'SELF::POSSIBLE_ACTIONS'
     */
    public function importAction() {
        Shopware()->Plugins()->Controller()->ViewRenderer()->setNoRender();
        $this->{$this->params['action']}();
//        call_user_func([
//            $this,
//            $this->params['action']
//        ]);
    }

    /**
     * Will be called from 'importAction'
     */
    private function listLanguages() {

    }

    /**
     * Will be called from 'importAction'
     */
    private function getUpdates() {
        /** @var \MakairaConnect\Repositories\MakRevisionRepository $makRevisionRepo */
        $makRevisionRepo = Shopware()->Models()->getRepository(MakRevisionModel::class);

        /** @var MakRevisionModel[] $revisions */
        $revisions = $makRevisionRepo->getRevisions($this->params['since'], $this->params['count']);

        /** @var array $result */
        $response = $this->buildResponseHead(count($revisions));

        foreach ($revisions as $revision) {
            switch($revision->getType()) {
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
            if(count($data)) {
                $changes['deleted'] = false;
                $changes['data'] = $data;
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
     * @return array
     */
    private function buildResponseHead($revisionCount) {
        return [
            'type'              => null,
            'since'             => $this->params['since'],
            'indexName'         => null,
            'count'             => $revisionCount,
            'requestedCount'    => $this->params['count'],
            'active'            => null,
            'language'          => 'de',    //logic to be implemented
            'highLoad'          => false,   //logic to be implemented
            //'ok'                => true, ?
            'changes'           => [],
        ];
    }

    /**
     * id       => revision->id         (data object id)
     * sequence => revision->sequence   (revision id)
     * deleted  => as long assumed to be deleted as the object data set was not saved
     * type     => revision->type
     * data     => object data set
     *
     * @param $revision MakRevisionModel
     * @return array
     */
    private function buildChangesHead($revision) {
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
    private function saveObjectData($data, &$changeResult) {
        if($changeResult['type'] === 'product') {
            $changeResult['data']['id'] = $changeResult['data']['articleID'];
        } else if ($changeResult['type'] === 'variant') {
            $changeResult['data']['parent'] = $changeResult['data']['articleID'];
        }
    }

    /**
     * @param $id int
     * @param bool $getAllProducts
     * @return mixed
     */
    private function fetchProducts($id, $getAllProducts = false) {
        if($getAllProducts) {
            $table = 'article';
        } else {
            $table = 'details';
        }

        /** @var \Doctrine\ORM\QueryBuilder $shopQuery */
        $shopQuery = $this->repo_article->createQueryBuilder('article')
            ->select('article, details')
            ->innerJoin('article.details', 'details')
            ->where($table.'.id = :id')
            ->setParameter('id', $id);

        return $shopQuery->getQuery()->getResult(self::HYDRATE_ARRAY);
    }

    /**
     * @param $id int
     * @return array
     */
    private function fetchCategory($id) {
        /** @var \Doctrine\ORM\QueryBuilder $shopQuery */
        $shopQuery = $this->repo_category->createQueryBuilder('c')
            ->select('c')
            ->where('c.id = :id')
            ->setParameter('id', $id)
            ->setMaxResults(1);

        $raw_changes = $shopQuery->getQuery()->getResult(self::HYDRATE_ARRAY);
        $changes = $this->fetchMappedChanges(SELF::MAP_CATEGORY, $raw_changes);

        $depth = substr_count($changes['depth'], '|');
        $changes['depth']   = $depth ? $depth - 1 : '';
        $changes['url']     = $this->getPath($changes['url']);

        return $changes;
    }

    /**
     * @param $id int
     * @return array
     */
    private function fetchManufacturer($id) {
        /** @var \Doctrine\ORM\QueryBuilder $shopQuery */
        $shopQuery = $this->repo_supplier->createQueryBuilder('s')
            ->select('s')
            ->where('s.id = :id')
            ->setParameter('id', $id)
            ->setMaxResults(1);

        $raw_changes = $shopQuery->getQuery()->getResult(self::HYDRATE_ARRAY);
        return $this->fetchMappedChanges(SELF::MAP_CATEGORY, $raw_changes);
    }

    /**
     * @param $map array
     * @param $raw_changes array
     * @return array
     */
    private function fetchMappedChanges($map, $raw_changes) {
        if(count($raw_changes) !== 1) {
            return [];
        }

        $mapped_changes = [];
        $mapped_changes['additionalData'] = $raw_changes[0];

        foreach($map as $makaira => $shopware) {
            $mapped_changes[$makaira] = $raw_changes[0][$shopware];
        }

        $mapped_changes['timestamp'] = $mapped_changes['timestamp']->format('Y-m-d H:i:s');

        return $mapped_changes;
    }

    /**
     * The first path entry is the language, that may seem logically correct but:
     * -> if that link would be called, shopware could not interpret it correctly
     * -> the language never occurs within the browser link
     * -> their fore we have to cut it off to get the real link to the category
     *
     * @param $id
     * @return string
     */
    private function getPath($id) {
        $path = $this->repo_category->getPathById($id);
        array_shift($path);

        return strtolower(
            implode('/', $path)
        ).'/';
    }
}
