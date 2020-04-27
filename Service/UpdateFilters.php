<?php

namespace MakairaConnect\Service;

use Doctrine\ORM\EntityManagerInterface;
use MakairaConnect\Client\ApiInterface;
use MakairaConnect\Search\Facet\MakairaFacet;
use Shopware\Components\Model\ModelRepository;
use Shopware\Models\Search\CustomFacet;
use function get_class;

class UpdateFilters
{
    private $api;

    private $doctrine;

    public function __construct(ApiInterface $api, EntityManagerInterface $doctrine)
    {
        $this->api      = $api;
        $this->doctrine = $doctrine;
    }

    public function update(): void
    {
        $filter = $this->api->fetchFilter();

        /** @var ModelRepository $repo */
        $repo = $this->doctrine->getRepository(CustomFacet::class);

        $this->disable();

        foreach ($filter['filter'] as $filterItem) {
            $facet = new MakairaFacet(
                $filterItem['type'],
                $filterItem['key'],
                $filterItem['title'],
                "makairaFilter_{$filterItem['key']}",
                $filterItem['showCount']
            );

            $uniqueKey   = "makaira_{$filterItem['key']}";
            $customFacet = $repo->findOneBy(['uniqueKey' => $uniqueKey]) ?? new CustomFacet();

            $customFacet->setActive($filterItem['active']);
            $customFacet->setDeletable(false);
            $customFacet->setDisplayInCategories(true);
            $customFacet->setName($filterItem['title']);
            $customFacet->setPosition($filterItem['position']);
            $customFacet->setUniqueKey($uniqueKey);
            $customFacet->setFacet(json_encode([get_class($facet) => $facet]));

            $this->doctrine->persist($customFacet);
        }

        $this->doctrine->flush();

        $qb = $repo->createQueryBuilder('cf');
        $qb->where(
            $qb->expr()->andX(
                $qb->expr()->like('cf.uniqueKey', "'makaira_%'"),
                $qb->expr()->eq('cf.active', 0)
            )
        );

        foreach ($qb->getQuery()->execute() as $customFacet) {
            $this->doctrine->remove($customFacet);
        }
        $this->doctrine->flush();
    }

    public function remove()
    {
        $customFacetClass = CustomFacet::class;
        $this->doctrine
            ->createQuery("DELETE FROM {$customFacetClass} c WHERE c.uniqueKey LIKE 'makaira_%'")
            ->execute();
    }

    public function disable()
    {
        $this->setActive(false);
    }

    public function enable()
    {
        $this->setActive(true);
    }

    public function setActive(bool $active = true)
    {
        $dqlActive = $active ? 1 : 0;
        $customFacetClass = CustomFacet::class;
        $this->doctrine
            ->createQuery("UPDATE {$customFacetClass} c SET c.active = {$dqlActive} WHERE c.uniqueKey LIKE 'makaira_%'")
            ->execute();
    }
}
