<?php

namespace OCA\TickyCRM\DB;

use OCP\AppFramework\Db\Entity;
use JsonSerializable;
/**
 * @method string|null getUuid()
 * @method string|null getClientNumber()
 * @method string|null getName()
 * @method string|null getType()
 * @method string|null getStatus()
 * @method string|null getContactEmail()
 * @method string|null getInvoiceEmail()
 * @method string|null getPhone()
 * @method string|null getWebsite()
 * @method string|null getVatId()
 * @method string|null getTaxNumber()
 * @method string|null getRegisterCourt()
 * @method string|null getRegisterNumber()
 * @method int|string|null getNcFileId()
 * @method string|null getNcFolderPath()
 * @method array getAddresses()
 * @method \DateTime|null getCreatedAt()
 * @method \DateTime|null getUpdatedAt()
 */
class Client extends Entity implements JsonSerializable {
    protected $uuid;
    protected $clientNumber;
    protected $name;
    protected $type;
    protected $status;
    protected $contactEmail;
    protected $invoiceEmail;
    protected $phone;
    protected $website;
    protected $vatId;
    protected $taxNumber;
    protected $registerCourt;
    protected $registerNumber;
    protected $ncFileId;
    protected $ncFolderPath;
    protected $addresses = [];
    protected $createdAt;
    protected $updatedAt;


    public function __construct() {
        $this->addType('createdAt', 'datetime');
        $this->addType('updatedAt', 'datetime');
    }

    /**
     * Mappt die PHP-Properties auf das JSON-Objekt fürs Vue-Frontend
     */
    public function jsonSerialize(): array {
        return [
            'id' => $this->getId(),
            'uuid' => $this->getUuid(),
            'client_number' => $this->getClientNumber(),
            'name' => $this->getName(),
            'type' => $this->getType(),
            'status' => $this->getStatus(),
            'contact_email' => $this->getContactEmail(),
            'invoice_email' => $this->getInvoiceEmail(),
            'phone' => $this->getPhone(),
            'website' => $this->getWebsite(),
            'vat_id' => $this->getVatId(),
            'tax_number' => $this->getTaxNumber(),
            'register_court' => $this->getRegisterCourt(),
            'register_number' => $this->getRegisterNumber(),
            'nc_file_id' => $this->getNcFileId(),
            'nc_folder_path' => $this->getNcFolderPath(),
            'addresses' => $this->getAddresses(),
            // Formatiert das DateTime-Objekt als ISO-8601-String für JS
            'created_at' => $this->getCreatedAt() ? $this->getCreatedAt()->format(\DateTime::ATOM) : null,
            'updated_at' => $this->getUpdatedAt() ? $this->getUpdatedAt()->format(\DateTime::ATOM) : null,
        ];
    }
}