<?php

// Real HTTP regression checks; all created customers are removed afterwards.
$base = getenv('CRM_API_URL') ?: 'http://localhost/apps/ticky_crm/api/v1';
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

$number = 'NOTE-CONTACT-' . bin2hex(random_bytes(8));
$noteIds = [];
try {
    $client = expectClient(201, requestClient('POST', '/clients', ['client_number' => $number, 'name' => 'Note contact test', 'follow_up_mode' => 'manual', 'follow_up_manual' => 'red']));
    $created[] = $client['uuid'];
    $path = '/clients/' . $client['id'] . '/notes';
    $current = fn() => expectClient(200, requestClient('GET', '/clients/' . $client['uuid']));
    $save = function(array $extra) use ($path, &$noteIds) {
        $note = expectClient(201, requestClient('POST', $path, array_merge(['content' => 'Contact update regression'], $extra)));
        $noteIds[] = $note['id'];
        return $note;
    };
    $note = $save([]);
    checkClient(!isset($note['client']), 'Plain note unexpectedly includes customer update');
    checkClient($current()['last_contact_date'] === null && $current()['follow_up_manual'] === 'red', 'Plain note changed contact status');
    $note = $save(['contact_update' => ['last_contact_date' => date('Y-m-d')]]);
    checkClient($note['client']['follow_up_status'] === 'red', 'Date-only update changed manual status');
    checkClient($current()['last_contact_date'] === date('Y-m-d'), 'Contact date not persisted');
    $note = $save(['contact_update' => ['follow_up_mode' => 'manual', 'follow_up_manual' => 'yellow']]);
    checkClient($note['client']['follow_up_status'] === 'yellow', 'Status-only update failed');
    checkClient($current()['last_contact_date'] === date('Y-m-d'), 'Status update changed date');
    $note = $save(['contact_update' => ['follow_up_mode' => 'automatic', 'last_contact_date' => date('Y-m-d')]]);
    checkClient($note['client']['follow_up_status'] === 'green', 'Combined update failed');
    $before = count(expectClient(200, requestClient('GET', $path)));
    foreach ([['follow_up_manual' => 'invalid'], ['last_contact_date' => '2026-02-30'], ['name' => 'Unauthorized field']] as $invalid) {
        expectClient(400, requestClient('POST', $path, ['content' => 'Must not be saved', 'contact_update' => $invalid]));
    }
    checkClient(count(expectClient(200, requestClient('GET', $path))) === $before, 'Invalid contact update created note');
    expectClient(400, requestClient('POST', $path, ['content' => ' ', 'contact_update' => ['follow_up_mode' => 'manual', 'follow_up_manual' => 'red']]));
    checkClient($current()['follow_up_status'] === 'green', 'Failed note changed status');
    // Opt in locally: the intentional server error is logged, while CI requires a clean server log.
    if (getenv('TEST_NOTE_ROLLBACK') === '1') {
        // Force note persistence to fail after the customer update, then verify transaction rollback.
        expectClient(500, requestClient('POST', $path, ['content' => 'Must roll back', 'title' => str_repeat('X', 1000), 'contact_update' => ['follow_up_mode' => 'manual', 'follow_up_manual' => 'red']]));
        checkClient($current()['follow_up_status'] === 'green', 'Failed insert did not roll back customer update');
        checkClient(count(expectClient(200, requestClient('GET', $path))) === $before, 'Failed note was persisted');
    }
    echo "Note contact update API checks passed.\n";
} finally {
    foreach ($noteIds as $id) expectClient(200, requestClient('DELETE', '/notes/' . $id));
    foreach ($created as $uuid) expectClient(200, requestClient('DELETE', '/clients/' . $uuid));
}
