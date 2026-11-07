<?php
namespace OCA\TickyCRM\DB;

use OCP\AppFramework\Db\QBMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<Client>
 */
class ClientMapper extends QBMapper {

    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'ticky_clients', Client::class);
    }

    public function findById(int $id): Client {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')->from($this->tableName)->where($qb->expr()->eq('id', $qb->createNamedParameter($id)));
        return $this->findEntity($qb);
    }

    /**
     * Findet einen Kunden anhand seiner öffentlichen UUID
     */
    public function findByUuid(string $uuid, bool $withAddress = true): Client {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->tableName)
            ->where($qb->expr()->eq('uuid', $qb->createNamedParameter($uuid)));

        $client = $this->findEntity($qb);

        if($withAddress) {
            $client->setAddresses($this->getAddressesForClient($client->getId()));;
        }

        return $client;
    }

    /**
     * Holt alle Kunden aus der Datenbank
     */
    public function findAllClients(): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')->from($this->tableName)->orderBy($qb->createFunction('LOWER(name)'), 'ASC')->addOrderBy('id', 'ASC');

        $clients = $this->findEntities($qb);

        foreach ($clients as $client) {
            $client->setAddresses($this->getAddressesForClient($client->getId()));
        }

        return $clients;
    }

    /** Aggregate separately to avoid multiplying counts when both relations exist. */
    public function getRelationCounts(array $clientIds): array {
        $counts = [];
        if ($clientIds === []) {
            return $counts;
        }
        foreach (['product_count' => 'ticky_client_products', 'contact_count' => 'ticky_client_contacts'] as $field => $table) {
            $qb = $this->db->getQueryBuilder();
            $qb->select('links.client_id')->selectAlias($qb->func()->count('links.id'), 'total')
                ->from($table, 'links')
                ->where($qb->expr()->in('links.client_id', $qb->createNamedParameter($clientIds, IQueryBuilder::PARAM_INT_ARRAY)))
                ->groupBy('links.client_id');
            if ($field === 'contact_count') {
                $qb->innerJoin('links', 'cards', 'card', $qb->expr()->eq('links.card_id', 'card.id'));
            }
            $result = $qb->executeQuery();
            foreach ($result->fetchAll() as $row) {
                $counts[(int)$row['client_id']][$field] = (int)$row['total'];
            }
            $result->closeCursor();
        }
        return $counts;
    }

    /**
     * Hilfsmethode: Holt die Adressen direkt als hydrierte Address-Entities
     */
    private function getAddressesForClient(int $clientId): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from('ticky_client_addresses')
            ->where($qb->expr()->eq('client_id', $qb->createNamedParameter($clientId)));

        // Wir nutzen hier den Doctrine-Query, mappen es aber manuell in die Address-Entity,
        // damit wir keinen Zirkelbezug im Constructor der Mapper erzeugen.
        $result = $qb->executeQuery();
        $addresses = [];

        while ($row = $result->fetch()) {
            $addresses[] = [
                'id'              => (int)$row['id'],
                'client_id'        => (int)$row['client_id'],
                'type'            => $row['type'],
                'label'           => $row['label'],
                'street'          => $row['street'],
                'house_number'     => $row['house_number'],
                'address_addition' => $row['address_addition'],
                'postal_code'      => $row['postal_code'],
                'city'            => $row['city'],
                'country_code'     => $row['country_code']
            ];
        }

        $result->closeCursor();
        return $addresses;
    }
}
