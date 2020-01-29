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

        $progress = new ProgressBar($output);
        $progress->setRedrawFrequency(10);
        $progress->setBarWidth(120);

        /** @var Article[] $products */
        $products = $productRepo->findAll();
        $productCount = count($products);
        $output->writeln("Adding {$productCount} products.");
        $progress->start($productCount);
        foreach ($products as $product) {
            $progress->advance();
            $revisionRepo->addRevision('product', $product->getMainDetail()->getNumber());
        }
        $progress->finish();

        /** @var Detail[] $variants */
        $variants = $variantRepo->findAll();
        $variantCount = count($variants);
        $output->writeln("\n\nAdding {$productCount} variants.");
        $progress->start($variantCount);
        foreach ($variants as $variant) {
            $progress->advance();
            $revisionRepo->addRevision('variant', $variant->getNumber());
        }
        $progress->finish();

        /** @var Category[] $categories */
        $categories = $categoryRepo->findAll();
        $categoryCount = count($categories);
        $output->writeln("\n\nAdding {$categoryCount} categories.");
        $progress->start($categoryCount);
        foreach ($categories as $category) {
            $progress->advance();
            $revisionRepo->addRevision('category', $category->getId());
        }
        $progress->finish();

        /** @var Supplier[] $suppliers */
        $suppliers = $supplierRepo->findAll();
        $supplierCount = count($suppliers);
        $output->writeln("\n\nAdding {$supplierCount} suppliers.");
        $progress->start($supplierCount);
        foreach ($suppliers as $supplier) {
            $progress->advance();
            $revisionRepo->addRevision('manufacturer', $supplier->getId());
        }
        $progress->finish();

        $output->writeln("\n\nDone");
    }

}
