<?php

namespace MakairaConnect\Mapper;

class ProductMapper extends AbstractMapper
{
    protected function getDefaultDocument(): array
    {
        return [
            'id'                           => '',
            'parent'                       => '',
            'shop'                         => [],
            'ean'                          => '',
            'activeto'                     => null,
            'activefrom'                   => null,
            'isVariant'                    => false,
            'active'                       => false,
            'hidden'                       => false,
            'sort'                         => 0,
            'stock'                        => 0,
            'onstock'                      => true,
            'picture_url_main'             => '',
            'title'                        => '',
            'shortdesc'                    => '',
            'longdesc'                     => '',
            'price'                        => 0,
            'soldamount'                   => 0,
            'searchable'                   => false,
            'searchkeys'                   => '',
            'meta_keywords'                => '',
            'meta_description'             => '',
            'manufacturerid'               => null,
            'manufacturer_title'           => null,
            'url'                          => '',
            'maincategory'                 => '',
            'maincategoryurl'              => '',
            'category'                     => [],
            'attributes'                   => [],
            'attributeStr'                 => [],
            'attributeInt'                 => [],
            'attributeFloat'               => [],
            'mak_boost_norm_insert'        => 0.0,
            'mak_boost_norm_sold'          => 0.0,
            'mak_boost_norm_rating'        => 0.0,
            'mak_boost_norm_revenue'       => 0.0,
            'mak_boost_norm_profit_margin' => 0.0,
            'timestamp'                    => '1970-01-01 00:00:00',
            'additionalData'               => [
                'ean2'              => '',
                'price_from_text'   => '',
                'price_no_vat'      => 0,
                'tprice'            => 0,
                'tprice_no_vat'     => 0,
                'label_new'         => 'NEU',
                'label_discount'    => '0%',
                'label_promo_text'  => '',
                'base_price'        => '',
                'base_price_no_vat' => '',
                'base_price_text'   => '',
            ],
        ];
    }

    protected function getFieldMap(): array
    {
        return [];
    }

    public function getType(): string
    {
        return 'product';
    }
}
