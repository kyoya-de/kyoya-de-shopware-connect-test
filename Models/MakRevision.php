<?php

namespace MakairaConnect\Models;

use DateTime;
use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;
use Shopware\Components\Model\ModelEntity;

/**
 * @ORM\Entity(repositoryClass="MakairaConnect\Repositories\MakRevisionRepository")
 * @ORM\Table(name="mak_revision", uniqueConstraints={
 *     @ORM\UniqueConstraint(name="mak_unique_doc", columns={"id", "type"})
 * })
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
     * @var string $id
     *
     * @ORM\Column(name="id", type="string", length=255, nullable=false)
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
     * @var string|null
     *
     * @ORM\Column(name="entity_id", type="bigint", nullable=true)
     */
    private $entityId;

    /**
     * set default date
     */
    public function __construct()
    {
        $this->changed = new DateTime('now');
    }

    /**
     * @return int
     */
    public function getSequence(): int
    {
        return (int) $this->sequence;
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
     *
     * @return MakRevision
     */
    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    /**
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @param string $id
     *
     * @return MakRevision
     */
    public function setId(string $id): self
    {
        $this->id = $id;

        return $this;
    }

    /**
     * @return DateTime
     */
    public function getChanged(): DateTimeInterface
    {
        return $this->changed;
    }

    /**
     * @param DateTime $changed
     *
     * @return MakRevision
     */
    public function setChanged(DateTimeInterface $changed): self
    {
        $this->changed = $changed;

        return $this;
    }

    /**
     * @return int|null
     */
    public function getEntityId(): ?int
    {
        return $this->entityId;
    }

    /**
     * @param int|null $entityId
     *
     * @return MakRevision
     */
    public function setEntityId(?int $entityId): MakRevision
    {
        $this->entityId = $entityId;

        return $this;
    }
}
