<?php

namespace MakairaConnect\Search\Facet;

use JsonSerializable;
use Shopware\Bundle\SearchBundle\FacetInterface;

class MakairaFacet implements FacetInterface, JsonSerializable
{
    private $type;

    private $key;

    private $label;

    private $formFieldName;

    private $showCount;

    public function __construct(
        string $type,
        string $key,
        string $label,
        string $formFieldName,
        bool $showCount = false
    ) {
        $this->type          = $type;
        $this->key           = $key;
        $this->label         = $label;
        $this->formFieldName = $formFieldName;
        $this->showCount     = $showCount;
    }

    public function getName()
    {
        return "makaira_{$this->key}";
    }

    /**
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @return string
     */
    public function getKey(): string
    {
        return $this->key;
    }

    /**
     * @return string
     */
    public function getLabel(): string
    {
        return $this->label;
    }

    /**
     * @return string
     */
    public function getFormFieldName(): string
    {
        return $this->formFieldName;
    }

    /**
     * @return bool
     */
    public function isShowCount(): bool
    {
        return $this->showCount;
    }

    /**
     * @return array
     */
    public function jsonSerialize()
    {
        return [
            'type'          => $this->type,
            'key'           => $this->key,
            'label'         => $this->label,
            'formFieldName' => $this->formFieldName,
            'showCount'     => $this->showCount,
        ];
    }
}
