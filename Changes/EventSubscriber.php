<?php

namespace MakairaConnect\Changes;

use Enlight\Event\SubscriberInterface;
use Shopware\Models\Article\Article;
use Shopware\Models\Article\Detail;
use Shopware\Models\Article\Supplier;
use Shopware\Models\Category\Category;
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
            'Shopware\Models\Article\Article::postPersist'   => 'onProductSave',
            'Shopware\Models\Article\Article::postUpdate'    => 'onProductSave',
            'Shopware\Models\Article\Article::preRemove'     => 'onProductSave',
            'Shopware\Models\Category\Category::postPersist' => 'onCategorySave',
            'Shopware\Models\Category\Category::postUpdate'  => 'onCategorySave',
            'Shopware\Models\Category\Category::preRemove'   => 'onCategorySave',
            'Shopware\Models\Article\Supplier::postPersist'  => 'onManufacturerSave',
            'Shopware\Models\Article\Supplier::postUpdate'   => 'onManufacturerSave',
            'Shopware\Models\Article\Supplier::preRemove'    => 'onManufacturerSave',
            'Shopware\Models\Article\Detail::postPersist'    => 'onVariantSave',
            'Shopware\Models\Article\Detail::postUpdate'     => 'onVariantSave',
            'Shopware\Models\Article\Detail::preRemove'      => 'onVariantSave',
        ];
    }

    public function onProductSave(\Enlight_Event_EventArgs $event)
    {
        /** @var Article $model */
        $model = $event->get('entity');
        $this->manager->add('product', $model->getId());
    }

    public function onCategorySave(\Enlight_Event_EventArgs $event)
    {
        /** @var Category $model */
        $model = $event->get('entity');
        $this->manager->add('category', $model->getId());
    }

    public function onManufacturerSave(\Enlight_Event_EventArgs $event)
    {
        /** @var Supplier $model */
        $model = $event->get('entity');
        $this->manager->add('manufacturer', $model->getId());
    }

    public function onVariantSave(\Enlight_Event_EventArgs $event)
    {
        /** @var Detail $model */
        $model = $event->get('entity');
        $this->manager->add('variant', $model->getId());
    }
}
