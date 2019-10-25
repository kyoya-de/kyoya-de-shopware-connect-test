<?php

namespace MakairaConnect\Mapper;

class MapperPool
{
    /**
     * @var MapperInterface[]
     */
    private $mapper;

    /**
     * @param MapperInterface $mapper
     *
     * @return $this
     */
    public function addMapper(MapperInterface $mapper): self
    {
        $this->mapper[$mapper->getType()] = $mapper;

        return $this;
    }

    public function mapDocument(string $type, array $data): array
    {
        if (isset($this->mapper[$type])) {
            return $this->mapper[$type]->mapDocument($data);
        }

        throw new UnknownTypeException(
            sprintf(
                "The type '%s' is not supported. Supported types are %s",
                $type,
                implode(', ', array_keys($this->mapper))
            )
        );
    }
}
