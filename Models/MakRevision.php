<?php

namespace MakairaConnect\Models;

use DateTime;
use Shopware\Components\Model\ModelEntity;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="MakairaConnect\Repositories\MakRevisionRepository")
 * @ORM\Table(name="mak_revision", uniqueConstraints={@ORM\UniqueConstraint(name="mak_unique_doc", columns={"id", "type"})})
 */
class MakRevision extends ModelEntity
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
     * @var DateTime $changed
     *
     * @ORM\Column(name="changed", type="datetime", nullable=false)
     */
    private $changed;

    /**
     * set default date
     */
    public function __construct() {
      $this->changed = new DateTime('now');
    }

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
     * @return MakRevision
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
     * @return MakRevision
     */
    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }

    /**
     * @return DateTime
     */
    public function getChanged()
    {
        return $this->changed;
    }

    /**
     * @param DateTime $changed
     *
     * @return MakRevision
     */
    public function setChanged($changed)
    {
        $this->changed = $changed;

        return $this;
    }

}
