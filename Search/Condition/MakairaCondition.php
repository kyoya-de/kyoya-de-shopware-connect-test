<?php

namespace MakairaConnect\Search\Condition;

use Assert\Assertion;
use Assert\AssertionFailedException;
use JsonSerializable;
use Shopware\Bundle\SearchBundle\ConditionInterface;
use function get_object_vars;

class MakairaCondition implements ConditionInterface, JsonSerializable
{
    /**
     * @var string
     */
    protected $field;

    /**
     * @var string
     */
    protected $type;

    /**
     * @var string|array
     */
    protected $value;

    /**
     * @param string       $field
     * @param string|array $value ['min' => 1, 'max' => 10] for between operator
     * @param              $type
     *
     * @throws AssertionFailedException
     */
    public function __construct($field, $value, $type)
    {
        Assertion::string($field);
        $this->field = $field;
        $this->value = $value;
        $this->type  = $type;
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'makaira_' . $this->field;
    }

    /**
     * @return string
     */
    public function getField(): string
    {
        return $this->field;
    }

    /**
     * @param string $field
     */
    public function setField($field): void
    {
        $this->field = $field;
    }

    /**
     * @return string|array|null $value
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * @param string|array $value
     */
    public function setValue($value): void
    {
        $this->value = $value;
    }

    /**
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @param string $type
     */
    public function setType(string $type): void
    {
        $this->type = $type;
    }

    /**
     * {@inheritdoc}
     */
    public function jsonSerialize()
    {
        return get_object_vars($this);
    }
}
