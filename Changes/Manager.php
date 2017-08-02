<?php

namespace MakairaConnect\Changes;

use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Statement;
use Doctrine\ORM\EntityManager;

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
}
