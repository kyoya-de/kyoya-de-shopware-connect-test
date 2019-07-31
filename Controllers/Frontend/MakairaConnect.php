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
     * @var \Symfony\Component\HttpFoundation\Request
     */
    private $makairaRequest;

    /** @var \Doctrine\ORM\EntityRepository */
    private $repo_article;

    /** @var \Doctrine\ORM\EntityRepository */
    private $repo_supplier;

    /** @var \Doctrine\ORM\EntityRepository */
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
     * @return array|string[]
     */
    public function getWhitelistedCSRFActions() {
        return [
            'import',
        ];
    }

    /**
     * invalid action -> redirect to index site
     * @throws Exception
     */
    public function indexAction() {
        $this->redirect('index');
    }

    /**
     * import action for makaira to connect to
     * See list of possible actions/methods in 'SELF::POSSIBLE_ACTIONS'
     */
    public function importAction() {
        Shopware()->Plugins()->Controller()->ViewRenderer()->setNoRender();
        $this->{$this->params['action']};
    }

    private function listLanguages() {

    }

    private function getUpdates() {
        /** @var \MakairaConnect\Repositories\MakRevisionRepository $makRevisionRepo */
        $makRevisionRepo = Shopware()->Models()->getRepository(MakRevisionModel::class);

        /** @var MakRevisionModel[] $revisions */
        $revisions = $makRevisionRepo->getRevisions($this->params['since'], $this->params['count']);

        $result = [
            'type'     => null,
            'since'    => $this->params['since'],
            'count'    => count($revisions),
            'changes'  => [],
            'language' => 'de',
            'highLoad' => false,
            'ok'       => true,
        ];

        foreach ($revisions as $revision) {
            $changeResult = [
                'type'     => $revision->getType(),
                'id'       => $revision->getId(),
                'sequence' => $revision->getSequence(),
                'data'     => [],
                'deleted'  => true,
            ];

            switch($revision->getType()) {
                case 'product':
                    $data = $this->fetchProducts($changeResult['id'], true);
                    break;

                case 'variant':
                    $data = $this->fetchProducts($changeResult['id'], false);
                    break;

                case 'category':
                    $data = $this->repo_category->findBy(['id' => $changeResult['id']]);
                    break;

                case 'manufacturer':
                    $data = $this->repo_supplier->findBy(['id' => $changeResult['id']]);
                    break;
            }

            if($data) {
                $this->saveObjectData($data, $changeResult);
            }

            $result['changes'][] = $changeResult;
        }

        $jsonResponse = new JsonResponse();
        $jsonResponse->setData($result);

        $jsonResponse->send();
    }

    /**
     * @param $data
     * @param $changeResult &array
     */
    private function saveObjectData($data, &$changeResult) {
        $changeResult['deleted'] = false;
        $changeResult['data']    = $data;

        if($changeResult['type'] === 'product') {
            $changeResult['data']['id'] = $changeResult['data']['articleID'];
        } else if ($changeResult['type'] === 'variant') {
            $changeResult['data']['parent'] = $changeResult['data']['articleID'];
        }
    }

    /**
     * @param $id string
     * @param bool $getAllProducts
     * @return mixed
     */
    private function fetchProducts($id, $getAllProducts = false) {
        if($getAllProducts) {
            $table = 'article';
        } else {
            $table = 'details';
        }

        /** @var \Doctrine\ORM\QueryBuilder $shopArticleQuery */
        $shopArticleQuery = $this->repo_article->createQueryBuilder('article')
            ->select('article, details')
            ->innerJoin('article.details', 'details')
            ->where($table.'.id = :id')
            ->setParameter('id', $id);

        return $shopArticleQuery->getQuery()->getResult(self::HYDRATE_ARRAY);
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
}
