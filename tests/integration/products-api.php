<?php

// Run inside the Nextcloud container. Uses disposable products and real HTTP requests.
$base = getenv('PRODUCT_API_URL') ?: 'http://localhost/apps/ticky_crm/api/v1/products';
$credentials = (getenv('API_USER') ?: 'admin') . ':' . (getenv('API_PASSWORD') ?: 'admin-dev-password');
$created = [];

function requestProduct(string $method, string $path = '', ?array $body = null, bool $authenticated = true): array {
    global $base, $credentials;
    $curl = curl_init($base . $path);
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => ['Accept: application/json', 'Content-Type: application/json', 'OCS-APIRequest: true'],
    ]);
    if ($authenticated) {
        curl_setopt($curl, CURLOPT_USERPWD, $credentials);
    }
    if ($body !== null) {
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($body, JSON_THROW_ON_ERROR));
    }
    $response = curl_exec($curl);
    if ($response === false) {
        throw new RuntimeException(curl_error($curl));
    }
    $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    return [$status, json_decode($response, true)];
}

function expectStatus(int $expected, array $response): mixed {
    if ($response[0] !== $expected) {
        throw new RuntimeException('Expected HTTP ' . $expected . ', got ' . json_encode($response));
    }
    return $response[1];
}

function checkApi(bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$token = bin2hex(random_bytes(10));
$payload = [
    'product_number' => 'API-' . $token, 'name' => 'API product test',
    'unit' => 'hour', 'currency' => 'eur', 'sales_price_net' => '1234,5678', 'sales_tax_rate' => '19',
];

try {
    checkApi(in_array(requestProduct('GET', '', null, false)[0], [401, 403], true), 'Anonymous API access allowed');
    expectStatus(400, requestProduct('POST', '', []));
    $product = expectStatus(201, requestProduct('POST', '', $payload));
    $created[] = $product['uuid'];
    $path = '/' . $product['uuid'];
    checkApi($product['sales_price_net'] === '1234.5678', 'Comma decimal normalization failed');
    checkApi($product['currency'] === 'EUR', 'Currency normalization failed');
    checkApi($product['purchase_price_net'] === null, 'Unknown purchase cost must be NULL');
    expectStatus(200, requestProduct('GET', $path));
    $page = expectStatus(200, requestProduct('GET', '?limit=1&offset=0'));
    checkApi(count($page) <= 1, 'Pagination limit ignored');
    expectStatus(400, requestProduct('GET', '?limit=101'));
    expectStatus(400, requestProduct('GET', '?offset=-1'));
    expectStatus(409, requestProduct('POST', '', $payload));

    foreach (['sales_price_net' => '-1', 'purchase_price_net' => '0.00001', 'sales_tax_rate' => '100.01',
        'purchase_tax_rate' => '-1', 'type' => 'invalid', 'procurement_type' => 'invalid',
        'status' => 'invalid', 'unit' => '', 'currency' => 'EU'] as $field => $invalid) {
        expectStatus(400, requestProduct('PUT', $path, [$field => $invalid]));
    }
    $updated = expectStatus(200, requestProduct('PUT', $path, [
        'purchase_price_net' => '0', 'purchase_tax_rate' => '0', 'type' => 'service',
        'procurement_type' => 'external', 'category' => 'Consulting',
        'description' => '<script>text only</script>', 'internal_note' => 'Internal',
        'uuid' => 'ignored', 'id' => -1, 'created_at' => 'ignored',
    ]));
    checkApi($updated['uuid'] === $product['uuid'] && $updated['id'] === $product['id'], 'Identity can be overwritten');
    checkApi($updated['created_at'] === $product['created_at'], 'Creation timestamp can be overwritten');
    checkApi($updated['purchase_price_net'] === '0.0000' && $updated['purchase_tax_rate'] === '0.00', 'Zero purchase values lost');
    $updated = expectStatus(200, requestProduct('PUT', $path, ['purchase_price_net' => '', 'purchase_tax_rate' => null, 'status' => 'archived']));
    checkApi($updated['purchase_price_net'] === null && $updated['purchase_tax_rate'] === null, 'Clearing purchase values failed');
    checkApi($updated['status'] === 'archived', 'Archiving failed');

    $secondPayload = $payload;
    $secondPayload['product_number'] .= '-other';
    $second = expectStatus(201, requestProduct('POST', '', $secondPayload));
    $created[] = $second['uuid'];
    expectStatus(409, requestProduct('PUT', '/' . $second['uuid'], ['product_number' => $payload['product_number']]));
    expectStatus(200, requestProduct('DELETE', $path));
    array_shift($created);
    expectStatus(404, requestProduct('GET', $path));
    expectStatus(404, requestProduct('PUT', $path, ['name' => 'Missing']));
    expectStatus(404, requestProduct('DELETE', $path));
    echo "Product API checks passed.\n";
} finally {
    foreach ($created as $uuid) {
        expectStatus(200, requestProduct('DELETE', '/' . $uuid));
    }
}
