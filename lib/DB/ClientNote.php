<?php

namespace OCA\TickyCRM\DB;

use OCP\AppFramework\Db\Entity;
use DateTime;

/**
 * @method int getClientId()
 * @method void setClientId(int $clientId)
 * @method string getUserId()
 * @method void setUserId(string $userId)
 * @method string getType()
 * @method void setType(string $type)
 * @method string|null getTitle()
 * @method void setTitle(string|null $title)
 * @method string getContent()
 * @method void setContent(string $content)
 * @method DateTime|null getCreatedAt()
 * @method void setCreatedAt(DateTime|null $createdAt)
 * @method DateTime|null getUpdatedAt()
 * @method void setUpdatedAt(DateTime|null $updatedAt)
 */
class ClientNote extends Entity implements \JsonSerializable
{

    protected $clientId;
    protected $userId;
    protected $type;
    protected $title;
    protected $content;
    protected $createdAt;
    protected $updatedAt;

    public function __construct() {
        $this->addType('createdAt', 'datetime');
        $this->addType('updatedAt', 'datetime');
    }

    public function jsonSerialize(): array {
        return [
            'id' => $this->id,
            'client_id' => $this->clientId,
            'user_id' => $this->userId,
            'type' => $this->type,
            'title' => $this->title,
            'content' => $this->content,
            'created_at' => $this->createdAt ? $this->createdAt->format(\DateTime::ATOM) : null,
            'updated_at' => $this->updatedAt ? $this->updatedAt->format(\DateTime::ATOM) : null,
        ];
    }
}