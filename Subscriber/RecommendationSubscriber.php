<?php

namespace MakairaConnect\Subscriber;

use Enlight\Event\SubscriberInterface;
use Enlight_Controller_Action;
use Enlight_Controller_Request_RequestHttp;
use Makaira\Constraints;
use Makaira\RecommendationQuery;
use MakairaConnect\Client\ApiInterface;

class RecommendationSubscriber implements SubscriberInterface
{
    private ApiInterface $api;

    public function __construct(ApiInterface $api)
    {
        $this->api = $api;
    }

    /**
     * only add Events::<classes>
     *
     * @return array
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'Enlight_Controller_Action_PostDispatch_Frontend_Detail' => 'onEnlightControllerActionPostDispatchFrontendDetail'
        ];
    }

    /**
     * @throws \Makaira\Connect\Exception
     */
    public function onEnlightControllerActionPostDispatchFrontendDetail(\Enlight_Event_EventArgs $arguments)
    {
        $config = Shopware()->Container()->get('shopware.plugin.cached_config_reader')->getByPluginName('MakairaConnect');
        if ($config['makaira_recommendations_active']) {
            /** @var $subject Enlight_Controller_Action */
            $subject = $arguments->get('subject');

            $view = $subject->View();

            $sArticle = $view->getAssign('sArticle');

            $sArticle['sSimilarArticles'] = $this->getRecommendationItems($sArticle, $config, $arguments->getRequest());

            $view->assign('sArticle', $sArticle);
        }
    }

    /**
     * @throws \Makaira\Connect\Exception
     */
    private function getRecommendationItems($sArticle, $config, Enlight_Controller_Request_RequestHttp $request): array
    {
        $query = new RecommendationQuery();

        $query->setConstraint(Constraints::OI_USER_AGENT, $request->getHeader('User-Agent'));
        $query->setConstraint(Constraints::OI_USER_ID, $request->getClientIp());
        $session = Shopware()->Session();
        if (!empty($session->sUserId)) {
            $query->setConstraint(Constraints::OI_USER_ID, $session->sUserId);
        }

        if (!empty($_COOKIE['oiLocalTimeZone'])) {
            $query->setConstraint(Constraints::OI_USER_TIMEZONE, $_COOKIE['oiLocalTimeZone']);
        }

        $shop = Shopware()->Shop();
        $query->setConstraint(Constraints::SHOP, $shop->getId());

        $locale = $shop->getLocale();
        $language = substr($locale->getLocale(), 0, 2);
        $query->setConstraint(Constraints::LANGUAGE, $language);

        $query->offset = 0;
        $query->count = 10;

        $query->recommendationId = $config['makaira_recommendations_identifier'] ?? 'none';

        $query->productId = $sArticle['articleID'];

        $query->fields = ['id'];

        $recommendedProducts = $this->api->getRecommendedProducts($query);

        $sSimilarArticles = [];

        $shopwareArticleService = Shopware()->Modules()->Articles();
        foreach ($recommendedProducts as $recommendedProduct) {
            $sSimilarArticles[] = $shopwareArticleService->sGetArticleById($recommendedProduct['id']);
        }

        return $sSimilarArticles;
    }
}
