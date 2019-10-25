<?php

namespace MakairaConnect\Mapper;

class ManufacturerMapper extends AbstractMapper
{
    protected function getFieldMap(): array
    {
        return [];
    }

    protected function getDefaultDocument(): array
    {
        return [
            'id'                 => '',
            'manufacturer_title' => '',
            'shortdesc'          => '',
            'meta_keywords'      => '',
            'meta_description'   => '',
            'timestamp'          => '1970-01-01 00:00:00',
            'url'                => '',
            'active'             => false,
            'shop'               => '',
            'additionalData'     => '',
        ];
    }

    public function getType(): string
    {
        return 'manufacturer';
    }
}
