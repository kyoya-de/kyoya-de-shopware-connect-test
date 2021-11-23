<?php

namespace MakairaConnect\Subscriber;

use Doctrine\ORM;
use Enlight\Event\SubscriberInterface;
use Enlight_Event_EventArgs;
use MakairaConnect\Repositories\MakRevisionRepository;
use Shopware_Proxies_sOrderProxy;

class OrderSubscriber implements SubscriberInterface
{
    /**
     * @var MakRevisionRepository
     */
    private $revisionRepository;

    /**
     * @param MakRevisionRepository $revisionRepository
     */
    public function __construct(MakRevisionRepository $revisionRepository)
    {
        $this->revisionRepository = $revisionRepository;
    }

    /**
     * @return string[]
     */
    public static function getSubscribedEvents()
    {
        return [
            'Shopware_Modules_Order_SaveOrder_OrderCreated' => 'onOrderCreated',
        ];
    }

    /**
     * @param Enlight_Event_EventArgs $eventArgs
     *
     * @throws ORM\ORMException
     * @throws ORM\OptimisticLockException
     */
    public function onOrderCreated(Enlight_Event_EventArgs $eventArgs)
    {
        /** @var Shopware_Proxies_sOrderProxy $entity */
        $entity = $eventArgs->get('subject');
        foreach ($entity->sBasketData['content'] as $basketProduct) {
            $this->revisionRepository->addRevision('variant', $basketProduct['ordernumber']);
            $this->revisionRepository->addRevision('product', $basketProduct['ordernumber']);
        }
    }
}
