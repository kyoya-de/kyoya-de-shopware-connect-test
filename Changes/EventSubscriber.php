<?php

namespace MakairaConnect\Changes;

use Enlight\Event\SubscriberInterface;
use Shopware\Models\Article\Article;
use Symfony\Component\EventDispatcher\Event;

class EventSubscriber implements SubscriberInterface
{
    private $manager;

    public function __construct(Manager $manager)
    {
        $this->manager = $manager;
    }

    public static function getSubscribedEvents()
    {
        return [
            'Shopware\Models\Article\Article::postPersist' => 'onProductSave',
            'Shopware\Models\Article\Article::postUpdate'  => 'onProductSave',
            'Shopware\Models\Article\Article::postRemove'  => 'onProductSave',
        ];
    }

    public function onProductSave(\Enlight_Event_EventArgs $event)
    {
        /** @var Article $model */
        $model = $event->get('entity');
        $this->manager->add('product', $model->getId());
    }
}
