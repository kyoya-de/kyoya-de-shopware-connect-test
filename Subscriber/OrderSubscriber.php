<?php

namespace MakairaConnect\Subscriber;

use Doctrine\DBAL\Connection;
use Doctrine\ORM;
use Enlight\Event\SubscriberInterface;
use Enlight_Event_EventArgs;
use MakairaConnect\Repositories\MakRevisionRepository;
use sOrder;
use function Doctrine\DBAL\Query\QueryBuilder;

class OrderSubscriber implements SubscriberInterface
{
    /**
     * @var MakRevisionRepository
     */
    private $revisionRepository;

    /**
     * @var Connection
     */
    private $db;

    /**
     * @param MakRevisionRepository $revisionRepository
     * @param Connection            $db
     */
    public function __construct(MakRevisionRepository $revisionRepository, Connection $db)
    {
        $this->revisionRepository = $revisionRepository;
        $this->db                 = $db;
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
        $qb = $this->db->createQueryBuilder();
        $qb->select('ad.ordernumber')
            ->from('s_articles', 'a')
            ->from('s_articles_details', 'ad')
            ->where(
                $qb->expr()->andX(
                    $qb->expr()->eq('ad.id', 'a.main_detail_id'),
                    $qb->expr()->eq('a.id', ':articleID')
                )
            );
        /** @var sOrder $entity */
        $entity = $eventArgs->get('subject');
        foreach ($entity->sBasketData['content'] as $basketProduct) {
            if (0 < $basketProduct['articleID']) {
                $this->revisionRepository->addRevision('variant', $basketProduct['ordernumber']);
                $qb->setParameter('articleID', $basketProduct['articleID']);
                $productOrderNo = $qb->execute()->fetchColumn();
                $this->revisionRepository->addRevision('product', $productOrderNo);
            }
        }
    }
}
