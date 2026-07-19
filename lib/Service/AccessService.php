<?php

namespace OCA\TickyCRM\Service;

use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IUserManager;
use OCP\IL10N;
use OCA\DAV\CardDAV\CardDavBackend;
use OCA\DAV\CardDAV\AddressBook;
use Psr\Log\LoggerInterface;

class AccessService {
    private IConfig $config;
    private IGroupManager $groupManager;
    private IUserManager $userManager;
    private CardDavBackend $cardDavBackend;
    private IL10N $l10n;
    private LoggerInterface $logger;

    private string $appName = 'ticky_crm';
    // ACHTUNG: Kein von Sabre/Nextcloud aufgelöster Principal (nur
    // principals/users/*, principals/groups/*, principals/system/* werden
    // vom PrincipalBackend erkannt). Für den DB-Insert selbst ist das kein
    // Problem, aber bei allem was ACL/Owner-Auflösung braucht (Sharing,
    // Anzeige in der Contacts-App) kann es zu stillen Fehlern kommen.
    private string $principalUri = 'principals/app/ticky';
    private string $addressBookUri = 'tickycrm-contacts';

    public function getAddressBookUri(): string {
        return $this->addressBookUri;
    }

    public function getOwnerSlug(): string {
        $lastSlash = strrpos($this->principalUri, '/');
        return $lastSlash === false ? $this->principalUri : substr($this->principalUri, $lastSlash + 1);
    }

    public function __construct(
        IConfig $config,
        IGroupManager $groupManager,
        IUserManager $userManager,
        CardDavBackend $cardDavBackend,
        IL10N $l10n,
        LoggerInterface $logger
    ) {
        $this->config = $config;
        $this->groupManager = $groupManager;
        $this->userManager = $userManager;
        $this->cardDavBackend = $cardDavBackend;
        $this->l10n = $l10n;
        $this->logger = $logger;
    }

