<?php

namespace MakairaConnect\Subscriber;

use Doctrine\Common\EventSubscriber;
use MakairaConnect\Models\MakRevision;

use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Events;

use Shopware\Models\Article\Article;
use Shopware\Models\Article\Detail;
use Shopware\Models\Category\Category;
use Shopware\Models\Article\Supplier;

use MakairaConnect\Models\MakRevision as MakRevisionModel;

class DoctrineSubscriber implements EventSubscriber {
    CONST INSTANCES = [
        //Related to: PRODUCT
        'product' => Article::class,
        'variant' => Detail::class,

        //Related to: CATEGORY
        'category' => Category::class,

        //Related to: MANUFACTURER
        'manufacturer' => Supplier::class
    ];

    /**
     * only add Events::<classes>
     * @return array
     */
    public function getSubscribedEvents() {
        return [
            Events::postUpdate,
            Events::postPersist,
            Events::preRemove,
          ];
    }

    /**
     * @param LifecycleEventArgs $arguments
     */
    public function postPersist(LifecycleEventArgs $arguments) {
        $this->generateRevisionEntry('create', $arguments);
    }

    /**
     * @param LifecycleEventArgs $arguments
     */
    public function postUpdate(LifecycleEventArgs $arguments) {
        $this->generateRevisionEntry('update', $arguments);
    }

    /**
     * @param LifecycleEventArgs $arguments
     */
    public function preRemove(LifecycleEventArgs $arguments) {
        $this->generateRevisionEntry('delete', $arguments);
    }

    /**
     * @param $method string
     * @param $arguments LifecycleEventArgs
     */
    private function generateRevisionEntry($method, $arguments) {
        $entity = $arguments->getEntity();
        $type = $this->checkInstance($entity);
        if(!$type) {
            return;
        }

        /** @var Article|Detail|Category|Supplier $entity */

        $makRevisionRepo = Shopware()->Models()->getRepository(MakRevisionModel::class);
        $makRevisionRepo->addRevision($type, $entity->getId());
    }

    /**
     * @param $entity Object
     * @return bool|string
     */
    private function checkInstance($entity) {
        foreach (self::INSTANCES as $type => $instance) {
            if($entity instanceof $instance) {
                return $type;
            }
        }

        return false;
    }
}
