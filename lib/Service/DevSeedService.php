<?php

namespace OCA\TickyCRM\Service;

use DateTimeImmutable;
use InvalidArgumentException;
use OCA\TickyCRM\DB\Client;
use OCA\TickyCRM\DB\Product;
use OCP\IDBConnection;

class DevSeedService {
    public function __construct(
        private IDBConnection $db,
        private ClientService $clients,
        private ProductService $products,
        private ClientProductService $positions,
        private ProductOptionsService $productOptions,
        private FollowUpService $followUp,
    ) {}

    public function seed(int $clientCount, int $productCount, string $prefix, bool $dryRun = false): array {
        if ($clientCount < 1 || $clientCount > 1000 || $productCount < 1 || $productCount > 1000) {
            throw new InvalidArgumentException('Customer and product counts must be between 1 and 1000.');
        }
        if (!preg_match('/^[A-Z][A-Z0-9_-]{0,23}$/D', $prefix)) {
            throw new InvalidArgumentException('Prefix must start with A-Z and contain at most 24 uppercase letters, digits, underscores or hyphens.');
        }
        $this->db->beginTransaction();
        try {
            $this->assertAvailable('ticky_clients', 'client_number', $prefix . '-C-', $clientCount);
            $this->assertAvailable('ticky_products', 'product_number', $prefix . '-P-', $productCount);
            $activeCount = $productCount - intdiv($productCount, 5);
            $linkCount = 0;
            for ($i = 0; $i < $clientCount; $i++) $linkCount += min($i % 4, $activeCount);
            $result = ['clients' => $clientCount, 'products' => $productCount, 'links' => $linkCount];
            if (!$dryRun) {
                $options = $this->productOptions->get();
                $activeProducts = [];
                for ($i = 0; $i < $productCount; $i++) {
                    $product = $this->products->create($this->productData($i, $prefix, $options));
                    if ($product->getStatus() === Product::STATUS_ACTIVE) $activeProducts[] = $product;
                }
                $intervals = $this->followUp->settings();
                for ($i = 0; $i < $clientCount; $i++) {
                    $client = $this->clients->create($this->clientData($i, $prefix, $intervals));
                    $this->linkProducts($client, $activeProducts, $i);
                }
            }
            $this->db->commit();
            return $result;
        } catch (\Throwable $error) {
            $this->db->rollBack();
            throw $error;
        }
    }

    private function assertAvailable(string $table, string $field, string $prefix, int $count): void {
        $numbers = [];
        for ($i = 1; $i <= $count; $i++) $numbers[] = $prefix . sprintf('%04d', $i);
        // Stay below SQLite's parameter limit even for the largest supported dataset.
        foreach (array_chunk($numbers, 500) as $batch) {
            $qb = $this->db->getQueryBuilder();
            $qb->select($field)->from($table)
                ->where($qb->expr()->in($field, $qb->createNamedParameter($batch, \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_STR_ARRAY)))
                ->setMaxResults(1);
            $query = $qb->executeQuery();
            $existing = $query->fetchOne();
            $query->closeCursor();
            if ($existing !== false) {
                throw new InvalidArgumentException('Seed number already exists: ' . $existing . '. Choose a different --prefix. No data was changed.');
            }
        }
    }

