<?php

namespace MakairaConnect\Command;

use Doctrine\ORM\EntityManagerInterface;
use MakairaConnect\Client\ApiInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use function get_class;
use function ob_get_clean;

class FilterUpdateCommand extends Command
{
    private $api;

    private $doctrine;

    public function __construct(ApiInterface $api, EntityManagerInterface $doctrine)
    {
        parent::__construct('makaira:filter:update');

        $this->api = $api;
        $this->doctrine = $doctrine;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $filter = $this->api->fetchFilter();

        ob_start();
        var_dump($filter);
        $output->writeln(ob_get_clean());
    }
}
