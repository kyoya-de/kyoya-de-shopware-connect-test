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

/**
 * MakRevisionRepository
 */
class MakRevisionRepository extends EntityRepository implements Enlight_Hook
{
    private const DOCTRINE_REFRESH_FREQUENCY = 500;

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
     * @param string $type
     * @param ModelEntity[] $objects
     *
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function addRevisions(string $type, $objects): void
    {
        $objectIds = [];
        foreach ($objects as $object) {
            $objectIds = array_map(
                static function ($object) use ($type) {
                    if ('product' === $type && $object instanceof Article) {
                        return $object->getMainDetail()->getNumber();
                    }

                    if ('variant' === $type && $object instanceof Detail) {
                        return $object->getNumber();
                    }

                    return $object->getId();
                },
                $objects
            );
        }

        $qb = $this->createQueryBuilder('mr');
        /** @var MakRevisionModel[] $revisions */
        $revisions = $qb->select()->where($qb->expr()->in('mr.id', $objectIds))->getQuery()->getResult();

        $i = 0;

        foreach ($revisions as $revision) {
            $this->_em->remove($revision);
            if (0 === ($i % self::DOCTRINE_REFRESH_FREQUENCY)) {
                $this->_em->flush();
            }

            $i++;
        }

        $this->_em->flush();

        $i = 0;
        foreach ($objectIds as $objectId) {
            $revision = (new MakRevisionModel())->setType($type)->setId($objectId);

            $this->_em->persist($revision);
            if (0 === ($i % self::DOCTRINE_REFRESH_FREQUENCY)) {
                $this->_em->flush();
            }

            $i++;
        }

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
        $query = $this->createQueryBuilder('revisions')->select()->where('revisions.sequence > :lastRev')->setParameter(
            'lastRev',
            $lastRev
        )->setMaxResults($count)->orderBy('revisions.sequence', 'ASC')->getQuery();

        return $query->getResult();
    }

    public function countSince($lastRev)
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
