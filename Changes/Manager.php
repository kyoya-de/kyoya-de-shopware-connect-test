<?php

namespace MakairaConnect\Changes;

use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Statement;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Query\Expr\Andx;
use MakairaConnect\Models\ConnectChange;

class Manager
{
    /**
     * @var EntityManager
     */
    private $entityManager;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var Statement
     */
    private $addStatement;

    public function __construct(EntityManager $entityManager, Connection $connection)
    {
        $this->entityManager = $entityManager;
        $this->connection    = $connection;
    }

    public function add($type, $objectId)
    {
        if (null === $this->addStatement) {
            $this->addStatement = $this->connection->prepare(
                'REPLACE INTO `mak_connect_changes` (`id`, `type`, `changed`) VALUES (?, ?, NOW())'
            );
        }

        $this->addStatement->execute([$objectId, $type]);
    }

    /**
     * @param int $lastRev
     * @param int $count
     *
     * @return ConnectChange[]
     */
    public function getChanges($lastRev, $count = 50)
    {
        $repo = $this->entityManager->getRepository(ConnectChange::class);
        $query = $repo->createQueryBuilder('cc')
            ->select()
            ->where('cc.sequence > :lastRev')
            ->setParameter('lastRev', $lastRev)
            ->setMaxResults($count)
            ->orderBy('cc.sequence', 'ASC')
            ->getQuery();

        return $query->getResult();
    }
}
