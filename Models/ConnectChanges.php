<?php

namespace MakairaConnect\Models;

use Shopware\Components\Model\ModelEntity;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="mak_connect_changes", uniqueConstraints={@ORM\UniqueConstraint(name="mak_unique_doc", columns={"id", "type"})})
 */
class ConnectChanges extends ModelEntity
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
}
