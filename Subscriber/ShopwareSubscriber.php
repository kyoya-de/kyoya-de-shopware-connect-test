<?php

namespace MakairaConnect\Subscriber;

use Enlight\Event\SubscriberInterface;
use MakairaConnect\MakairaConnect;
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

    /**
     * @var MakairaConnect
     */
    private $makairaConnect;

    public function __construct(MakairaConnect $makairaConnect) {
        $this->makRevisionRepo = Shopware()->Models()->getRepository(MakRevision::class);
        
        $this->container = Shopware()->Container();
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

    public function onFrontendDispatch(\Enlight_Controller_ActionEventArgs $args)
    {
        $subject = $args->getSubject();

        $viewPath = $this->makairaConnect->getPath() . '/Resources/Views';

        $subject->View()->addTemplateDir($viewPath);
    }

}