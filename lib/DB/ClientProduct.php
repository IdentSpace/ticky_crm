<?php

namespace OCA\TickyCRM\DB;

use OCP\AppFramework\Db\Entity;

/**
 * @method string getUuid()
 * @method void setUuid(string $uuid)
 * @method int getClientId()
 * @method void setClientId(int $clientId)
 * @method ?int getProductId()
 * @method void setProductId(?int $productId)
 * @method string getProductNumber()
 * @method void setProductNumber(string $productNumber)
 * @method string getName()
 * @method void setName(string $name)
 * @method string getUnit()
 * @method void setUnit(string $unit)
 * @method string getCurrency()
 * @method void setCurrency(string $currency)
 * @method string getQuantity()
 * @method void setQuantity(string $quantity)
 * @method string getSalesPriceNet()
 * @method void setSalesPriceNet(string $salesPriceNet)
 * @method string getSalesTaxRate()
 * @method void setSalesTaxRate(string $salesTaxRate)
 * @method ?string getPurchasePriceNet()
 * @method void setPurchasePriceNet(?string $purchasePriceNet)
 * @method ?string getPurchaseTaxRate()
 * @method void setPurchaseTaxRate(?string $purchaseTaxRate)
 * @method \DateTime getCreatedAt()
 * @method void setCreatedAt(\DateTime $createdAt)
 * @method \DateTime getUpdatedAt()
 * @method void setUpdatedAt(\DateTime $updatedAt)
 */
class ClientProduct extends Entity implements \JsonSerializable {
    protected $uuid;
    protected $clientId;
    protected $productId;
    protected $productNumber;
    protected $name;
    protected $unit;
    protected $currency;
    protected $quantity;
    protected $salesPriceNet;
    protected $salesTaxRate;
    protected $purchasePriceNet;
    protected $purchaseTaxRate;
    protected $createdAt;
    protected $updatedAt;

    public function __construct() {
        $this->addType('clientId', 'integer');
        $this->addType('productId', 'integer');
        foreach (['quantity', 'salesPriceNet', 'salesTaxRate', 'purchasePriceNet', 'purchaseTaxRate'] as $field) {
            $this->addType($field, 'decimal');
        }
        $this->addType('createdAt', 'datetime');
        $this->addType('updatedAt', 'datetime');
    }

    public function jsonSerialize(): array {
        return [
            'id' => $this->getId(),
            'uuid' => $this->getUuid(),
            'client_id' => $this->getClientId(),
            'product_id' => $this->getProductId(),
            'product_number' => $this->getProductNumber(),
            'name' => $this->getName(),
            'unit' => $this->getUnit(),
            'currency' => $this->getCurrency(),
            'quantity' => $this->getQuantity(),
            'sales_price_net' => $this->getSalesPriceNet(),
            'sales_tax_rate' => $this->getSalesTaxRate(),
            'purchase_price_net' => $this->getPurchasePriceNet(),
            'purchase_tax_rate' => $this->getPurchaseTaxRate(),
            'created_at' => $this->getCreatedAt()?->format(\DateTime::ATOM),
            'updated_at' => $this->getUpdatedAt()?->format(\DateTime::ATOM),
        ];
    }
}

