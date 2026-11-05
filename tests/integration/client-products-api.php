<?php

// Real HTTP checks for scoped assignments, copied prices and deletion behavior.
$base = getenv('CRM_API_URL') ?: 'http://localhost/apps/ticky_crm/api/v1';
$credentials = (getenv('API_USER') ?: 'admin') . ':' . (getenv('API_PASSWORD') ?: 'admin-dev-password');
$clients = [];
$clientIds = [];
$products = [];

function api(string $method, string $path, ?array $body = null): array {
    global $base, $credentials;
    $curl = curl_init($base . $path);
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_TIMEOUT => 30, CURLOPT_USERPWD => $credentials,
        CURLOPT_HTTPHEADER => ['Accept: application/json', 'Content-Type: application/json', 'OCS-APIRequest: true'],
    ]);
    if ($body !== null) curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($body, JSON_THROW_ON_ERROR));
    $response = curl_exec($curl);
    if ($response === false) throw new RuntimeException(curl_error($curl));
    $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    return [$status, json_decode($response, true)];
}
function expect(int $status, array $response): mixed {
    if ($status !== $response[0]) throw new RuntimeException('Expected ' . $status . ', got ' . json_encode($response));
    return $response[1];
}
function check(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}
function activityCount(string $clientUuid, string $subject): int {
    $activities = expect(200, api('GET', '/clients/' . $clientUuid . '/activities'));
    return count(array_filter($activities, fn($activity) => $activity['subject'] === $subject));
}
function findPosition(array $positions, string $uuid): array {
    foreach ($positions as $position) if ($position['uuid'] === $uuid) return $position;
    throw new RuntimeException('Position not found');
}
function productActivityCount(string $productUuid, string $subject): int {
    $activities = expect(200, api('GET', '/products/' . $productUuid . '/activities'));
    return count(array_filter($activities, fn($activity) => $activity['subject'] === $subject));
}

