<?php

namespace OCA\TickyCRM\DB;

use OCP\AppFramework\Db\Entity;

/**
 * @method string getUuid()
 * @method void setUuid(string $uuid)
 * @method string getProductNumber()
 * @method void setProductNumber(string $productNumber)
 * @method string getName()
 * @method void setName(string $name)
 * @method ?string getDescription()
 * @method void setDescription(?string $description)
 * @method string getType()
 * @method void setType(string $type)
 * @method ?string getProcurementType()
 * @method void setProcurementType(?string $procurementType)
 * @method ?string getCategory()
 * @method void setCategory(?string $category)
 * @method string getUnit()
 * @method void setUnit(string $unit)
 * @method string getStatus()
 * @method void setStatus(string $status)
 * @method string getCurrency()
 * @method void setCurrency(string $currency)
 * @method string getSalesPriceNet()
 * @method void setSalesPriceNet(string $salesPriceNet)
 * @method string getSalesTaxRate()
 * @method void setSalesTaxRate(string $salesTaxRate)
 * @method ?string getPurchasePriceNet()
 * @method void setPurchasePriceNet(?string $purchasePriceNet)
 * @method ?string getPurchaseTaxRate()
 * @method void setPurchaseTaxRate(?string $purchaseTaxRate)
 * @method ?string getInternalNote()
 * @method void setInternalNote(?string $internalNote)
 * @method \DateTime getCreatedAt()
 * @method void setCreatedAt(\DateTime $createdAt)
 * @method \DateTime getUpdatedAt()
 * @method void setUpdatedAt(\DateTime $updatedAt)
 */
class Product extends Entity implements \JsonSerializable {
    public const TYPE_PRODUCT = 'product';
    public const TYPE_SERVICE = 'service';
    public const TYPE_DIGITAL = 'digital';
    public const PROCUREMENT_IN_HOUSE = 'in_house';
    public const PROCUREMENT_EXTERNAL = 'external';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_ARCHIVED = 'archived';

    protected $uuid;
    protected $productNumber;
    protected $name;
    protected $description;
    protected $type;
    protected $procurementType;
    protected $category;
    protected $unit;
    protected $status;
    protected $currency;
    protected $salesPriceNet;
    protected $salesTaxRate;
    protected $purchasePriceNet;
    protected $purchaseTaxRate;
    protected $internalNote;
    protected $createdAt;
    protected $updatedAt;

    public function __construct() {
        // Keep exact decimal values as strings throughout the persistence layer.
        foreach (['salesPriceNet', 'salesTaxRate', 'purchasePriceNet', 'purchaseTaxRate'] as $field) {
            $this->addType($field, 'decimal');
        }
        $this->addType('createdAt', 'datetime');
        $this->addType('updatedAt', 'datetime');
    }

    public function jsonSerialize(): array {
        return [
            'id' => $this->getId(),
            'uuid' => $this->getUuid(),
            'product_number' => $this->getProductNumber(),
            'name' => $this->getName(),
            'description' => $this->getDescription(),
            'type' => $this->getType(),
            'procurement_type' => $this->getProcurementType(),
            'category' => $this->getCategory(),
            'unit' => $this->getUnit(),
            'status' => $this->getStatus(),
            'currency' => $this->getCurrency(),
            'sales_price_net' => $this->getSalesPriceNet(),
            'sales_tax_rate' => $this->getSalesTaxRate(),
            'purchase_price_net' => $this->getPurchasePriceNet(),
            'purchase_tax_rate' => $this->getPurchaseTaxRate(),
            'internal_note' => $this->getInternalNote(),
            'created_at' => $this->getCreatedAt()?->format(\DateTime::ATOM),
            'updated_at' => $this->getUpdatedAt()?->format(\DateTime::ATOM),
        ];
    }
}

