<?php

namespace MakairaConnect\Subscriber;

use Enlight\Event\SubscriberInterface;
use MakairaConnect\MakairaConnect;

class ShopwareSubscriber implements SubscriberInterface 
{
    /**
     * @var MakairaConnect
     */
    private $makairaConnect;

    /**
     * @param MakairaConnect $makairaConnect
     */
    public function __construct(MakairaConnect $makairaConnect)
    {
        $this->makairaConnect = $makairaConnect;
    }

    /**
    * for all events like 'Shopware_Modules_Basket_AddArticle_Start'
    * @return array
    */
    public static function getSubscribedEvents(): array {
        return [
            'Enlight_Controller_Action_PostDispatchSecure_Frontend' => 'onFrontendDispatch'
        ];
    }

    public function onFrontendDispatch(\Enlight_Controller_ActionEventArgs $args)
    {
        $subject = $args->getSubject();

        $viewPath = $this->makairaConnect->getPath() . '/Resources/Views';

        $subject->View()->addTemplateDir($viewPath);
    }

}
