<?php

namespace MakairaConnect\Models;

use Shopware\Components\Model\ModelEntity;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="mak_connect_changes", uniqueConstraints={@ORM\UniqueConstraint(name="mak_unique_doc", columns={"id", "type"})})
 */
class ConnectChange extends ModelEntity
{
    /**
     * Primary Key - autoincrement value
     *
     * @var integer $sequence
     *
     * @ORM\Column(name="sequence", type="bigint", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $sequence;

    /**
     * Type
     *
     * @var string
     *
     * @ORM\Column(name="type", type="string", length=32, nullable=false)
     */
    private $type;

    /**
     * Id
     *
     * @var integer $id
     *
     * @ORM\Column(name="id", type="integer", nullable=false)
     */
    private $id;

    /**
     * Timestamp
     *
     * @var \DateTime $changed
     *
     * @ORM\Column(name="changed", type="datetime", nullable=false)
     */
    private $changed;

    /**
     * @return int
     */
    public function getSequence()
    {
        return $this->sequence;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param string $type
     *
     * @return ConnectChange
     */
    public function setType($type)
    {
        $this->type = $type;

        return $this;
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int $id
     *
     * @return ConnectChange
     */
    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }

    /**
     * @return \DateTime
     */
    public function getChanged()
    {
        return $this->changed;
    }

    /**
     * @param \DateTime $changed
     *
     * @return ConnectChange
     */
    public function setChanged($changed)
    {
        $this->changed = $changed;

        return $this;
    }

}
