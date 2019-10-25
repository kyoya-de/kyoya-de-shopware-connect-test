<?php

namespace MakairaConnect\Mapper;

class CategoryMapper extends AbstractMapper
{
    protected function getFieldMap(): array
    {
        return [];
    }

    protected function getDefaultDocument(): array
    {
        return [
            'id'               => '',
            'active'           => false,
            'hidden'           => true,
            'sort'             => 0,
            'category_title'   => '',
            'shortdesc'        => '',
            'longdesc'         => '',
            'meta_keywords'    => '',
            'meta_description' => '',
            'hierarchy'        => '',
            'depth'            => 0,
            'subcategories'    => [],
            'timestamp'        => '1970-01-01 00:00:00',
            'url'              => '',
            'shop'             => '',
            'additionalData'   => '',
        ];
    }

    public function getType(): string
    {
        return 'category';
    }
}
