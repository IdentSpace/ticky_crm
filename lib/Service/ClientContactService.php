<?php
namespace OCA\TickyCRM\Service;

use OCP\IURLGenerator;
use OCP\L10N\IFactory;
use OCA\TickyCRM\DB\ClientContact;
use OCA\TickyCRM\DB\ClientContactMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use Psr\Log\LoggerInterface;
use Sabre\VObject\Reader;

class ClientContactService {

    public function __construct(
        private ClientContactMapper $clientContactMapper,
        private IDBConnection $db,
        private AccessService $accessService,
        private LoggerInterface $logger,
        private IURLGenerator $urlGenerator,
        private ActivityService $activityService,
    ) {
    }

    public function linkContactToClient(int $clientId, int $cardId): void {
        if ($this->clientContactMapper->existsLink($clientId, $cardId)) {
            return;
        }

        $link = new ClientContact();
        $link->setClientId($clientId);
        $link->setCardId($cardId);
        $link->setCreatedAt(new \DateTime());

        $this->clientContactMapper->insert($link);

        $this->logContactActivity($clientId, $cardId, 'contact_linked');
    }

    public function unlinkContactFromClient(int $clientId, int $cardId): void {
        $this->logContactActivity($clientId, $cardId, 'contact_unlinked');
        $this->clientContactMapper->deleteLink($clientId, $cardId);
    }

    /**
     * Kontaktliste eines Kunden, angereichert mit Live-Daten aus dem vCard.
     */
    public function getContactsForClient(int $clientId): array {
        $links = $this->clientContactMapper->findByClient($clientId);
        if (empty($links)) {
            return [];
        }

        $cardIds = array_map(fn (ClientContact $l) => $l->getCardId(), $links);
        $cards = $this->getCardsByIds($cardIds);

        $result = [];
        foreach ($cards as $card) {
            $result[] = $this->mapCardToContact($card);
        }

        return $result;
    }

    /**
     * Direkter Lookup gegen 'cards' per numerischer ID – CardDavBackend
     * bietet dafür offiziell keine Methode (nur getCard() per URI).
     */
    private function getCardsByIds(array $cardIds): array {
        if (empty($cardIds)) {
            return [];
        }

        $qb = $this->db->getQueryBuilder();
        $qb->select('id', 'uri', 'uid', 'carddata')
            ->from('cards')
            ->where($qb->expr()->in('id', $qb->createNamedParameter(
                $cardIds,
                IQueryBuilder::PARAM_INT_ARRAY
            )));

        $result = $qb->executeQuery();
        $rows = $result->fetchAll();
        $result->closeCursor();

        return $rows;
    }

    public function mapCardToContact(array $card): array {
        $uid = '';
        $emails = [];
        $phones = [];
        $displayName = 'Unbekannt';

        try {
            $vcard = Reader::read($card['carddata']);
            $uid = isset($vcard->UID) ? (string)$vcard->UID : '';
            $emails = $this->extractTypedValues($vcard, 'EMAIL');
            $phones = $this->extractTypedValues($vcard, 'TEL');

            $displayName = isset($vcard->FN) ? (string)$vcard->FN : '';
            if ($displayName === '' && isset($vcard->N)) {
                $displayName = trim(str_replace(';', ' ', (string)$vcard->N));
            }
        } catch (\Throwable $e) {
            $this->logger->warning(
                'Ticky CRM: vCard konnte nicht geparst werden (id: ' . ($card['id'] ?? '?') . ')',
                ['app' => 'ticky_crm']
            );
            $displayName = 'Unbekannt';
        }

        return [
            'id' => (int)($card['id'] ?? 0),
            'uid' => $uid,
            'displayName' => $displayName !== '' ? $displayName : 'Unbekannt',
            'emails' => $emails,
            'phones' => $phones,
            'url' => $uid !== '' ? $this->buildContactsAppUrl($uid) : '',
        ];
    }

