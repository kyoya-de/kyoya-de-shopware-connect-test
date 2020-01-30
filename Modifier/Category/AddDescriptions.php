<?php

namespace MakairaConnect\Modifier\Category;

use MakairaConnect\Modifier\CategoryModifierInterface;
use Shopware\Bundle\StoreFrontBundle\Struct\Category;
use Shopware\Bundle\StoreFrontBundle\Struct\ShopContext;

class AddDescriptions implements CategoryModifierInterface
{
    /**
     * @param array       $mappedData
     * @param Category    $item
     * @param ShopContext $context
     */
    public function modifyCategory(array &$mappedData, Category $item, ShopContext $context): void
    {
        $mappedData['shortdesc'] = (string) $item->getCmsHeadline();
        $mappedData['longdesc']  = (string) $item->getCmsText();
    }
}
