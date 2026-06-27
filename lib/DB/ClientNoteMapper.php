<?php

namespace OCA\TickyCRM\DB;

use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

class ClientNoteMapper extends QBMapper {

    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'ticky_client_notes', ClientNote::class);
    }

    /**
     * Findet eine einzelne Notiz über ihre ID
     */
    public function findById(int $id): ClientNote {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->tableName)
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($id, \PDO::PARAM_INT)));

        return $this->findEntity($qb);
    }

    /**
     * Holt alle Notizen zu einem Kunden (neueste zuerst)
     * Wichtig: Muss exakt "findAllByClientId" heißen!
     */
    public function findAllByClientId(int $clientId): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->tableName)
            ->where($qb->expr()->eq('client_id', $qb->createNamedParameter($clientId, \PDO::PARAM_INT)))
            ->orderBy('created_at', 'DESC');

        return $this->findEntities($qb);
    }
}