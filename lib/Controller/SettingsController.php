<?php
namespace OCA\TickyCRM\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCP\IGroupManager;
use OCP\IUserManager;
use OCA\TickyCRM\Service\AccessService;

class SettingsController extends Controller {

    public function __construct(
        string $appName,
        IRequest $request,
        private AccessService $accessService,
        private IGroupManager $groupManager,
        private IUserManager $userManager
    ) {
        parent::__construct($appName, $request);
    }

    /**
     * @NoAdminRequired
     */
    public function getSettings(): JSONResponse {
        $settings = $this->accessService->getAllowedSettings();

        // Alle Nextcloud-Gruppen laden
        $groups = array_map(
            fn($group) => ['id' => $group->getGID(), 'label' => $group->getDisplayName()],
            $this->groupManager->search('')
        );

        // Alle Nextcloud-User laden
        $users = [];
        foreach ($this->userManager->search('') as $user) {
            $users[] = ['id' => $user->getUID(), 'label' => $user->getDisplayName()];
        }

        return new JSONResponse([
            'allowed_groups' => $settings['groups'],
            'allowed_users'  => $settings['users'],
            'all_groups'     => $groups,
            'all_users'      => $users,
        ]);
    }

    /**
     * Nur Admins dürfen Einstellungen speichern
     */
    public function saveSettings(array $groups, array $users): JSONResponse {
        $this->accessService->saveSettings($groups, $users);
        return new JSONResponse(['success' => true]);
    }
}