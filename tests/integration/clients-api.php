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
    checkClient($client['follow_up_status'] === 'unknown', 'Missing contact should be unknown');
    foreach ([29 => 'green', 30 => 'yellow', 59 => 'yellow', 60 => 'red', 61 => 'red'] as $days => $expected) {
        $date = (new DateTimeImmutable('today'))->modify('-' . $days . ' days')->format('Y-m-d');
        $updated = expectClient(200, requestClient('PUT', '/' . $client['uuid'], ['last_contact_date' => $date]));
        checkClient($updated['follow_up_status'] === $expected, 'Incorrect follow-up boundary at ' . $days);
    }
    $updated = expectClient(200, requestClient('PUT', '/' . $client['uuid'], ['follow_up_mode' => 'manual', 'follow_up_manual' => 'green']));
    checkClient($updated['follow_up_status'] === 'green', 'Manual override ignored');
    $updated = expectClient(200, requestClient('PUT', '/' . $client['uuid'], ['status' => 'inactive']));
    checkClient($updated['follow_up_status'] === 'inactive', 'Inactive should hide manual status');
    $updated = expectClient(200, requestClient('PUT', '/' . $client['uuid'], ['status' => 'active', 'follow_up_mode' => 'automatic']));
    checkClient($updated['follow_up_status'] === 'red', 'Reactivation should restore automatic status');
    $updated = expectClient(200, requestClient('PUT', '/' . $client['uuid'], ['last_contact_date' => date('Y-m-d')]));
    checkClient($updated['follow_up_status'] === 'green', 'Contact today should reset automatic status');
    $updated = expectClient(200, requestClient('PUT', '/' . $client['uuid'], ['last_contact_date' => '']));
    checkClient($updated['last_contact_date'] === null && $updated['follow_up_status'] === 'unknown', 'Contact date cannot be cleared');
    foreach ([['follow_up_mode' => 'bad'], ['follow_up_manual' => 'blue'], ['last_contact_date' => '2026-02-30'], ['last_contact_date' => 'tomorrow'], ['last_contact_date' => (new DateTimeImmutable('tomorrow'))->format('Y-m-d')]] as $invalid) {
        expectClient(400, requestClient('PUT', '/' . $client['uuid'], $invalid));
    }
    $listed = expectClient(200, requestClient('GET'));
    $listedClient = current(array_filter($listed, fn($item) => $item['uuid'] === $client['uuid']));
    checkClient($listedClient['follow_up_status'] === 'unknown', 'List and detail status differ');
    echo "Client validation API checks passed.\n";
} finally {
    foreach ($created as $uuid) {
        expectClient(200, requestClient('DELETE', '/' . $uuid));
    }
}