    /**
     * Prüft, ob ein Benutzer Zugriff auf die App hat
     */
    public function canAccess(?string $userId): bool {
        if ($userId === null) {
            return false;
        }

        if ($this->groupManager->isAdmin($userId)) {
            return true;
        }

        $allowedUsers = json_decode($this->config->getAppValue($this->appName, 'allowed_users', '[]'), true);
        if (in_array($userId, $allowedUsers, true)) {
            return true;
        }

        $allowedGroups = json_decode($this->config->getAppValue($this->appName, 'allowed_groups', '[]'), true);
        foreach ($allowedGroups as $groupId) {
            if ($this->groupManager->isInGroup($userId, $groupId)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Holt die aktuell erlaubten Gruppen und Benutzer
     */
    public function getAllowedSettings(): array {
        return [
            'groups' => json_decode($this->config->getAppValue($this->appName, 'allowed_groups', '[]'), true),
            'users' => json_decode($this->config->getAppValue($this->appName, 'allowed_users', '[]'), true),
        ];
    }

    /**
     * Speichert die Einstellungen und triggert die Synchronisation der Freigaben
     */
    public function saveSettings(array $groups, array $users): void {
        $this->config->setAppValue($this->appName, 'allowed_groups', json_encode($groups));
        $this->config->setAppValue($this->appName, 'allowed_users', json_encode($users));

        $this->syncAddressBookShares($groups, $users);
    }

    /**
     * Öffentlicher Einstiegspunkt, um die Adressbuch-Freigaben anhand der
     * aktuell gespeicherten Einstellungen zu synchronisieren (z. B. für
     * Repair-Steps oder CLI-Aufrufe, ohne dass neue Settings gespeichert werden).
     */
    public function syncAddressBookSharesFromSettings(): void {
        $settings = $this->getAllowedSettings();
        $this->syncAddressBookShares($settings['groups'], $settings['users']);
    }

    /**
     * Synchronisiert die Shares des System-Adressbuchs über die offiziellen APIs.
     */
    private function syncAddressBookShares(array $groups, array $users): void {
        $addressBookProperties = $this->ensureSystemAddressBookProperties();
        if (!$addressBookProperties) {
            $this->logger->warning('Ticky CRM: Adressbuch-Freigaben übersprungen.', ['app' => 'ticky_crm']);
            return;
        }

        $allAllowedUserIds = [];
        foreach ($users as $userId) {
            if ($this->userManager->userExists($userId)) {
                $allAllowedUserIds[] = $userId;
            }
        }
        foreach ($groups as $groupId) {
            $group = $this->groupManager->get($groupId);
            if ($group !== null) {
                foreach ($group->getUsers() as $user) {
                    $allAllowedUserIds[] = $user->getUID();
                }
            }
        }
        $adminGroup = $this->groupManager->get('admin');
        if ($adminGroup !== null) {
            foreach ($adminGroup->getUsers() as $admin) {
                $allAllowedUserIds[] = $admin->getUID();
            }
        }
        $allAllowedUserIds = array_unique($allAllowedUserIds);

        // Gewünschter Ziel-Zustand: href => userId
        $desiredHrefs = [];
        foreach ($allAllowedUserIds as $userId) {
            $desiredHrefs['principal:principals/users/' . $userId] = $userId;
        }

        // Ist-Zustand
        $currentShares = $this->cardDavBackend->getShares($addressBookProperties['id']);
        $currentHrefs = [];
        foreach ($currentShares as $share) {
            $href = is_array($share) ? $share['href'] : $share->href;
            $currentHrefs[$href] = true;
        }

        // Nur wirklich nicht mehr erlaubte Hrefs entfernen
        $sharesToRemove = array_values(array_diff(array_keys($currentHrefs), array_keys($desiredHrefs)));

        // ALLE gewünschten Hrefs erneut in "add" — updateShares() behandelt bereits
        // bestehende Hrefs als Update (u. a. für readOnly), nicht als Duplikat-Fehler.
        // Wichtig: hier steht kein Href gleichzeitig in $remove.
        $sharesToAdd = [];
        foreach ($desiredHrefs as $href => $userId) {
            $sharesToAdd[] = [
                'href' => $href,
                'commonName' => $userId,
                'readOnly' => false,
            ];
        }

        try {
            if (!isset($addressBookProperties['principaluri'])) {
                $addressBookProperties['principaluri'] = $this->principalUri;
            }

            $addressBook = new AddressBook($this->cardDavBackend, $addressBookProperties, $this->l10n);
            $addressBook->updateShares($sharesToAdd, $sharesToRemove);
        } catch (\Throwable $e) {
            $this->logger->error(
                'Ticky CRM: Adressbuch-Freigaben konnten nicht synchronisiert werden: ' . $e->getMessage(),
                ['app' => 'ticky_crm', 'exception' => $e]
            );
        }
    }

    /**
     * Hilfsmethode: Holt oder erstellt das Adressbuch und liefert die rohen Eigenschaften zurück
     */
    public function ensureSystemAddressBookProperties(): ?array {
        try {
            $existingBooks = $this->cardDavBackend->getAddressBooksForUser($this->principalUri);

            foreach ($existingBooks as $book) {
                if ($book['uri'] === $this->addressBookUri) {
                    return $book;
                }
            }

            // Wenn es noch nicht existiert, erstellen.
            // WICHTIG: createAddressBook() akzeptiert NUR '{DAV:}displayname' und
            // '{urn:ietf:params:xml:ns:carddav}addressbook-description'. Jede andere
            // Property (z. B. das vorherige '{http://sabredav.org/ns}read-only')
            // führt zu einer BadRequest-Exception, bevor überhaupt ein Datensatz
            // angelegt wird.
            $this->cardDavBackend->createAddressBook(
                $this->principalUri,
                $this->addressBookUri,
                [
                    '{DAV:}displayname' => 'Ticky CRM Kontakte',
                ]
            );

            // Nach Erstellung neu laden, um die generierte ID zu erhalten
            $existingBooks = $this->cardDavBackend->getAddressBooksForUser($this->principalUri);
            foreach ($existingBooks as $book) {
                if ($book['uri'] === $this->addressBookUri) {
                    return $book;
                }
            }

            $this->logger->error(
                'Ticky CRM: Adressbuch wurde erstellt, konnte danach aber nicht wiedergefunden werden.',
                ['app' => 'ticky_crm', 'principalUri' => $this->principalUri, 'uri' => $this->addressBookUri]
            );
        } catch (\Throwable $e) {
            $this->logger->error(
                'Ticky CRM Adressbuch konnte nicht erstellt werden: ' . $e->getMessage(),
                ['app' => 'ticky_crm', 'exception' => $e]
            );
        }

        return null;
    }
}