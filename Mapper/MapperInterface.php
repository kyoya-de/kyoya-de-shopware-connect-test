<?php

namespace MakairaConnect\Mapper;

interface MapperInterface
{
    public function mapDocument(array $data): array;
    public function getType(): string;
}
