<?php

namespace MakairaConnect\Subscriber;

use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\OptimisticLockException;
use MakairaConnect\Models\MakRevision as MakRevisionModel;
use Shopware\Models\Article\Article;
use Shopware\Models\Article\Detail;
use Shopware\Models\Article\Supplier;
use Shopware\Models\Category\Category;
use function get_class;
use function Shopware;

class DoctrineSubscriber implements EventSubscriber
{
    const INSTANCES = [
        Article::class  => 'product',
        Detail::class   => 'variant',
        Category::class => 'category',
        Supplier::class => 'manufacturer',
    ];

    /**
     * only add Events::<classes>
     *
     * @return array
     */
    public function getSubscribedEvents()
    {
        return [
            Events::postUpdate,
            Events::postPersist,
            Events::preRemove,
        ];
    }

    /**
     * @param LifecycleEventArgs $arguments
     *
     * @throws OptimisticLockException
     */
    public function postPersist(LifecycleEventArgs $arguments)
    {
        $this->generateRevisionEntry($arguments);
    }

    /**
     * @param $method    string
     * @param $arguments LifecycleEventArgs
     *
     * @throws OptimisticLockException
     */
    private function generateRevisionEntry($arguments)
    {
        $entity = $arguments->getEntity();
        if ($type = $this->checkInstance($entity)) {
            $id = $entity->getId();
            if ($entity instanceof Detail) {
                $id = $entity->getNumber();
            }

            if ($entity instanceof Article) {
                $id = $entity->getMainDetail()->getNumber();
            }

            $makRevisionRepo = Shopware()->Models()->getRepository(MakRevisionModel::class);
            $makRevisionRepo->addRevision($type, $id);
        }
    }

    /**
     * @param $entity Object
     *
     * @return bool|string
     */
    private function checkInstance($entity)
    {
        return self::INSTANCES[get_class($entity)] ?? false;
    }

    /**
     * @param LifecycleEventArgs $arguments
     *
     * @throws OptimisticLockException
     */
    public function postUpdate(LifecycleEventArgs $arguments)
    {
        $this->generateRevisionEntry($arguments);
    }

    /**
     * @param LifecycleEventArgs $arguments
     *
     * @throws OptimisticLockException
     */
    public function preRemove(LifecycleEventArgs $arguments)
    {
        $this->generateRevisionEntry($arguments);
    }
}
