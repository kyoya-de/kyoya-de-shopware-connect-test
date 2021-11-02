<?php

namespace MakairaConnect\Subscriber;

use Enlight\Event\SubscriberInterface;
use Enlight_Controller_Action;
use Enlight_Controller_Request_RequestHttp;
use Enlight_Controller_Response_ResponseHttp;

class RecommendationSubscriber implements SubscriberInterface
{

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

    public function onEnlightControllerActionPostDispatchFrontendDetail(\Enlight_Event_EventArgs $arguments)
    {
        /** @var $subject Enlight_Controller_Action */
        $subject = $arguments->get('subject');

        /** @var $request Enlight_Controller_Request_RequestHttp */
        $request = $arguments->getRequest();

        /** @var $response Enlight_Controller_Response_ResponseHttp */
        $response = $arguments->getResponse();

        $view = $subject->View();
        $result = $this->getRecommendationItems();

        $sArticle = $view->getAssign('sArticle');
        die(var_dump($sArticle['sSimilarArticles']));
        $sArticle['sSimilarArticles'] = [];
        $view->assign('sArticle', $sArticle);
    }

    private function getRecommendationItems(): array
    {
        return [];
    }
}
