<?php

namespace MakairaConnect\Subscriber;

use Enlight\Event\SubscriberInterface;
use Enlight_Controller_ActionEventArgs;
use MakairaConnect\MakairaConnect;
use MakairaConnect\Service\SearchService;

class ShopwareSubscriber implements SubscriberInterface 
{
    /**
     * @var SearchService
     */
    private $searchService;

    /**
     * @var MakairaConnect
     */
    private $makairaConnect;

    /**
     * @param SearchService $productSearch
     */
    public function __construct(SearchService $productSearch, MakairaConnect $makairaConnect)
    {
        $this->searchService  = $productSearch;
        $this->makairaConnect = $makairaConnect;
    }

    /**
    * for all events like 'Shopware_Modules_Basket_AddArticle_Start'
    * @return array
    */
    public static function getSubscribedEvents(): array {
        return [
            'Enlight_Controller_Action_PostDispatch_Frontend_AjaxSearch' => 'modifySearchResults',
            'Enlight_Controller_Action_PostDispatchSecure_Frontend' => 'onFrontendDispatch'
        ];
    }

    /**
     * @param Enlight_Controller_ActionEventArgs $scope
     */
    public function modifySearchResults(Enlight_Controller_ActionEventArgs $scope)
    {
        $controller = $scope->getSubject();
        $view = $controller->View();
        $searchResult = [];

        if ($view->getAssign("sSearchResults")) {
            $searchResult = $this->searchService->getCompleteResult();
        }

        $view->assign('makairaResult', $searchResult);
    }

    public function onFrontendDispatch(\Enlight_Controller_ActionEventArgs $args)
    {
        $subject = $args->getSubject();

        $viewPath = $this->makairaConnect->getPath() . '/Resources/Views';

        $subject->View()->addTemplateDir($viewPath);
    }

}
