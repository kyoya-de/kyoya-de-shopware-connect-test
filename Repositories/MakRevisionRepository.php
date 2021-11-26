<?php

namespace MakairaConnect\Repositories;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Enlight_Hook;
use MakairaConnect\Models\MakRevision as MakRevisionModel;
use Shopware\Components\Model\ModelEntity;
use Shopware\Models\Article\Article;
use Shopware\Models\Article\Detail;
use function array_map;
use function in_array;

/**
 * MakRevisionRepository
 */
class MakRevisionRepository extends EntityRepository implements Enlight_Hook
{
    private const DOCTRINE_REFRESH_FREQUENCY = 500;

    /**
     * @param string $type
     * @param string $objectId
     *
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function addRevision(string $type, string $objectId, string $entityId = null)
    {
        $statement = $this->_em
            ->getConnection()
            ->prepare(
                'REPLACE INTO mak_revision (`type`, `id`, `entity_id`, `changed`) VALUES (:type, :id, :entityId, NOW())'
            );
        $statement->execute(['type' => $type, 'id' => $objectId, 'entityId' => $entityId]);
    }

    /**
     * @param string        $type
     * @param ModelEntity[] $objects
     *
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function addRevisions(string $type, array $objects): void
    {
        $objectIds = [];
        foreach ($objects as $object) {
            try {
                $id = $object->getId();
                if ('product' === $type && $object instanceof Article) {
                    $id = $object->getMainDetail()->getNumber();
                }

                if ('variant' === $type && $object instanceof Detail) {
                    $id = $object->getNumber();
                }

                $objectIds[$object->getId()] = $id;
            } catch (ORMException $e) {
                // Skip erroneous article.
            }
        }

        $statement = $this->_em
            ->getConnection()
            ->prepare(
                'REPLACE INTO mak_revision (`type`, `id`, `entity_id`, `changed`) VALUES (:type, :id, :entityId, NOW())'
            );

        foreach ($objectIds as $entityId => $objectId) {
            $statement->execute(['type' => $type, 'id' => $objectId, 'entityId' => $entityId]);
        }

        $this->_em->flush();
    }

    /**
     * @param int $lastRev
     * @param int $count
     *
     * @return MakRevisionModel[]
     */
    public function getRevisions(int $lastRev, int $count = 50): array
    {
        $query = $this->createQueryBuilder('revisions')->select()->where('revisions.sequence > :lastRev')->setParameter(
            'lastRev',
            $lastRev
        )->setMaxResults($count)->orderBy('revisions.sequence', 'ASC')->getQuery();

        return $query->getResult();
    }

    /**
     * @param $lastRev
     *
     * @return int
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function countSince($lastRev): int
    {
        $qb    = $this->createQueryBuilder('revs');
        $query =
            $qb->select('COUNT(revs.sequence)')
                ->where('revs.sequence > :lastRev')
                ->setParameter('lastRev', $lastRev)
                ->setMaxResults(1)
                ->getQuery();

        return (int) $query->getSingleScalarResult();
    }
}
