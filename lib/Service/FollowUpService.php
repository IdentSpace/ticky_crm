<?php

namespace OCA\TickyCRM\Service;

use OCP\IConfig;

class FollowUpService {
    public function __construct(private IConfig $config) {}

    public function settings(): array {
        return [
            'yellow_days' => (int)$this->config->getAppValue('ticky_crm', 'follow_up_yellow_days', '30'),
            'red_days' => (int)$this->config->getAppValue('ticky_crm', 'follow_up_red_days', '60'),
        ];
    }

    public function save(int $yellowDays, int $redDays): void {
        if ($yellowDays < 1 || $redDays <= $yellowDays || $redDays > 36500) {
            throw new \InvalidArgumentException('follow_up_intervals');
        }
        $this->config->setAppValue('ticky_crm', 'follow_up_yellow_days', (string)$yellowDays);
        $this->config->setAppValue('ticky_crm', 'follow_up_red_days', (string)$redDays);
    }

    public function status(array $client, ?\DateTimeImmutable $today = null): string {
        if ($client['status'] === 'inactive') return 'inactive';
        if ($client['follow_up_mode'] === 'manual') return $client['follow_up_manual'];
        if (!$client['last_contact_date']) return 'unknown';
        $today ??= new \DateTimeImmutable('today');
        $date = new \DateTimeImmutable($client['last_contact_date']);
        $days = (int)$date->diff($today)->format('%r%a');
        $settings = $this->settings();
        return $days >= $settings['red_days'] ? 'red' : ($days >= $settings['yellow_days'] ? 'yellow' : 'green');
    }
}
