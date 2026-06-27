<?php
namespace OCA\TickyCRM\DB;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class ClientMapper extends QBMapper {

    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'ticky_clients', Client::class);
    }

    /**
     * Findet einen Kunden anhand seiner öffentlichen UUID
     */
    public function findByUuid(string $uuid): Client {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->tableName)
            ->where($qb->expr()->eq('uuid', $qb->createNamedParameter($uuid)));

        return $this->findEntity($qb);
    }

    /**
     * Holt alle Kunden aus der Datenbank
     */
    public function findAllClients(): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')->from($this->tableName);

        return $this->findEntities($qb);
    }
}