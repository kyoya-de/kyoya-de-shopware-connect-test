<?php

namespace MakairaConnect\Modifier\Common;

use MakairaConnect\Modifier\CategoryModifierInterface;
use MakairaConnect\Modifier\ManufacturerModifierInterface;
use MakairaConnect\Modifier\ProductModifierInterface;
use Shopware\Bundle\StoreFrontBundle\Struct\Category;
use Shopware\Bundle\StoreFrontBundle\Struct\Product;
use Shopware\Bundle\StoreFrontBundle\Struct\ShopContext;

class AddSeoMeta implements ProductModifierInterface, CategoryModifierInterface, ManufacturerModifierInterface
{
    /**
     * @param array       $mappedData
     * @param Category    $item
     * @param ShopContext $context
     */
    public function modifyCategory(array &$mappedData, Category $item, ShopContext $context): void
    {
        $mappedData['meta_keywords']    = (string) $item->getMetaKeywords();
        $mappedData['meta_description'] = (string) $item->getMetaDescription();
    }

    /**
     * @param array                $mappedData
     * @param Product\Manufacturer $item
     * @param ShopContext          $context
     */
    public function modifyManufacturer(array &$mappedData, Product\Manufacturer $item, ShopContext $context): void
    {
        $mappedData['meta_keywords']    = $item->getMetaKeywords();
        $mappedData['meta_description'] = $item->getMetaDescription();
    }

    /**
     * @param array       $mappedData
     * @param Product     $item
     * @param ShopContext $context
     */
    public function modifyProduct(array &$mappedData, Product $item, ShopContext $context): void
    {
        $mappedData['meta_keywords']    = $item->getKeywords();
        $mappedData['meta_description'] = $item->getShortDescription();
    }

}
