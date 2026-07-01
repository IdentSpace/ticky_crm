<?php

namespace OCA\TickyCRM\Controller;

use OCA\TickyCRM\DB\ClientMapper;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IDBConnection;
use OCP\L10N\IFactory as L10NFactory;

class ActivityController extends Controller {

    private ClientMapper $mapper;
    private IDBConnection $db;
    private L10NFactory $l10nFactory;

    public function __construct(
        string $appName,
        IRequest $request,
        ClientMapper $mapper,
        IDBConnection $db,
        L10NFactory $l10nFactory
    ) {
        parent::__construct($appName, $request);
        $this->mapper = $mapper;
        $this->db = $db;
        $this->l10nFactory = $l10nFactory;
    }

    /**
     * Holt die Aktivitäten-Historie für einen spezifischen Kunden via UUID.
     * Direct-DB-FallBack, da IManager kein Lese-Interface besitzt.
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     * * @param string $uuid Die UUID des Kunden aus dem Frontend
     * @return DataResponse
     */
    public function getClientActivities(string $uuid): DataResponse {
        try {
            // 1. Client anhand der UUID über den Mapper suchen
            $client = $this->mapper->findByUuid($uuid);
            if (!$client) {
                return new DataResponse([
                    'success' => false,
                    'message' => 'Client not found'
                ], 404);
            }

            // 2. QueryBuilder aufbauen und direkt aus der oc_activity-Tabelle lesen
            $query = $this->db->getQueryBuilder();
            $query->select('*')
                ->from('activity') // Nextcloud mappt das automatisch auf das korrekte Präfix (z.B. oc_activity)
                ->where($query->expr()->eq('app', $query->createNamedParameter('ticky_crm')))
                ->andWhere($query->expr()->eq('object_type', $query->createNamedParameter('client')))
                ->andWhere($query->expr()->eq('object_id', $query->createNamedParameter((int)$client->getId())))
                ->orderBy('timestamp', 'DESC')
                ->setMaxResults(50); // Die letzten 50 Einträge reichen dicke für die Sidebar

            $rows = $query->execute()->fetchAll();

            // 3. Übersetzungsinstanz für Ticky CRM via Factory anfordern
            // Schnappt sich automatisch die Sprache des aktuell eingeloggten Nextcloud-Users
            $l = $this->l10nFactory->get('ticky_crm');

            // 4. Daten transformieren und Platzhalter in den Subjects live übersetzen
            $result = array_map(function($row) use ($l) {
                $subject = $row['subject'];

                // Extrahiere die Parameter (z.B. {"name":"Demo GmbH", "uuid":"1"})
                $params = json_decode($row['subjectparams'], true) ?? [];

                // l10n erwartet die Parameter als sequentielles Array für vsprintf.
                // Da wir 'name' immer drin haben, wird es sauber an %s übergeben.
                $paramValues = array_values($params);

                // Übersetzt z.B. "client_updated" anhand deiner l10n/de.json
                $parsedSubject = $l->t($subject, $paramValues);

                return [
                    'id'            => (int)$row['activity_id'],
                    'subject'       => $subject,
                    'parsedSubject' => $parsedSubject, // Das fertige HTML/Text-Ergebnis für Vue
                    'timestamp'     => (int)$row['timestamp'],
                    'user'          => $row['user'] // Der auslösende User (z.B. admin)
                ];
            }, $rows);

            return new DataResponse($result);

        } catch (\Throwable $e) {
            return new DataResponse([
                'success' => false,
                'error'   => 'Could not load activities',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}