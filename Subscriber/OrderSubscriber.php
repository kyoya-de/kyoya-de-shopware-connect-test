<?php

namespace MakairaConnect\Subscriber;

use Doctrine\DBAL\Connection;
use Doctrine\ORM;
use Enlight\Event\SubscriberInterface;
use Enlight_Event_EventArgs;
use MakairaConnect\Repositories\MakRevisionRepository;
use PDO;
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
        // Create doctrine query to fetch the ordernumber of the main product details (also known as "parent").
        $qb = $this->db->createQueryBuilder();
        $qb->select('ad.ordernumber', 'a.id')
            ->from('s_articles', 'a')
            ->from('s_articles_details', 'ad')
            ->where(
                $qb->expr()->andX(
                    $qb->expr()->eq('ad.id', 'a.main_detail_id'),
                    $qb->expr()->eq('a.id', ':articleID')
                )
            );

        /** @var sOrder $order */
        $order = $eventArgs->get('subject');
        foreach ($order->sBasketData['content'] as $basketProduct) {
            // Skip virtual products like discounts.
            if (0 < $basketProduct['articleID']) {
                $this->revisionRepository->addRevision(
                    'variant',
                    (string) $basketProduct['ordernumber'],
                    (int) $basketProduct['articleDetailId']
                );

                $qb->setParameter('articleID', $basketProduct['articleID']);
                $productInfo = $qb->execute()->fetch(PDO::FETCH_ASSOC);
                $this->revisionRepository->addRevision(
                    'product',
                    (string) $productInfo['ordernumber'],
                    (int) $basketProduct['articleID']
                );
            }
        }
    }
}
