<?php

namespace MakairaConnect\Modifier;

use Shopware\Bundle\StoreFrontBundle\Struct\Product;
use Shopware\Bundle\StoreFrontBundle\Struct\ShopContext;

interface ProductModifierInterface
{
    public function modifyProduct(array &$mappedData, Product $item, ShopContext $context): void;
}
