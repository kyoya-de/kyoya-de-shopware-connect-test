<?php

namespace MakairaConnect\Command;

use Makaira\Constraints;
use Makaira\Query;
use MakairaConnect\Client\ApiInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use function ob_get_clean;

class SearchCommand extends Command
{
    private $api;

    public function __construct(ApiInterface $api)
    {
        parent::__construct('makaira:search');

        $this->api = $api;
    }

    protected function configure()
    {
        // @formatter:off
        $this
            ->addArgument('shopId', InputArgument::REQUIRED, 'Id of the shop.')
            ->addArgument('locale', InputArgument::REQUIRED, 'Locale the language.')
            ->addArgument('searchTerm', InputArgument::REQUIRED, 'Term used to search.')
            ->addOption('debug', 'd', InputOption::VALUE_NONE, 'Enable Makaira request tracing.');
        // @formatter:on
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $query               = new Query();
        $query->searchPhrase = $input->getArgument('searchTerm');
        $query->isSearch     = true;
        $query->count        = 50;
        $query->offset       = 0;
        $query->fields       = ['title'];

        $query->constraints[Constraints::SHOP]      = $input->getArgument('shopId');
        $query->constraints[Constraints::LANGUAGE]  = $input->getArgument('locale');
        $query->constraints[Constraints::USE_STOCK] = true;

        ob_start();
        $result = $this->api->search($query, $input->getOption('debug') ? 'true' : '');
        var_dump($result);
        $output->writeln(ob_get_clean());

        return 0;
    }
}
