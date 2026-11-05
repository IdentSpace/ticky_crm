<?php

namespace OCA\TickyCRM\Service;

use DateTime;
use InvalidArgumentException;
use OCA\TickyCRM\DB\Product;
use OCA\TickyCRM\DB\ProductMapper;
use OCA\TickyCRM\DB\ClientProductMapper;

class ProductService {
    public function __construct(private ProductMapper $mapper, private ProductOptionsService $options, private ClientProductMapper $positions, private ProductActivityService $activities) {
    }

    /** @return Product[] */
    public function all(int $limit = 100, int $offset = 0, string $query = '', bool $activeOnly = false): array {
        if ($limit < 1 || $limit > 100 || $offset < 0) {
            throw new InvalidArgumentException('pagination');
        }
        if (mb_strlen($query) > 255) {
            throw new InvalidArgumentException('query');
        }
        return $this->mapper->findAllProducts($limit, $offset, trim($query), $activeOnly);
    }

    public function find(string $uuid): Product {
        return $this->mapper->findByUuid($uuid);
    }

    public function create(array $data): Product {
        $product = new Product();
        $this->apply($product, $this->validate(array_merge([
            'type' => $this->options->get()['type'][0]['id'],
            'status' => Product::STATUS_ACTIVE,
        ], $data)));
        $product->setUuid(bin2hex(random_bytes(16)));
        $product->setCreatedAt(new DateTime());
        $product->setUpdatedAt(new DateTime());
        $saved = $this->mapper->insert($product);
        $this->activities->log('product', 'created', $saved->getName(), $saved->getId(), ['productUuid' => $saved->getUuid()]);
        return $saved;
    }

    public function update(string $uuid, array $data): Product {
        $product = $this->find($uuid);
        $positions = $this->positions->findByProduct($product->getId());
        $this->apply($product, $this->validate(array_merge($product->jsonSerialize(), $data), $product->jsonSerialize()));
        $product->setUpdatedAt(new DateTime());
        $saved = $this->mapper->update($product);
        $params = ['productUuid' => $saved->getUuid(), 'productNumber' => $saved->getProductNumber()];
        $this->activities->log('product', 'updated', $saved->getName(), $saved->getId(), $params);
        $this->logForLinkedClients('product_catalog_updated', $saved->getName(), $params, $positions);
        return $saved;
    }

    public function delete(string $uuid): void {
        $product = $this->find($uuid);
        $positions = $this->positions->findByProduct($product->getId());
        $this->mapper->delete($product);
        $params = ['productUuid' => $product->getUuid(), 'productNumber' => $product->getProductNumber()];
        $this->activities->log('product', 'deleted', $product->getName(), $product->getId(), $params);
        $this->logForLinkedClients('product_deleted', $product->getName(), $params, $positions);
    }

    private function logForLinkedClients(string $action, string $name, array $params, array $positions): void {
        $loggedClients = [];
        foreach ($positions as $position) {
            $clientId = $position->getClientId();
            if (!isset($loggedClients[$clientId])) {
                $this->activities->log('client', $action, $name, $clientId, $params);
                $loggedClients[$clientId] = true;
            }
        }
    }

    private function apply(Product $product, array $data): void {
        foreach ($data as $field => $value) {
            $setter = 'set' . str_replace(' ', '', ucwords(str_replace('_', ' ', $field)));
            $product->$setter($value);
        }
    }

    private function validate(array $data, array $previous = []): array {
        $result = [];
        foreach (['product_number' => 64, 'name' => 255, 'unit' => 32, 'currency' => 3, 'type' => 32, 'status' => 32] as $field => $length) {
            $result[$field] = $this->text($data[$field] ?? null, $field, $length, true);
        }
        foreach (['description' => 65535, 'internal_note' => 65535, 'category' => 255, 'procurement_type' => 32] as $field => $length) {
            $result[$field] = $this->text($data[$field] ?? null, $field, $length, false);
        }
        $result['currency'] = strtoupper($result['currency']);
        if (!preg_match('/^[A-Z]{3}$/D', $result['currency'])) {
            throw new InvalidArgumentException('currency');
        }
        foreach (ProductOptionsService::FIELDS as $field) {
            if (!$this->options->accepts($field, $result[$field], $previous[$field] ?? null)) {
                throw new InvalidArgumentException($field);
            }
        }
        foreach (['sales_price_net', 'purchase_price_net', 'sales_tax_rate', 'purchase_tax_rate'] as $field) {
            $result[$field] = DecimalValue::normalize($data[$field] ?? null, $field, str_ends_with($field, 'tax_rate'), str_starts_with($field, 'purchase_'));
        }
        return $result;
    }

    private function text(mixed $value, string $field, int $length, bool $required): ?string {
        if ($value === null && !$required) {
            return null;
        }
        if (!is_string($value)) {
            throw new InvalidArgumentException($field);
        }
        $value = trim($value);
        if (($required && $value === '') || mb_strlen($value) > $length) {
            throw new InvalidArgumentException($field);
        }
        return $value === '' ? null : $value;
    }

}
