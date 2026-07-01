<?php

namespace OCA\TickyCRM\DB;

use OCP\AppFramework\Db\Entity;

/**
 * @method int getClientId()
 * @method void setClientId(int $clientId)
 * @method string getType()
 * @method void setType(string $type)
 * @method string|null getLabel()
 * @method void setLabel(string|null $label)
 * @method string getStreet()
 * @method void setStreet(string $street)
 * @method string|null getHouseNumber()
 * @method void setHouseNumber(string|null $houseNumber)
 * @method string|null getAddressAddition()
 * @method void setAddressAddition(string|null $addressAddition)
 * @method string getPostalCode()
 * @method void setPostalCode(string $postalCode)
 * @method string getCity()
 * @method void setCity(string $city)
 * @method string getCountryCode()
 * @method void setCountryCode(string $countryCode)
 * @method string|null getPhone()
 * @method void setPhone(string|null $phone)
 * @method \DateTime|null getCreatedAt()
 * @method void setCreatedAt(\DateTime|null $createdAt)
 * @method \DateTime|null getUpdatedAt()
 * @method void setUpdatedAt(\DateTime|null $updatedAt)
 */
class Address extends Entity implements \JsonSerializable {

    protected $clientId;
    protected $type;
    protected $label;
    protected $street;
    protected $houseNumber;
    protected $addressAddition;
    protected $postalCode;
    protected $city;
    protected $countryCode;
    protected $createdAt;
    protected $updatedAt;

    public function __construct() {
        // Typen registrieren, damit Nextcloud weiß, wie es konvertieren soll
        $this->addType('clientId', 'integer');
        $this->addType('createdAt', 'datetime');
        $this->addType('updatedAt', 'datetime');
    }

    /**
     * Bereitet die Entity für die JSON-Ausgabe im ApiController vor
     */
    public function jsonSerialize(): array {
        return [
            'id' => $this->getId(),
            'clientId' => $this->getClientId(),
            'type' => $this->getType(),
            'label' => $this->getLabel(),
            'street' => $this->getStreet(),
            'houseNumber' => $this->getHouseNumber(),
            'addressAddition' => $this->getAddressAddition(),
            'postalCode' => $this->getPostalCode(),
            'city' => $this->getCity(),
            'countryCode' => $this->getCountryCode(),
            'createdAt' => $this->getCreatedAt() ? $this->getCreatedAt()->format(\DateTime::ATOM) : null,
            'updatedAt' => $this->getUpdatedAt() ? $this->getUpdatedAt()->format(\DateTime::ATOM) : null,
        ];
    }
}