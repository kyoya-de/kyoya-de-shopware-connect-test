<?php

namespace MakairaConnect\Modifier;

use Shopware\Bundle\StoreFrontBundle\Struct\Product;
use Shopware\Bundle\StoreFrontBundle\Struct\ShopContext;

interface ManufacturerModifierInterface
{
    public function modifyManufacturer(array &$mappedData, Product\Manufacturer $item, ShopContext $context): void;
}
