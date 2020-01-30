<?php

namespace MakairaConnect\Modifier;

use Shopware\Bundle\StoreFrontBundle\Struct\Category;
use Shopware\Bundle\StoreFrontBundle\Struct\ShopContext;

interface CategoryModifierInterface
{
    public function modifyCategory(array &$mappedData, Category $item, ShopContext $context): void;

}
