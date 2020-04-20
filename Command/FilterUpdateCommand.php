<?php

namespace MakairaConnect\Command;

use Doctrine\ORM\EntityManagerInterface;
use MakairaConnect\Client\ApiInterface;
use MakairaConnect\Search\Facet\MakairaFacet;
use Shopware\Bundle\SearchBundle\Facet\ProductAttributeFacet;
use Shopware\Components\Model\ModelRepository;
use Shopware\Models\Search\CustomFacet;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use function json_encode;
use function ksort;
use function ob_get_clean;

class FilterUpdateCommand extends Command
{
    private $api;

    private $doctrine;

    public function __construct(ApiInterface $api, EntityManagerInterface $doctrine)
    {
        parent::__construct('makaira:filter:update');

        $this->api      = $api;
        $this->doctrine = $doctrine;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $filter = $this->api->fetchFilter();

        /** @var ModelRepository $repo */
        $repo = $this->doctrine->getRepository(CustomFacet::class);

        $customFacetClass = CustomFacet::class;
        $this->doctrine
            ->createQuery("UPDATE {$customFacetClass} c SET c.active = 0 WHERE c.uniqueKey LIKE 'makaira_%'")
            ->execute();

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

    private function getFacetMode($filterItem)
    {
        $filterType = $filterItem['type'];

        if ('script' === $filterType) {
            return ProductAttributeFacet::MODE_BOOLEAN_RESULT;
        }

        if (0 === strpos($filterType, 'range_')) {
            return ProductAttributeFacet::MODE_RANGE_RESULT;
        }

        if (0 === strpos($filterType, 'list_multiselect')) {
            return ProductAttributeFacet::MODE_VALUE_LIST_RESULT;
        }

        return ProductAttributeFacet::MODE_RADIO_LIST_RESULT;
    }
}
