<?php
namespace OCA\TickyCRM\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCP\IGroupManager;
use OCP\IUserManager;
use OCA\TickyCRM\Service\AccessService;
use OCA\DAV\CardDAV\CardDavBackend;
use OCP\App\IAppManager;
use OCP\Server;
use OCP\IUserSession;
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

        $groups = array_map(
            fn($group) => ['id' => $group->getGID(), 'label' => $group->getDisplayName()],
            $this->groupManager->search('')
        );

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
     * @AdminRequired
     */
    public function saveSettings(array $groups, array $users): JSONResponse {
        $this->accessService->saveSettings($groups, $users);
        return new JSONResponse(['success' => true]);
    }

    /**
     * Erstellt ein zentrales Adressbuch für die App
     * @AdminRequired
     */
    public function createAddressBook(): JSONResponse {
        try {
            // Prüfen, ob Contacts App installiert ist
            $appManager = \OC::$server->get(IAppManager::class);
            if (!$appManager->isInstalled('contacts')) {
                return new JSONResponse([
                    'success' => false,
                    'error' => 'Contacts app is not installed'
                ], 400);
            }

            // System-Principal anstelle des Users nutzen
            $principalUri = 'principals/app/ticky';

            /** @var CardDavBackend $cardDavBackend */
            $cardDavBackend = Server::get(CardDavBackend::class);

            // System-Adressbücher abrufen (Nutzt dieselbe Methode, benötigt aber den System-Principal)
            $existing = $cardDavBackend->getAddressBooksForUser($principalUri);
            foreach ($existing as $ab) {
                if ($ab['{DAV:}displayname'] === 'Ticky CRM Kontakte') {
                    return new JSONResponse([
                        'success' => false,
                        'error' => 'System address book already exists',
                        'addressbook_id' => $ab['id'],
                        'uri' => $ab['uri']
                    ], 409);
                }
            }

            // Erstellt das Adressbuch auf Systemebene
            $addressBookId = $cardDavBackend->createAddressBook(
                $principalUri,
                'ticky-crm-kontakte',
                [
                    '{DAV:}displayname' => 'Ticky CRM Kontakte',
                ]
            );

            return new JSONResponse([
                'success' => true,
                'addressbook_id' => $addressBookId,
                'uri' => 'ticky-crm-kontakte',
                'principaluri' => $principalUri
            ]);
        } catch (\Exception $e) {
            return new JSONResponse([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}