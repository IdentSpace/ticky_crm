<?php
namespace OCA\TickyCRM\DB;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\IResultRunner;
use OCP\IDBConnection;

class AddressMapper extends QBMapper {

    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'ticky_client_addresses', Address::class);
    }

    public function findById(int $id): Address {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->tableName)
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($id, \PDO::PARAM_INT)));

        return $this->findEntity($qb);
    }


    /**
     * Findet alle Adressen eines Kunden über seine UUID
     */
    public function findByClientUuid(string $clientUuid): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('a.*')
            ->from('ticky_client_addresses', 'a')
            ->join('a', 'ticky_clients', 'c', $qb->expr()->eq('a.client_id', 'c.id'))
            ->where($qb->expr()->eq('c.uuid', $qb->createNamedParameter($clientUuid)));

        return $this->findEntities($qb);
    }

    public function findAddressesByClientId(int $clientId): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from('ticky_client_addresses')
            ->where($qb->expr()->eq('client_id', $qb->createNamedParameter($clientId)));

        return $this->findEntities($qb);
    }
}