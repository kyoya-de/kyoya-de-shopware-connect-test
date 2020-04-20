<?php

namespace MakairaConnect\Command;

use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use MakairaConnect\Models\MakRevision;
use MakairaConnect\Repositories\MakRevisionRepository;
use Shopware\Commands\ShopwareCommand;
use Shopware\Models\Article\Article;
use Shopware\Models\Article\Detail;
use Shopware\Models\Article\Supplier;
use Shopware\Models\Category\Category;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use function count;

class TouchAllCommand extends ShopwareCommand
{
    /**
     *
     */
    protected function configure()
    {
        $this->setName('makaira:touch-all');
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return int|void|null
     * @throws ORMException
     * @throws OptimisticLockException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $doctrine = $this->getContainer()->get('models');

        /** @var MakRevisionRepository $revisionRepo */
        $revisionRepo     = $doctrine->getRepository(MakRevision::class);
        $productRepo      = $doctrine->getRepository(Article::class);
        $variantRepo      = $doctrine->getRepository(Detail::class);
        $categoryRepo     = $doctrine->getRepository(Category::class);
        $supplierRepo = $doctrine->getRepository(Supplier::class);

        /** @var Article[] $products */
        $products = $productRepo->findAll();
        $productCount = count($products);
        $output->writeln("Adding {$productCount} products.");
        $revisionRepo->addRevisions('product', $products);

        /** @var Detail[] $variants */
        $variants = $variantRepo->findAll();
        $variantCount = count($variants);
        $output->writeln("Adding {$variantCount} variants.");
        $revisionRepo->addRevisions('variant', $variants);

        /** @var Category[] $categories */
        $categories = $categoryRepo->findAll();
        $categoryCount = count($categories);
        $output->writeln("Adding {$categoryCount} categories.");
        $revisionRepo->addRevisions('category', $categories);

        /** @var Supplier[] $suppliers */
        $suppliers = $supplierRepo->findAll();
        $supplierCount = count($suppliers);
        $output->writeln("Adding {$supplierCount} suppliers.");
        $revisionRepo->addRevisions('manufacturer', $suppliers);

        $output->writeln('Done');
    }

}