$token = 'ASSIGN-' . bin2hex(random_bytes(10));
try {
    foreach (['A', 'B'] as $suffix) {
        $client = expect(201, api('POST', '/clients', ['client_number' => $token . $suffix, 'name' => 'Assignment test ' . $suffix]));
        $clients[] = $client['uuid'];
        $clientIds[] = $client['id'];
    }
    $product = expect(201, api('POST', '/products', [
        'product_number' => $token, 'name' => 'Original product', 'unit' => 'hour', 'currency' => 'EUR',
        'sales_price_net' => '100,1234', 'sales_tax_rate' => '19', 'purchase_price_net' => '50,1234', 'purchase_tax_rate' => '7',
    ]));
    $products[] = $product['uuid'];
    check(productActivityCount($product['uuid'], 'product_created') === 1, 'Product creation activity missing');
    expect(404, api('GET', '/products/missing/activities'));
    $path = '/clients/' . $clients[0] . '/products';
    $other = '/clients/' . $clients[1] . '/products';
    check(expect(200, api('GET', $path)) === [], 'Expected empty assignments');
    expect(404, api('POST', '/clients/missing/products', ['product_uuid' => $product['uuid']]));
    expect(400, api('POST', $path, []));
    expect(404, api('POST', $path, ['product_uuid' => 'missing']));
    foreach (['quantity' => '0', 'sales_price_net' => '-1', 'sales_tax_rate' => '100.01', 'purchase_tax_rate' => '-1'] as $field => $value) {
        expect(400, api('POST', $path, ['product_uuid' => $product['uuid'], $field => $value]));
    }
    $position = expect(201, api('POST', $path, [
        'product_uuid' => $product['uuid'], 'quantity' => '2,5', 'sales_price_net' => '90,5678',
        'client_id' => -1, 'product_id' => -1, 'name' => 'Ignored', 'currency' => 'USD',
    ]));
    check($position['quantity'] === '2.5000' && $position['sales_price_net'] === '90.5678', 'Exact comma values lost');
    check($position['purchase_price_net'] === '50.1234' && $position['purchase_tax_rate'] === '7.00', 'Purchase snapshot missing');
    check($position['name'] === 'Original product' && $position['currency'] === 'EUR', 'Snapshot metadata can be overridden');
    check($position['client_id'] === $clientIds[0], 'Position assigned to wrong client');
    check(activityCount($clients[0], 'client_product_linked') === 1, 'Expected one link activity; invalid attempts must not produce activities');
    check(productActivityCount($product['uuid'], 'product_client_linked') === 1, 'Product-side link activity missing');
    $positionPath = $path . '/' . $position['uuid'];
    expect(404, api('PUT', $other . '/' . $position['uuid'], ['quantity' => '1']));
    expect(404, api('DELETE', $other . '/' . $position['uuid']));
    check(activityCount($clients[1], 'client_product_unlinked') === 0, 'Failed cross-client deletion produced activity');
    $updated = expect(200, api('PUT', $positionPath, ['quantity' => '3,125', 'purchase_price_net' => '', 'purchase_tax_rate' => null]));
    check($updated['quantity'] === '3.1250' && $updated['purchase_price_net'] === null, 'Updating/clearing values failed');
    expect(400, api('PUT', $positionPath, ['quantity' => '0']));
    check(activityCount($clients[0], 'client_product_updated') === 1, 'Customer-side assignment update activity missing or includes failed update');
    check(productActivityCount($product['uuid'], 'product_client_updated') === 1, 'Product-side assignment update activity missing');
    $history = expect(200, api('GET', '/products/' . $product['uuid'] . '/activities'));
    $updateEvent = array_values(array_filter($history, fn($entry) => $entry['subject'] === 'product_client_updated'))[0];
    check($updateEvent['objectName'] === 'Assignment test A' && $updateEvent['parameters']['quantity'] === '3.1250', 'Product history must identify customer and saved values');

    $second = expect(201, api('POST', $path, ['product_uuid' => $product['uuid']]));
    check($second['sales_price_net'] === '100.1234', 'Default sales price not copied');
    expect(200, api('DELETE', $path . '/' . $second['uuid']));
    expect(404, api('DELETE', $path . '/' . $second['uuid']));
    check(activityCount($clients[0], 'client_product_linked') === 2, 'Missing second link activity');
    check(activityCount($clients[0], 'client_product_unlinked') === 1, 'Unlink activity missing or duplicated on repeated delete');
    check(productActivityCount($product['uuid'], 'product_client_unlinked') === 1, 'Product-side removal activity missing or duplicated');
    expect(200, api('GET', '/products/' . $product['uuid']));
    $extra = expect(201, api('POST', $path, ['product_uuid' => $product['uuid']]));
    $otherPosition = expect(201, api('POST', $other, ['product_uuid' => $product['uuid']]));

    $found = expect(200, api('GET', '/products?query=' . $token . '&activeOnly=true'));
    check(count($found) === 1 && $found[0]['uuid'] === $product['uuid'], 'Active product search failed');
    expect(200, api('PUT', '/products/' . $product['uuid'], ['name' => 'Changed catalog', 'sales_price_net' => '999', 'purchase_price_net' => '500', 'status' => 'archived']));
    check(productActivityCount($product['uuid'], 'product_updated') === 1, 'Catalog update activity missing');
    check(activityCount($clients[0], 'client_product_catalog_updated') === 1, 'Catalog update must log once per linked client');
    check(activityCount($clients[1], 'client_product_catalog_updated') === 1, 'Catalog update missing for second client');
    check(expect(200, api('GET', '/products?query=' . $token . '&activeOnly=true')) === [], 'Archived product offered for assignment');
    expect(400, api('POST', $path, ['product_uuid' => $product['uuid']]));
    $saved = findPosition(expect(200, api('GET', $path)), $position['uuid']);
    check($saved['name'] === 'Original product' && $saved['sales_price_net'] === '90.5678', 'Catalog edits changed saved position');

    expect(200, api('DELETE', '/products/' . $product['uuid']));
    $products = [];
    check(activityCount($clients[0], 'client_product_deleted') === 1, 'Catalog deletion must log once per client, not once per position');
    check(activityCount($clients[1], 'client_product_deleted') === 1, 'Catalog deletion missing on second customer');
    $activities = expect(200, api('GET', '/clients/' . $clients[0] . '/activities'));
    $deletion = array_values(array_filter($activities, fn($entry) => $entry['subject'] === 'client_product_deleted'))[0];
    check($deletion['objectName'] === 'Changed catalog', 'Deletion activity must retain catalog product name');
    $saved = findPosition(expect(200, api('GET', $path)), $position['uuid']);
    check($saved['product_id'] === null && $saved['sales_price_net'] === '90.5678', 'Product deletion lost position/history');
    expect(200, api('PUT', $positionPath, ['sales_price_net' => '91,1234']));
    expect(200, api('DELETE', $positionPath));
    expect(200, api('DELETE', $path . '/' . $extra['uuid']));
    expect(200, api('DELETE', $other . '/' . $otherPosition['uuid']));
    check(activityCount($clients[0], 'client_product_unlinked') === 3, 'Removing saved positions after catalog deletion must still log activities');
    check(expect(200, api('GET', $path)) === [], 'Assignment removal failed');
    echo "Client product API checks passed.\n";
} finally {
    foreach ($clients as $uuid) expect(200, api('DELETE', '/clients/' . $uuid));
    foreach ($products as $uuid) expect(200, api('DELETE', '/products/' . $uuid));
}
