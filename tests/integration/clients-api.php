<?php

// Real HTTP regression checks; all created customers are removed afterwards.
$base = getenv('CLIENT_API_URL') ?: 'http://localhost/apps/ticky_crm/api/v1/clients';
$credentials = (getenv('API_USER') ?: 'admin') . ':' . (getenv('API_PASSWORD') ?: 'admin-dev-password');
$created = [];

function requestClient(string $method, string $path = '', ?array $body = null): array {
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

function expectClient(int $status, array $response): mixed {
    if ($status !== $response[0]) throw new RuntimeException('Expected ' . $status . ', got ' . json_encode($response));
    return $response[1];
}

function checkClient(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}

$number = 'VALIDATION-' . bin2hex(random_bytes(10));
try {
    foreach ([[], ['client_number' => ' '], ['client_number' => $number, 'name' => ' '],
        ['client_number' => str_repeat('A', 65), 'name' => 'Test'],
        ['client_number' => $number, 'name' => 'Test', 'contact_email' => 'invalid'],
        ['client_number' => $number, 'name' => 'Test', 'invoice_email' => 'invalid']] as $invalid) {
        $error = expectClient(400, requestClient('POST', '', $invalid));
        checkClient($error['error'] === 'client_invalid_field', 'Missing validation code');
    }
    $client = expectClient(201, requestClient('POST', '', ['client_number' => ' ' . $number . ' ', 'name' => ' Validation test ']));
    $created[] = $client['uuid'];
    checkClient($client['client_number'] === $number && $client['name'] === 'Validation test', 'Required fields not normalized');

    $duplicate = expectClient(409, requestClient('POST', '', ['client_number' => $number, 'name' => 'Duplicate']));
    checkClient($duplicate['error'] === 'duplicate_client_number', 'Missing duplicate code');
    expectClient(409, requestClient('POST', '', ['client_number' => ' ' . $number . ' ', 'name' => 'Duplicate']));
    $unchanged = expectClient(200, requestClient('GET', '/' . $client['uuid']));
    checkClient($unchanged['name'] === 'Validation test', 'Duplicate create changed existing client');

    $second = expectClient(201, requestClient('POST', '', ['client_number' => $number . '-2', 'name' => 'Second validation test']));
    $created[] = $second['uuid'];
    $duplicate = expectClient(409, requestClient('PUT', '/' . $second['uuid'], ['client_number' => $number]));
    checkClient($duplicate['error'] === 'duplicate_client_number', 'Duplicate update not explained');
    expectClient(400, requestClient('PUT', '/' . $second['uuid'], ['name' => ' ']));
    expectClient(400, requestClient('PUT', '/' . $second['uuid'], ['client_number' => '']));
    expectClient(400, requestClient('PUT', '/' . $second['uuid'], ['contact_email' => 'invalid']));
    $unchanged = expectClient(200, requestClient('GET', '/' . $second['uuid']));
    checkClient($unchanged['client_number'] === $number . '-2', 'Failed update changed client number');
    expectClient(200, requestClient('PUT', '/' . $second['uuid'], ['name' => 'Updated validation test', 'contact_email' => 'test@example.com']));
    echo "Client validation API checks passed.\n";
} finally {
    foreach ($created as $uuid) {
        expectClient(200, requestClient('DELETE', '/' . $uuid));
    }
}

