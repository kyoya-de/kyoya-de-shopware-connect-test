<?php

use Elasticsearch\Common\Exceptions\Forbidden403Exception;
use Makaira\Signing\Hash\Sha256;
use Shopware\Components\CSRFWhitelistAware;
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
     * @var \Symfony\Component\HttpFoundation\Request
     */
    private $makairaRequest;

    public function preDispatch()
    {
        $controller   = $this->get('plugin_manager')->Controller();
        $controller->ViewRenderer()->setNoRender();

        $this->makairaRequest = Request::createFromGlobals();

        if ('json' === $this->makairaRequest->getContentType()) {
            $params = json_decode(file_get_contents('php://input'), true);
            $this->makairaRequest->request->replace($params);
        }

        $configReader = $this->container->get('shopware.plugin.config_reader');
        $config       = $configReader->getByPluginName('MakairaConnect');

        $this->verifySignature($config['makaira_connect_secret']);
    }

    public function getWhitelistedCSRFActions()
    {
        return [
            'index',
        ];
    }

    public function indexAction()
    {
        $params = json_decode(file_get_contents('php://input'));
        /** @var \MakairaConnect\Changes\Manager $manager */
        $manager = $this->container->get('makaira_connect.changes.manager');
        $changes = $manager->getChanges($params->since, $params->count);

        $result = [
            'type'     => null,
            'since'    => $params->since,
            'count'    => count($changes),
            'changes'  => [],
            'language' => 'de',
            'highLoad' => false,
            'ok'       => true,
        ];

        $db           = $this->container->get('dbal_connection');
        $fetchProduct = $db->prepare(
            'SELECT a.*, d.*
            FROM s_articles a, s_articles_details d
            WHERE a.id = d.articleID AND d.id = ?'
        );

        $productCheck = ['product', 'variant'];

        foreach ($changes as $change) {

            $changeResult = [
                'type'     => $change->getType(),
                'id'       => $change->getId(),
                'sequence' => $change->getSequence(),
                'deleted'  => true,
            ];

            if (in_array($change->getType(), $productCheck, true)) {
                $fetchProduct->execute([$change->getId()]);
                $changeResult['data']    = $fetchProduct->fetch(PDO::FETCH_ASSOC);
                $changeResult['deleted'] = (0 == count($changeResult['data']));

                if ('product' === $change->getType()) {
                    $changeResult['id']         = $changeResult['data']['articleID'];
                    $changeResult['data']['id'] = $changeResult['data']['articleID'];
                } else {
                    $changeResult['data']['parent'] = $changeResult['data']['articleID'];
                }
            }

            $result['changes'][] = $changeResult;
        }

        $jsonResponse = new JsonResponse();
        $jsonResponse->setData($result);

        $jsonResponse->send();
    }

    /**
     * @param string $secret
     *
     * @throws \Enlight_Controller_Exception
     */
    public function verifySignature($secret)
    {
        if (
            !$this->makairaRequest->headers->has('x-makaira-nonce') ||
            !$this->makairaRequest->headers->has('x-makaira-hash')
        ) {
            throw new Enlight_Controller_Exception("Unauthorized", 401);
        }

        $signer = $this->container->get('makaira_connect.signature.hash_generator');

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