    /**
     * Extrahiert alle Instanzen einer wiederholbaren vCard-Property (z. B. EMAIL, TEL)
     * inkl. ihrer TYPE-Parameter (Home/Work/Cell/...).
     *
     * @return array<int, array{value: string, type: ?string}>
     */
    private function extractTypedValues(\Sabre\VObject\Component\VCard $vcard, string $propertyName): array {
        $result = [];

        foreach ($vcard->select($propertyName) as $property) {
            $types = [];

            if (isset($property['TYPE'])) {
                foreach ($property['TYPE'] as $typeValue) {
                    // TYPE kann kommasepariert mehrere Werte enthalten (TYPE=HOME,VOICE)
                    foreach (explode(',', (string)$typeValue) as $singleType) {
                        $singleType = trim($singleType);
                        if ($singleType !== '') {
                            $types[] = ucfirst(strtolower($singleType));
                        }
                    }
                }
            }

            $value = (string)$property;
            if ($value === '') {
                continue;
            }

            $result[] = [
                'value' => $value,
                'type' => !empty($types) ? implode(', ', array_unique($types)) : null,
            ];
        }

        return $result;
    }

    /**
     * Baut den Direct-Link zum Kontakt in der nativen Contacts-App.
     */
    private function buildContactsAppUrl(string $uid): string {
        $contactToken = $uid . '~' . $this->accessService->getAddressBookUri()
            . '_shared_by_' . $this->accessService->getOwnerSlug();

        $relativeUrl = $this->urlGenerator->linkToRoute('contacts.contacts.direct', [
            'contact' => base64_encode($contactToken),
        ]);

        return $this->urlGenerator->getAbsoluteURL($relativeUrl);
    }

    /**
     * Wie mapCardToContact(), aber zusätzlich mit Auflösung der numerischen
     * card_id anhand von uri+addressbookid - notwendig, weil
     * CardDavBackend::search() kein 'id' im Ergebnis liefert (nur
     * addressbookid, carddata, uri).
     */
    public function mapSearchResultToContact(array $card): array {
        $contact = $this->mapCardToContact($card);

        if ($contact['id'] === 0 && isset($card['uri'], $card['addressbookid'])) {
            $contact['id'] = $this->resolveCardId((int)$card['addressbookid'], $card['uri']);
        }

        return $contact;
    }

    private function resolveCardId(int $addressBookId, string $uri): int {
        $qb = $this->db->getQueryBuilder();
        $qb->select('id')
            ->from('cards')
            ->where($qb->expr()->eq('addressbookid', $qb->createNamedParameter($addressBookId, IQueryBuilder::PARAM_INT)))
            ->andWhere($qb->expr()->eq('uri', $qb->createNamedParameter($uri)));

        $result = $qb->executeQuery();
        $id = (int)$result->fetchOne();
        $result->closeCursor();

        return $id;
    }

    /**
     * Erstellt einen Activity-Eintrag für Verknüpfen/Entfernen eines Kontakts.
     * Bewusst mit eigenem Try/Catch: Ein Fehler beim Loggen darf niemals die
     * eigentliche Verknüpfungs-Operation zum Scheitern bringen (ActivityService::log()
     * wirft Exceptions weiter, statt sie selbst abzufangen).
     */
    private function logContactActivity(int $clientId, int $cardId, string $action): void {
        try {
            $cards = $this->getCardsByIds([$cardId]);
            $contactName = 'Unbekannt';

            if (!empty($cards)) {
                $contact = $this->mapCardToContact($cards[0]);
                $contactName = $contact['displayName'];
            }

            $this->activityService->log(
                objectType: 'client',
                action: $action,
                displayName: $contactName,
                objectId: $clientId,
                params: ['cardId' => $cardId],
            );
        } catch (\Throwable $e) {
            $this->logger->warning(
                'Ticky CRM: Activity für Kontakt-' . $action . ' konnte nicht erstellt werden: ' . $e->getMessage(),
                ['app' => 'ticky_crm']
            );
        }
    }
}