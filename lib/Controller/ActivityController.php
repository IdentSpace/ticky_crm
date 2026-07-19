<?php

namespace OCA\TickyCRM\Controller;

use OCA\TickyCRM\DB\ClientMapper;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IDBConnection;
use OCP\L10N\IFactory as L10NFactory;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;

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

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function getClientActivities(string $uuid): DataResponse {
        try {
            $client = $this->mapper->findByUuid($uuid);
            if (!$client) {
                return new DataResponse([
                    'success' => false,
                    'message' => 'Client not found'
                ], 404);
            }

            $query = $this->db->getQueryBuilder();
            $query->select('*')
                ->from('activity')
                ->where($query->expr()->eq('app', $query->createNamedParameter('ticky_crm')))
                ->andWhere($query->expr()->eq('object_type', $query->createNamedParameter('client')))
                ->andWhere($query->expr()->eq('object_id', $query->createNamedParameter((int)$client->getId())))
                ->orderBy('timestamp', 'DESC')
                ->setMaxResults(50);

            $rows = $query->execute()->fetchAll();

            $l = $this->l10nFactory->get('ticky_crm');

            $result = array_map(function($row) use ($l) {
                $subject = $row['subject'];
                $params = json_decode($row['subjectparams'], true) ?? [];

                $translatedSubject = (string)$l->t($subject);
                $replacements = [];
                foreach ($params as $key => $value) {
                    $replacements['{' . $key . '}'] = (string)$value;
                }
                $parsedSubject = strtr($translatedSubject, $replacements);

                return [
                    'id'            => (int)$row['activity_id'],
                    'subject'       => $subject,
                    'parsedSubject' => $parsedSubject,
                    'objectName'    => $params['name'] ?? null, // <- aus subjectparams statt nicht-existenter DB-Spalte
                    'timestamp'     => (int)$row['timestamp'],
                    'user'          => $row['user']
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