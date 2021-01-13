<?php

namespace MakairaConnect\Subscriber;

use Enlight\Event\SubscriberInterface;
use Psr\Container\ContainerInterface;
use Enlight_Event_EventArgs;
use MakairaConnect\Repositories\MakRevisionRepository;
use MakairaConnect\Models\MakRevision;

class ShopwareSubscriber implements SubscriberInterface 
{
    /** @var MakRevisionRepository */
    private $makRevisionRepo;
    
    /**
     * @var ContainerInterface
     */
    private $container;

    public function __construct() {
        $this->makRevisionRepo = Shopware()->Models()->getRepository(MakRevision::class);
        
        $this->container = Shopware()->Container();
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
    
    public function modifySearchResults(\Enlight_Controller_ActionEventArgs $scope)
    {
        $return = $scope->getReturn();
        $controller = $scope->getSubject();
        $view = $controller->View();
        
        if ($view->getAssign("sSearchResults")) {
            $searchResult = $this->container->get('makaira_search.product_search')->getCompleteResult();
        }
        $view->assign('makairaResult', $searchResult);
    }
}