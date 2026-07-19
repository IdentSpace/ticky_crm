<?php
namespace OCA\TickyCRM\DB;

use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

/**
 * @method int getClientId()
 * @method void setClientId(int $clientId)
 * @method int getCardId()
 * @method void setCardId(int $cardId)
 * @method \DateTime getCreatedAt()
 * @method void setCreatedAt(\DateTime $createdAt)
 */
class ClientContact extends Entity {
    protected $clientId;
    protected $cardId;
    protected $createdAt;

    public function __construct() {
        $this->addType('clientId', 'integer');
        $this->addType('cardId', 'integer');
        $this->addType('createdAt', Types::DATETIME);
    }
}