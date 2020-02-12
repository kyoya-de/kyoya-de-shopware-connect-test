<?php

namespace MakairaConnect\Repositories;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Doctrine\ORM\Query\Expr\OrderBy;
use Enlight_Hook;
use MakairaConnect\Models\MakRevision as MakRevisionModel;

/**
 * MakRevisionRepository
 */
class MakRevisionRepository extends EntityRepository implements Enlight_Hook
{
    /**
     * @param $type
     * @param $objectId
     *
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function addRevision($type, $objectId)
    {
        $data = ['type' => $type, 'id' => $objectId];

        /** @var MakRevisionModel $revision */
        $revision = $this->findOneBy($data);

        if ($revision) {
            $this->_em->remove($revision);
            $this->_em->flush();
        }

        $revision = new MakRevisionModel();
        $revision->fromArray($data);
        $this->_em->persist($revision);
        $this->_em->flush();
    }

    /**
     * @param int $lastRev
     * @param int $count
     *
     * @return MakRevisionModel[]
     */
    public function getRevisions($lastRev, $count = 50)
    {
        $query = $this->createQueryBuilder('revisions')
            ->select()
            ->where('revisions.sequence > :lastRev')
            ->setParameter('lastRev', $lastRev)
            ->setMaxResults($count)
            ->orderBy('revisions.sequence', 'ASC')
            ->getQuery();

        return $query->getResult();
    }

    public function countSince($lastRev)
    {
        $qb = $this->createQueryBuilder('revs');
        $query = $qb->select('COUNT(revs.sequence)')
            ->where('revs.sequence > :lastRev')
            ->setParameter('lastRev', $lastRev)
            ->setMaxResults(1)
            ->getQuery();

        return (int) $query->getSingleScalarResult();
    }
}
