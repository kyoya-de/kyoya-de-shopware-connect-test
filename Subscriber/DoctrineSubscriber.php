<?php

namespace MakairaConnect\Subscriber;

use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Exception;
use MakairaConnect\Models\MakRevision;
use MakairaConnect\Models\MakRevision as MakRevisionModel;
use MakairaConnect\Repositories\MakRevisionRepository;
use Monolog\Logger;
use Shopware\Models\Article\Article;
use Shopware\Models\Article\Detail;
use Shopware\Models\Article\Supplier;
use Shopware\Models\Category\Category;
use Shopware\Models\Order\Order;
use Shopware\Models\Order\Detail as OrderDetail;
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

    private Logger $logger;

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }

    /**
     * only add Events::<classes>
     *
     * @return array
     */
    public function getSubscribedEvents(): array
    {
        return [
            Events::postUpdate,
            Events::postPersist,
            Events::preRemove,
            Events::preUpdate
        ];
    }

    /**
     * @param LifecycleEventArgs $arguments
     *
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function postPersist(LifecycleEventArgs $arguments)
    {
        $this->generateRevisionEntry($arguments);
    }

    /**
     * @param $arguments LifecycleEventArgs
     *
     * @throws OptimisticLockException
     * @throws ORMException
     */
    private function generateRevisionEntry(LifecycleEventArgs $arguments)
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
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function postUpdate(LifecycleEventArgs $arguments)
    {
        $this->generateRevisionEntry($arguments);
    }

    /**
     * @param LifecycleEventArgs $arguments
     *
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function preRemove(LifecycleEventArgs $arguments)
    {
        $this->generateRevisionEntry($arguments);
    }

    /**
     * @param Order $order
     */
    private function handleOrderStatusChange(Order $order)
    {
        try {
            $articleIds = [];
            $articleNumbers = [];
            foreach ($order->getDetails() as $detail) {
                /**@var OrderDetail $detail */
                $articleIds[] = $detail->getArticleId();
                $articleNumbers[] = $detail->getArticleNumber();
            }

            /**@var MakRevisionRepository $revisionRepo */
            $revisionRepo = Shopware()->Models()->getRepository(MakRevision::class);

            // Add product revisions
            /**@var EntityRepository $productRepo */
            $productRepo = Shopware()->Models()->getRepository(Article::class);
            $products = $productRepo->findBy([
                'id' => array_unique($articleIds)
            ]);
            foreach ($products as $product) {
                /**@var Article $product */
                $revisionRepo->addRevision('product', $product->getMainDetail()->getNumber());
            }

            // Add variant revisions
            /**@var EntityRepository $detailRepo */
            $detailRepo = Shopware()->Models()->getRepository(Detail::class);
            $variants = $detailRepo->findBy([
                'number' => array_unique($articleNumbers)
            ]);
            foreach ($variants as $variant) {
                /**@var Detail $variant */
                $revisionRepo->addRevision('variant', $variant->getNumber());
            }
        } catch (Exception $e) {
            $this->logger->error('[DoctrineSubscriber][handleOrderStatusChange] Error. Message: ' . $e->getMessage());
        }
    }

    public function preUpdate(PreUpdateEventArgs $arguments)
    {
        $entity = $arguments->getEntity();
        if ($entity instanceof Order && $arguments->hasChangedField('orderStatus')) {
            $this->handleOrderStatusChange($entity);
        }
    }
}
