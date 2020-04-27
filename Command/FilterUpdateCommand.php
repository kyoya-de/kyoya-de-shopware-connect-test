<?php

namespace MakairaConnect\Command;

use Doctrine\ORM\EntityManagerInterface;
use MakairaConnect\Client\ApiInterface;
use MakairaConnect\Search\Facet\MakairaFacet;
use MakairaConnect\Service\UpdateFilters;
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
    private $filterUpdater;

    public function __construct(UpdateFilters $filterUpdater)
    {
        parent::__construct('makaira:filter:update');

        $this->filterUpdater = $filterUpdater;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->filterUpdater->update();
    }
}
