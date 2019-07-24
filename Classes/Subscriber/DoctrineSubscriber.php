<?php

namespace MakairaConnect\Classes\Subscriber;

use Doctrine\Common\EventSubscriber;
use MakairaConnect\Classes\Models\MakRevision;

use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Events;

use Shopware\Models\Article\Article;
use Shopware\Models\Article\Detail;
use Shopware\Models\Category\Category;
use Shopware\Models\Article\Supplier;

use MakairaConnect\Classes\Models\MakRevision as MakRevisionModel;

class DoctrineSubscriber implements EventSubscriber {
    CONST INSTANCES = [
        Article::class,
        Category::class,
        Supplier::class
    ];

    /**
     * only add Events::<classes>
     * @return array
     */
    public function getSubscribedEvents() {
        return [
            Events::postUpdate,
            //Events::postPersist,
            //Events::postRemove,
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
    public function postRemove(LifecycleEventArgs $arguments) {
        $this->generateRevisionEntry('delete', $arguments);
    }

    /**
     * @param $method string
     * @param $arguments LifecycleEventArgs
     */
    private function generateRevisionEntry($method, $arguments) {
        if(!$this->checkIntance($arguments)) {
            return;
        }

        $makRevisionRepo = Shopware()->Models()->getRepository(MakRevisionModel::class);
        $makRevisionRepo->addRevision('product', 1);
    }

    /**
     * @param $arguments LifecycleEventArgs
     * @return bool
     */
    private function checkIntance($arguments) {
        $model = $arguments->getEntity();
        foreach (self::INSTANCES as $instance) {
            if($model instanceof $instance) {
                return true;
            }
        }

        return false;
    }
}
