<?php

namespace MakairaConnect\Mapper;

class VariantMapper extends AbstractMapper
{
    protected function getDefaultDocument(): array
    {
        return [
            'id'                 => '',
            'parent'             => '',
            'shop'               => [],
            'ean'                => '',
            'activeto'           => null,
            'activefrom'         => null,
            'isVariant'          => true,
            'active'             => false,
            'sort'               => 0,
            'stock'              => 0,
            'onstock'            => true,
            'picture_url_main'   => '',
            'title'              => '',
            'shortdesc'          => '',
            'longdesc'           => '',
            'price'              => 0,
            'soldamount'         => 0,
            'searchable'         => false,
            'searchkeys'         => '',
            'meta_keywords'      => '',
            'meta_description'   => '',
            'manufacturerid'     => null,
            'manufacturer_title' => null,
            'url'                => '',
            'maincategory'       => '',
            'maincategoryurl'    => '',
            'category'           => [],
            'attributes'         => [],
            'attributeStr'       => [],
            'attributeInt'       => [],
            'attributeFloat'     => [],
            'timestamp'          => '1970-01-01 00:00:00',
            'additionalData'     => [
                'ean2'              => '',
                'price_from_text'   => '',
                'price_no_vat'      => 0.0,
                'tprice'            => 0.0,
                'tprice_no_vat'     => 0.0,
                'label_new'         => '',
                'label_discount'    => '',
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
        return 'variant';
    }
}
