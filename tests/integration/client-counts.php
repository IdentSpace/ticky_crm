<?php

define('OC_CONSOLE', true);
require_once '/var/www/html/lib/base.php';

use OCA\DAV\CardDAV\CardDavBackend;
use OCA\TickyCRM\DB\Client;
use OCA\TickyCRM\DB\ClientMapper;
use OCP\IDBConnection;
use OCP\Server;

set_exception_handler(static function (Throwable $error): void {
    fwrite(STDERR, (string)$error . "\n");
    exit(1);
});
$db = Server::get(IDBConnection::class);
$mapper = new ClientMapper($db);
$token = bin2hex(random_bytes(12));
$db->beginTransaction();
try {
    $client = new Client();
    $client->setUuid($token);
    $client->setClientNumber('COUNT-' . $token);
    $client->setName('Count regression');
    $client = $mapper->insert($client);
    $empty = new Client();
    $empty->setUuid($token . '-empty');
    $empty->setClientNumber('EMPTY-' . $token);
    $empty->setName('Empty count regression');
    $empty = $mapper->insert($empty);
    for ($index = 0; $index < 2; $index++) {
        $qb = $db->getQueryBuilder();
        $qb->insert('ticky_client_products');
        foreach (['uuid' => $token . '-' . $index, 'client_id' => $client->getId(),
            'product_number' => 'Historical', 'name' => 'Historical product', 'unit' => 'piece', 'currency' => 'EUR',
            'quantity' => '10.0000', 'sales_price_net' => '1.0000', 'sales_tax_rate' => '19.00',
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')] as $field => $value) {
            $qb->setValue($field, $qb->createNamedParameter($value));
        }
        $qb->executeStatement();
    }

    $dav = Server::get(CardDavBackend::class);
    $bookId = $dav->createAddressBook('principals/users/admin', 'count-test-' . $token, ['{DAV:}displayname' => 'Count regression']);
    for ($index = 0; $index < 3; $index++) {
        $uri = $token . '-' . $index . '.vcf';
        $dav->createCard($bookId, $uri, "BEGIN:VCARD\r\nVERSION:3.0\r\nUID:$uri\r\nFN:Count regression\r\nEND:VCARD\r\n");
        $qb = $db->getQueryBuilder();
        $qb->select('id')->from('cards')->where($qb->expr()->eq('addressbookid', $qb->createNamedParameter($bookId)))
            ->andWhere($qb->expr()->eq('uri', $qb->createNamedParameter($uri)));
        $result = $qb->executeQuery();
        $cardId = (int)$result->fetchOne();
        $result->closeCursor();
        $qb = $db->getQueryBuilder();
        $qb->insert('ticky_client_contacts')->values([
            'client_id' => $qb->createNamedParameter($client->getId()),
            'card_id' => $qb->createNamedParameter($cardId),
            'created_at' => $qb->createNamedParameter(date('Y-m-d H:i:s')),
        ])->executeStatement();
    }
    $counts = $mapper->getRelationCounts([$client->getId(), $empty->getId()]);
    if (($counts[$client->getId()]['product_count'] ?? 0) !== 2 || ($counts[$client->getId()]['contact_count'] ?? 0) !== 3) {
        throw new RuntimeException('Expected 2 positions and 3 contacts; counts must not multiply or sum quantities');
    }
    if (isset($counts[$empty->getId()])) throw new RuntimeException('Empty customer received relation counts');
    echo "Client count aggregation checks passed.\n";
} finally {
    $db->rollBack();
}
