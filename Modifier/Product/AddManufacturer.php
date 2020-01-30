<?php

namespace MakairaConnect\Modifier\Product;

use MakairaConnect\Modifier\ProductModifierInterface;
use Shopware\Bundle\StoreFrontBundle\Struct\Product;
use Shopware\Bundle\StoreFrontBundle\Struct\ShopContext;

class AddManufacturer implements ProductModifierInterface
{
    public function modifyProduct(array &$mappedData, Product $item, ShopContext $context): void
    {
        $manufacturerId    = '';
        $manufacturerTitle = '';
        if (null !== ($manufacturer = $item->getManufacturer())) {
            $manufacturerId    = $manufacturer->getId();
            $manufacturerTitle = $manufacturer->getName();
        }

        $mappedData['manufacturerid']     = $manufacturerId;
        $mappedData['manufacturer_title'] = $manufacturerTitle;
    }

}
