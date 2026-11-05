<?php

define('OC_CONSOLE', true);
require_once '/var/www/html/lib/base.php';

use OCP\IConfig;
use OCP\IUserManager;
use OCP\Server;

set_exception_handler(static function (Throwable $error): void {
    fwrite(STDERR, (string)$error . "\n");
    exit(1);
});
$config = Server::get(IConfig::class);
$users = Server::get(IUserManager::class);
$missing = '__MISSING__';
$originalConfig = $config->getAppValue('ticky_crm', 'product_options', $missing);
$originalUsers = $config->getAppValue('ticky_crm', 'allowed_users', $missing);
$base = 'http://localhost/apps/ticky_crm/api/v1';
$admin = (getenv('API_USER') ?: 'admin') . ':' . (getenv('API_PASSWORD') ?: 'admin-dev-password');
$token = bin2hex(random_bytes(6));
$created = null;
$testUser = null;

function optionsApi(string $method, string $path, ?array $body = null, ?string $credentials = null): array {
    global $base, $admin;
    $c = curl_init($base . $path);
    curl_setopt_array($c, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_TIMEOUT => 30, CURLOPT_USERPWD => $credentials ?? $admin,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json', 'OCS-APIRequest: true']]);
    if ($body !== null) curl_setopt($c, CURLOPT_POSTFIELDS, json_encode($body, JSON_THROW_ON_ERROR));
    $body = curl_exec($c);
    if ($body === false) throw new RuntimeException(curl_error($c));
    $status = curl_getinfo($c, CURLINFO_HTTP_CODE);
    curl_close($c);
    return [$status, json_decode($body, true)];
}
function expectOptions(int $status, array $response): mixed {
    if ($response[0] !== $status) throw new RuntimeException('Expected ' . $status . ', got ' . json_encode($response));
    return $response[1];
}
function checkOptions(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}

try {
    $metadata = expectOptions(200, optionsApi('GET', '/product-options'));
    checkOptions($metadata['can_manage'] === true && count($metadata['defaults']) === 6, 'Missing admin metadata/defaults');
    $options = $metadata['options'];
    foreach (['category', 'type', 'procurement_type', 'status', 'unit', 'currency'] as $field) {
        $key = $field === 'currency' ? 'XTS' : ($field . '_' . $token);
        if (!in_array($key, array_column($options[$field], 'id'), true)) $options[$field][] = ['id' => $key, 'label' => 'Test ' . $field];
    }
    $saved = expectOptions(200, optionsApi('POST', '/product-options', ['options' => $options]));
    checkOptions($saved['options'] === $options, 'Settings did not round trip');
    $payload = ['product_number' => 'OPTIONS-' . $token, 'name' => 'Options test',
        'category' => 'category_' . $token, 'type' => 'type_' . $token,
        'procurement_type' => 'procurement_type_' . $token, 'unit' => 'unit_' . $token,
        'currency' => 'XTS', 'sales_price_net' => '1.1234', 'sales_tax_rate' => '19'];
    $created = expectOptions(201, optionsApi('POST', '/products', $payload));
    checkOptions($created['type'] === $payload['type'], 'Configured type not accepted');

    $without = $options;
    foreach (['category', 'type', 'procurement_type', 'unit'] as $field) {
        $without[$field] = array_values(array_filter($without[$field], fn($entry) => $entry['id'] !== $payload[$field]));
    }
    expectOptions(200, optionsApi('POST', '/product-options', ['options' => $without]));
    expectOptions(200, optionsApi('PUT', '/products/' . $created['uuid'], ['name' => 'Retired values preserved']));
    $payload['product_number'] .= '-other';
    expectOptions(400, optionsApi('POST', '/products', $payload));

    $invalid = $without;
    $invalid['currency'][] = $invalid['currency'][0];
    expectOptions(400, optionsApi('POST', '/product-options', ['options' => $invalid]));
    $invalid = $without;
    $invalid['status'] = array_values(array_filter($invalid['status'], fn($entry) => $entry['id'] !== 'active'));
    expectOptions(400, optionsApi('POST', '/product-options', ['options' => $invalid]));
    $invalid = $without;
    $invalid['unit'] = [];
    expectOptions(400, optionsApi('POST', '/product-options', ['options' => $invalid]));
    checkOptions(expectOptions(200, optionsApi('GET', '/product-options'))['options'] === $without, 'Invalid save modified settings');

    $uid = 'options-test-' . $token;
    $password = bin2hex(random_bytes(16));
    $testUser = $users->createUser($uid, $password);
    $allowed = json_decode($originalUsers === $missing ? '[]' : $originalUsers, true);
    $config->setAppValue('ticky_crm', 'allowed_users', json_encode(array_merge($allowed, [$uid])));
    $read = expectOptions(200, optionsApi('GET', '/product-options', null, $uid . ':' . $password));
    checkOptions($read['can_manage'] === false, 'Regular user received management privileges');
    expectOptions(403, optionsApi('POST', '/product-options', ['options' => $options], $uid . ':' . $password));
    echo "Product options settings/validation/permissions checks passed.\n";
} finally {
    if ($created !== null) expectOptions(200, optionsApi('DELETE', '/products/' . $created['uuid']));
    if ($testUser) $testUser->delete();
    foreach (['product_options' => $originalConfig, 'allowed_users' => $originalUsers] as $key => $value) {
        if ($value === $missing) $config->deleteAppValue('ticky_crm', $key);
        else $config->setAppValue('ticky_crm', $key, $value);
    }
}
