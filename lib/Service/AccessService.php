<?php
namespace OCA\TickyCRM\Service;

use OCP\IConfig;
use OCP\IGroupManager;

class AccessService {
    private IConfig $config;
    private IGroupManager $groupManager;
    private string $appName = 'ticky_crm';

    public function __construct(IConfig $config, IGroupManager $groupManager) {
        $this->config = $config;
        $this->groupManager = $groupManager;
    }

    /**
     * Prüft, ob ein Benutzer Zugriff auf die App hat
     */
    public function canAccess(?string $userId): bool {
        if ($userId === null) {
            return false;
        }

        // 1. Admins haben IMMER Zugriff
        if ($this->groupManager->isAdmin($userId)) {
            return true;
        }

        // 2. Erlaubte Personen prüfen
        $allowedUsers = json_decode($this->config->getAppValue($this->appName, 'allowed_users', '[]'), true);
        if (in_array($userId, $allowedUsers, true)) {
            return true;
        }

        // 3. Erlaubte Gruppen prüfen
        $allowedGroups = json_decode($this->config->getAppValue($this->appName, 'allowed_groups', '[]'), true);
        foreach ($allowedGroups as $groupId) {
            if ($this->groupManager->isInGroup($userId, $groupId)) {
                return true;
            }
        }

        return false;
    }

    public function getAllowedSettings(): array {
        return [
            'groups' => json_decode($this->config->getAppValue($this->appName, 'allowed_groups', '[]'), true),
            'users' => json_decode($this->config->getAppValue($this->appName, 'allowed_users', '[]'), true),
        ];
    }

    public function saveSettings(array $groups, array $users): void {
        $this->config->setAppValue($this->appName, 'allowed_groups', json_encode($groups));
        $this->config->setAppValue($this->appName, 'allowed_users', json_encode($users));
    }
}