    private function clientData(int $i, string $prefix, array $intervals): array {
        $names = ['Bildungswerk Müller', 'Anna Schneider', 'Nordlicht Technik GmbH', 'Werkstatt am Park', 'Bäckerei Krüger', 'Lena Weiß', 'Rheinblick Logistik', 'Südwest Akademie'];
        $scenario = $i % 8;
        $days = [0, $intervals['yellow_days'], $intervals['red_days'] + 1, null, $intervals['red_days'] + 10, 0, 0, $intervals['red_days'] + 90][$scenario];
        $number = sprintf('%04d', $i + 1);
        $data = [
            'client_number' => $prefix . '-C-' . $number,
            'name' => $names[$scenario] . ' (' . $prefix . ' ' . $number . ')',
            'type' => $i % 4 === 1 ? 'person' : 'company',
            'status' => $scenario === 7 ? 'inactive' : ($i % 3 === 0 ? 'lead' : 'active'),
            'follow_up_mode' => in_array($scenario, [4, 5, 6], true) ? 'manual' : 'automatic',
            'follow_up_manual' => [4 => 'green', 5 => 'yellow', 6 => 'red'][$scenario] ?? 'green',
            'last_contact_date' => $days === null ? null : (new DateTimeImmutable('today'))->modify('-' . $days . ' days')->format('Y-m-d'),
            'contact_email' => $i % 4 === 3 ? null : strtolower($prefix) . '.customer' . $number . '@example.invalid',
            'invoice_email' => $i % 2 === 0 ? 'billing.' . $number . '@example.invalid' : null,
            'phone' => $i % 3 === 0 ? null : '+49 30 555' . $number,
            'website' => $i % 2 === 0 ? 'https://customer' . $number . '.example.invalid' : null,
            'vat_id' => $i % 4 === 0 ? 'DE123456789' : null,
            'addresses' => [],
        ];
        if ($i % 4 !== 3) {
            $data['addresses'][] = ['type' => 'billing', 'label' => 'Rechnungsadresse', 'street' => 'Musterstraße', 'house_number' => (string)($i + 1), 'postal_code' => '10115', 'city' => 'Berlin', 'country_code' => 'DE'];
        }
        if ($i % 4 === 0) {
            $data['addresses'][] = ['type' => 'shipping', 'label' => 'Lieferadresse', 'street' => 'Hafenstraße', 'house_number' => '12', 'postal_code' => '20457', 'city' => 'Hamburg', 'country_code' => 'DE'];
        }
        return $data;
    }

    private function productData(int $i, string $prefix, array $options): array {
        $pick = static fn(string $field): ?string => $options[$field] === [] ? null : $options[$field][$i % count($options[$field])]['id'];
        $names = ['IT-Grundlagenkurs', 'Beratung', 'Digitale Lernlizenz', 'Schulungsunterlagen', 'Team-Workshop', 'Prüfungsvorbereitung'];
        return [
            'product_number' => $prefix . '-P-' . sprintf('%04d', $i + 1),
            'name' => $names[$i % count($names)] . ' (' . $prefix . ' ' . ($i + 1) . ')',
            'description' => $i % 3 === 0 ? null : 'Beispielprodukt für Entwicklung und Tests. Kein echtes Angebot.',
            'type' => $pick('type'), 'category' => $pick('category'), 'procurement_type' => $pick('procurement_type'),
            'unit' => $pick('unit'), 'currency' => $pick('currency'),
            'status' => ($i + 1) % 5 === 0 ? Product::STATUS_ARCHIVED : Product::STATUS_ACTIVE,
            'sales_price_net' => ['0.0000', '9.9900', '89.5000', '1250.0000', '19.1234', '250.7500'][$i % 6],
            'sales_tax_rate' => ['0', '7', '19'][$i % 3],
            'purchase_price_net' => $i % 2 === 0 ? null : '4.2500',
            'purchase_tax_rate' => $i % 2 === 0 ? null : '19',
            'internal_note' => 'Development seed: ' . $prefix,
        ];
    }

    /** @param Product[] $products */
    private function linkProducts(Client $client, array $products, int $i): void {
        for ($j = 0; $j < min($i % 4, count($products)); $j++) {
            $product = $products[($i + $j) % count($products)];
            $data = ['product_uuid' => $product->getUuid(), 'quantity' => ['1', '2.5', '12'][$j % 3]];
            if (($i + $j) % 2 === 0) $data['sales_price_net'] = '42.1250';
            $this->positions->create($client->getUuid(), $data);
        }
    }
}
