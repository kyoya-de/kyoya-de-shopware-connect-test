<?php

namespace MakairaConnect\Modifier\Manufacturer;

use MakairaConnect\Modifier\ManufacturerModifierInterface;
use Shopware\Bundle\StoreFrontBundle\Struct\Product;
use Shopware\Bundle\StoreFrontBundle\Struct\ShopContext;

class AddDescriptions implements ManufacturerModifierInterface
{
    /**
     * @param array                $mappedData
     * @param Product\Manufacturer $item
     * @param ShopContext          $context
     */
    public function modifyManufacturer(array &$mappedData, Product\Manufacturer $item, ShopContext $context): void
    {
        $mappedData['shortdesc'] = $item->getDescription();
    }

}
