<?php

define('OC_CONSOLE', true);
require_once '/var/www/html/lib/base.php';

use OCA\TickyCRM\Service\FollowUpService;
use OCP\IConfig;
use OCP\Server;

$config = Server::get(IConfig::class);
$service = new FollowUpService($config);
$original = [];
foreach (['follow_up_yellow_days', 'follow_up_red_days'] as $key) {
    $original[$key] = $config->getAppValue('ticky_crm', $key, '');
}
try {
    $service->save(5, 10);
    $today = new DateTimeImmutable('2027-01-15');
    $client = ['status' => 'active', 'follow_up_mode' => 'automatic', 'follow_up_manual' => 'green', 'last_contact_date' => null];
    foreach ([4 => 'green', 5 => 'yellow', 9 => 'yellow', 10 => 'red'] as $days => $expected) {
        $client['last_contact_date'] = $today->modify('-' . $days . ' days')->format('Y-m-d');
        if ($service->status($client, $today) !== $expected) throw new RuntimeException('Configured interval ignored');
    }
    foreach ([[0, 10], [5, 5], [10, 5], [5, 36501]] as [$yellow, $red]) {
        try {
            $service->save($yellow, $red);
            throw new RuntimeException('Invalid intervals accepted');
        } catch (InvalidArgumentException) {
            if ($service->settings() !== ['yellow_days' => 5, 'red_days' => 10]) throw new RuntimeException('Invalid save changed settings');
        }
    }
    echo "Follow-up configuration checks passed.\n";
} finally {
    foreach ($original as $key => $value) {
        if ($value === '') $config->deleteAppValue('ticky_crm', $key);
        else $config->setAppValue('ticky_crm', $key, $value);
    }
}
