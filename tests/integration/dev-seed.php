<?php

define('OC_CONSOLE', true);
require_once '/var/www/html/lib/base.php';

use OCA\TickyCRM\Command\DevSeed;
use OCA\TickyCRM\Service\FollowUpService;
use OCA\TickyCRM\Service\ProductOptionsService;
use OCP\IDBConnection;
use OCP\IUserSession;
use OCP\Server;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

set_exception_handler(static function (Throwable $error): void {
    fwrite(STDERR, (string)$error . "\n");
    exit(1);
});

function runSeed(array $options): array {
    $output = new BufferedOutput();
    $code = Server::get(DevSeed::class)->run(new ArrayInput($options), $output);
    return [$code, $output->fetch()];
}

function seedCheck(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}

$db = Server::get(IDBConnection::class);
$prefix = 'TESTSEED-' . strtoupper(bin2hex(random_bytes(4)));
$rows = static function(string $table, string $field) use ($db, $prefix): array {
    $qb = $db->getQueryBuilder();
    $qb->select('*')->from($table)->where($qb->expr()->like($field, $qb->createNamedParameter($db->escapeLikeParameter($prefix) . '-%')));
    $query = $qb->executeQuery();
    $data = $query->fetchAll();
    $query->closeCursor();
    return $data;
};
$previousUser = Server::get(IUserSession::class)->getUser();
$originalOptions = Server::get(ProductOptionsService::class)->get();
$options = ['--prefix' => $prefix, '--clients' => '24', '--products' => '12'];
$db->beginTransaction();
try {
    [$code, $output] = runSeed($options + ['--dry-run' => true]);
    seedCheck($code === 0 && str_contains($output, '36 customer-product links'), 'Dry run failed: ' . $output);
    seedCheck($rows('ticky_clients', 'client_number') === [] && $rows('ticky_products', 'product_number') === [], 'Dry run wrote records');
    [$code, $output] = runSeed($options);
    seedCheck($code === 0, 'Seed failed: ' . $output);
    $clients = $rows('ticky_clients', 'client_number');
    $products = $rows('ticky_products', 'product_number');
    $positions = $rows('ticky_client_products', 'product_number');
    seedCheck(count($clients) === 24 && count($products) === 12 && count($positions) === 36, 'Seed counts differ');
    $statuses = array_unique(array_map(fn($client) => Server::get(FollowUpService::class)->status($client), $clients));
    seedCheck(array_diff(['green', 'yellow', 'red', 'unknown', 'inactive'], $statuses) === [], 'Missing contact scenarios');
    seedCheck(count(array_unique(array_column($clients, 'type'))) === 2, 'Missing customer types');
    seedCheck(count(array_unique(array_column($products, 'status'))) === 2, 'Missing active/archived products');
    seedCheck(in_array('2.5000', array_column($positions, 'quantity'), true), 'Missing fractional quantity');
    seedCheck(in_array('42.1250', array_column($positions, 'sales_price_net'), true), 'Missing agreed customer prices');
    $productStatuses = array_column($products, 'status', 'id');
    foreach ($positions as $position) seedCheck($productStatuses[$position['product_id']] === 'active', 'Archived product was assigned');
    [$code] = runSeed($options);
    seedCheck($code === 1 && count($rows('ticky_clients', 'client_number')) === 24, 'Duplicate run was not rejected');
    foreach ([['--clients' => '0'], ['--products' => '2.5'], ['--prefix' => 'invalid prefix'], ['--user' => 'missing-seed-user']] as $invalid) {
        [$code] = runSeed(array_merge($options, $invalid));
        seedCheck($code === 1, 'Invalid command option accepted');
    }
    [$code, $output] = runSeed(['--prefix' => $prefix . '-SMALL', '--clients' => '4', '--products' => '1']);
    seedCheck($code === 0 && str_contains($output, '3 customer-product links'), 'Single-product dataset failed: ' . $output);
    seedCheck(Server::get(IUserSession::class)->getUser() === $previousUser, 'Seeder did not restore session');
    seedCheck(Server::get(ProductOptionsService::class)->get() === $originalOptions, 'Seeder changed global product options');
    echo "Development seed command checks passed.\n";
} finally {
    // This also removes seeded addresses, associations and database activity records.
    $db->rollBack();
}
seedCheck($rows('ticky_clients', 'client_number') === [] && $rows('ticky_products', 'product_number') === [], 'Seed test data was not rolled back');
