<?php

namespace OCA\TickyCRM\Service;

use DateTime;
use InvalidArgumentException;
use OCA\TickyCRM\DB\ClientMapper;
use OCA\TickyCRM\DB\ClientProduct;
use OCA\TickyCRM\DB\ClientProductMapper;
use OCA\TickyCRM\DB\Product;
use OCA\TickyCRM\DB\ProductMapper;

class ClientProductService {
    public function __construct(private ClientProductMapper $mapper, private ClientMapper $clients, private ProductMapper $products, private ProductActivityService $activities) {
    }

    /** @return ClientProduct[] */
    public function all(string $clientUuid): array {
        return $this->mapper->findByClient($this->clients->findByUuid($clientUuid, false)->getId());
    }

    public function create(string $clientUuid, array $data): ClientProduct {
        $client = $this->clients->findByUuid($clientUuid, false);
        if (!isset($data['product_uuid']) || !is_string($data['product_uuid']) || $data['product_uuid'] === '') {
            throw new InvalidArgumentException('product_uuid');
        }
        $product = $this->products->findByUuid($data['product_uuid']);
        if ($product->getStatus() !== Product::STATUS_ACTIVE) {
            throw new InvalidArgumentException('product_uuid');
        }
        $position = new ClientProduct();
        $position->setUuid(bin2hex(random_bytes(16)));
        $position->setClientId($client->getId());
        $position->setProductId($product->getId());
        $position->setProductNumber($product->getProductNumber());
        $position->setName($product->getName());
        $position->setUnit($product->getUnit());
        $position->setCurrency($product->getCurrency());
        // Copy catalog values once. Future catalog edits never update this position.
        $this->applyValues($position, array_merge($product->jsonSerialize(), ['quantity' => '1'], $data));
        $position->setCreatedAt(new DateTime());
        $position->setUpdatedAt(new DateTime());
        $saved = $this->mapper->insert($position);
        $this->activities->association($saved, $client, 'linked');
        return $saved;
    }

    public function update(string $clientUuid, string $positionUuid, array $data): ClientProduct {
        $client = $this->clients->findByUuid($clientUuid, false);
        $position = $this->mapper->findByUuid($positionUuid, $client->getId());
        $this->applyValues($position, array_merge($position->jsonSerialize(), $data));
        $position->setUpdatedAt(new DateTime());
        $saved = $this->mapper->update($position);
        $this->activities->association($saved, $client, 'updated');
        return $saved;
    }

    public function delete(string $clientUuid, string $positionUuid): void {
        $client = $this->clients->findByUuid($clientUuid, false);
        $position = $this->mapper->findByUuid($positionUuid, $client->getId());
        $this->mapper->delete($position);
        $this->activities->association($position, $client, 'unlinked');
    }

    private function applyValues(ClientProduct $position, array $data): void {
        $values = [];
        foreach (['quantity', 'sales_price_net', 'sales_tax_rate', 'purchase_price_net', 'purchase_tax_rate'] as $field) {
            $values[$field] = DecimalValue::normalize($data[$field] ?? null, $field, str_ends_with($field, 'tax_rate'), str_starts_with($field, 'purchase_'));
        }
        if ($values['quantity'] === '0.0000') {
            throw new InvalidArgumentException('quantity');
        }
        foreach ($values as $field => $value) {
            $setter = 'set' . str_replace(' ', '', ucwords(str_replace('_', ' ', $field)));
            $position->$setter($value);
        }
    }
}
