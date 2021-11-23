<?php

namespace MakairaConnect\Search\Sorting;

use Shopware\Bundle\SearchBundle\Condition\CategoryCondition;
use Shopware\Bundle\SearchBundle\Criteria;
use Shopware\Bundle\SearchBundle\Sorting;
use Shopware\Bundle\SearchBundle\SortingInterface;
use Shopware\Bundle\StoreFrontBundle\Struct\ShopContextInterface;
use function reset;

class SortingParser implements SortingParserInterface
{
    /**
     * @param array                $sortResult
     * @param SortingInterface     $sorting
     * @param Criteria             $criteria
     * @param ShopContextInterface $context
     */
    public function parseSorting(
        array &$sortResult,
        SortingInterface $sorting,
        Criteria $criteria,
        ShopContextInterface $context
    ): void {
        $name = false;

        if ($sorting instanceof Sorting\PopularitySorting) {
            $name = 'popularity';
        }

        if ($sorting instanceof Sorting\ManualSorting && $criteria->hasBaseCondition('category')) {
            /** @var CategoryCondition $categoryCondition */
            $categoryCondition = $criteria->getBaseCondition('category');
            $categoryIds       = $categoryCondition->getCategoryIds();
            $categoryId        = reset($categoryIds);

            $name = "catSort.cat_{$categoryId}";
        }

        if ($sorting instanceof Sorting\PriceSorting) {
            $name = 'price';
        }

        if ($sorting instanceof Sorting\ProductNameSorting) {
            $name = 'title';
        }

        if ($sorting instanceof Sorting\ProductNumberSorting) {
            $name = 'ean';
        }

        if ($sorting instanceof Sorting\ProductStockSorting) {
            $name = 'stock';
        }

        if ($sorting instanceof Sorting\ReleaseDateSorting) {
            $name = 'releaseDate';
        }

        if (false !== $name) {
            $sortResult[$name] = $sorting->getDirection();
        }
    }
}
