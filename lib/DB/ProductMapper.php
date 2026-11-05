<?php

namespace OCA\TickyCRM\DB;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<Product>
 */
class ProductMapper extends QBMapper {
    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'ticky_products', Product::class);
    }

    public function findById(int $id): Product {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')->from($this->tableName)
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
        return $this->findEntity($qb);
    }

    public function findByUuid(string $uuid): Product {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')->from($this->tableName)
            ->where($qb->expr()->eq('uuid', $qb->createNamedParameter($uuid)));
        return $this->findEntity($qb);
    }

    public function findByProductNumber(string $productNumber): Product {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')->from($this->tableName)
            ->where($qb->expr()->eq('product_number', $qb->createNamedParameter($productNumber)));
        return $this->findEntity($qb);
    }

    /** @return Product[] */
    public function findAllProducts(int $limit = 100, int $offset = 0, string $query = '', bool $activeOnly = false): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')->from($this->tableName)
            ->orderBy($qb->createFunction('LOWER(name)'), 'ASC')->addOrderBy('id', 'ASC')
            ->setMaxResults($limit)->setFirstResult($offset);
        if ($activeOnly) {
            $qb->andWhere($qb->expr()->eq('status', $qb->createNamedParameter(Product::STATUS_ACTIVE)));
        }
        if ($query !== '') {
            $pattern = $qb->createNamedParameter('%' . $this->db->escapeLikeParameter($query) . '%');
            $qb->andWhere($qb->expr()->orX($qb->expr()->iLike('name', $pattern), $qb->expr()->iLike('product_number', $pattern)));
        }
        return $this->findEntities($qb);
    }
}
