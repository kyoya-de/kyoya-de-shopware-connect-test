<?php

namespace MakairaConnect\Classes\Repositories;

use Doctrine\ORM\EntityRepository;

/**
 * MakRevisionRepository
 */
class MakRevisionRepository extends EntityRepository {

  /**
   * @param $type
   * @param $objectId
   * @throws \Doctrine\ORM\OptimisticLockException
   */
  public function addRevision($type, $objectId) {
    $data = ['type' => $type, 'id' => $objectId];

    /** @var \MakairaConnect\Classes\Models\MakRevision $revision */
    $revision = $this->findOneBy($data);

    if($revision) {
      $this->_em->remove($revision);
      $this->_em->flush();
    }

    $revision = new $this->_class();
    $revision->fromArray($data);
    $this->_em->persist($revision);
    $this->_em->flush();
  }

  /**
   * @param $lastRev
   * @param int $count
   *
   * @return \MakairaConnect\Classes\Models\MakRevision[]
   */
  public function getRevisions($lastRev, $count = 50) {
    $query = $this->createQueryBuilder('revisions')
      ->select()
      ->where('revisions.sequence > :lastRev')
      ->setParameter('lastRev', $lastRev)
      ->setMaxResults($count)
      ->orderBy('revisions.sequence', 'ASC')
      ->getQuery();

    return $query->getResult();
  }
}
