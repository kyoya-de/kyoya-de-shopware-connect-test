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
use MakairaConnect\Repositories\MakRevisionRepository;
use Monolog\Logger;
use Shopware\Components\Model\ModelEntity;
use Shopware\Models\Article\Article;
use Shopware\Models\Article\Detail;
use Shopware\Models\Article\Supplier;
use Shopware\Models\Attribute\Article as AttributeArticle;
use Shopware\Models\Category\Category;
use Shopware\Models\Order\Order;
use Shopware\Models\Order\Detail as OrderDetail;
use function array_unique;
use function Shopware;

class DoctrineSubscriber implements EventSubscriber
{
    /**
     * @const array<string, string>
     */
    const INSTANCES = [
        Article::class  => 'product',
        Detail::class   => 'variant',
        Category::class => 'category',
        Supplier::class => 'manufacturer',
    ];

    /**
     * @var MakRevisionRepository
     */
    private static $revisionRepository;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @param Logger $logger
     */
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
            Events::preUpdate,
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
        /** @var ModelEntity $entity */
        $entity = $arguments->getEntity();
        $this->generateRevisionEntry($entity);
    }

    /**
     * @param ModelEntity $entity
     *
     * @throws ORMException
     * @throws OptimisticLockException
     */
    private function generateRevisionEntry(ModelEntity $entity)
    {
        if ($type = $this->checkInstance($entity)) {
            $id = $entity->getId();
            $entityId = $entity->getId();
            if ($entity instanceof Detail) {
                $id = $entity->getNumber();
            }

            if ($entity instanceof Article) {
                $id = $entity->getMainDetail()->getNumber();
            }

            $this->getRevisionRepository()->addRevision($type, (string) $id, $entityId);
        }
    }

    /**
     * @param $entity Object
     *
     * @return bool|string
     */
    private function checkInstance($entity)
    {
        foreach (self::INSTANCES as $entityClass => $type) {
            if ($entity instanceof $entityClass) {
                return $type;
            }
        }

        return false;
    }

    /**
     * @param LifecycleEventArgs $arguments
     *
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function postUpdate(LifecycleEventArgs $arguments)
    {
        /** @var ModelEntity $entity */
        $entity = $arguments->getEntity();
        if ($entity instanceof Article) {
            $this->generateRevisionEntry($entity->getMainDetail());
        }
        $this->generateRevisionEntry($entity);
    }

    /**
     * @param LifecycleEventArgs $arguments
     *
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function preRemove(LifecycleEventArgs $arguments)
    {
        /** @var ModelEntity $entity */
        $entity = $arguments->getEntity();

        if ($entity instanceof AttributeArticle) {
            $this->generateRevisionEntry($entity->getArticleDetail());
        }

        if ($entity instanceof Article) {
            $this->generateRevisionEntry($entity->getMainDetail());
        }

        if ($entity instanceof Detail) {
            $this->generateRevisionEntry($entity->getArticle());
        }

        $this->generateRevisionEntry($entity);
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

            // Add product revisions
            /** @var EntityRepository $productRepo */
            $productRepo = Shopware()->Models()->getRepository(Article::class);
            $products = $productRepo->findBy([
                'id' => array_unique($articleIds),
            ]);
            foreach ($products as $product) {
                /** @var Article $product */
                $this->generateRevisionEntry($product->getMainDetail());
                $this->generateRevisionEntry($product);
            }

            // Add variant revisions
            /** @var EntityRepository $detailRepo */
            $detailRepo = Shopware()->Models()->getRepository(Detail::class);
            $variants = $detailRepo->findBy([
                'number' => array_unique($articleNumbers),
            ]);
            foreach ($variants as $variant) {
                /**@var Detail $variant */
                $this->generateRevisionEntry($variant);
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

    /**
     * @return EntityRepository|\Doctrine\Persistence\ObjectRepository|MakRevisionRepository
     */
    private function getRevisionRepository()
    {
        if (null === self::$revisionRepository) {
            self::$revisionRepository = Shopware()->Models()->getRepository(MakRevision::class);
        }

        return self::$revisionRepository;
    }
}
