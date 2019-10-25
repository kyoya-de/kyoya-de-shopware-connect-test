<?php

namespace MakairaConnect\Mapper;

abstract class AbstractMapper implements MapperInterface
{
    /**
     * Maps one database row to a Makaira document.
     *
     * @param array $data
     *
     * @return array
     */
    public function mapDocument(array $data): array
    {
        $fieldMap = $this->getFieldMap();

        foreach ($fieldMap as $sourceKey => $replacement) {
            $data[$replacement] = $data[$sourceKey];
            unset($data[$sourceKey]);
        }

        return array_replace_recursive($this->getDefaultDocument(), $data);
    }

    /**
     * Returns list of array keys and their replacements.
     *
     * @return array
     */
    abstract protected function getFieldMap(): array;

    /**
     * Returns the document structure including all required fields.
     *
     * @return array
     */
    abstract protected function getDefaultDocument(): array;
}
