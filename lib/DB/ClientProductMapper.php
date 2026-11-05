<?php

namespace OCA\TickyCRM\DB;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/** @template-extends QBMapper<ClientProduct> */
class ClientProductMapper extends QBMapper {
    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'ticky_client_products', ClientProduct::class);
    }

    /** @return ClientProduct[] */
    public function findByClient(int $clientId): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')->from($this->tableName)
            ->where($qb->expr()->eq('client_id', $qb->createNamedParameter($clientId, IQueryBuilder::PARAM_INT)))
            ->orderBy('created_at', 'DESC')->addOrderBy('id', 'DESC');
        return $this->findEntities($qb);
    }

    /** @return ClientProduct[] */
    public function findByProduct(int $productId): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')->from($this->tableName)
            ->where($qb->expr()->eq('product_id', $qb->createNamedParameter($productId, IQueryBuilder::PARAM_INT)));
        return $this->findEntities($qb);
    }

    public function findByUuid(string $uuid, int $clientId): ClientProduct {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')->from($this->tableName)
            ->where($qb->expr()->eq('uuid', $qb->createNamedParameter($uuid)))
            ->andWhere($qb->expr()->eq('client_id', $qb->createNamedParameter($clientId, IQueryBuilder::PARAM_INT)));
        return $this->findEntity($qb);
    }
}
