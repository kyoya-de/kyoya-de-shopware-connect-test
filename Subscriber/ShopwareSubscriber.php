<?php

namespace MakairaConnect\Subscriber;

use Enlight\Event\SubscriberInterface;
use Enlight_Controller_ActionEventArgs;
use MakairaConnect\Service\SearchService;

class ShopwareSubscriber implements SubscriberInterface 
{
    /**
     * @var SearchService
     */
    private $searchService;

    /**
     * @param SearchService $productSearch
     */
    public function __construct(SearchService $productSearch)
    {
        $this->searchService = $productSearch;
    }

    /**
    * for all events like 'Shopware_Modules_Basket_AddArticle_Start'
    * @return array
    */
    public static function getSubscribedEvents(): array {
        return [
            'Enlight_Controller_Action_PostDispatch_Frontend_AjaxSearch' => 'modifySearchResults',
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
}
