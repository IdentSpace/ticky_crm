<?php

namespace OCA\TickyCRM\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version0004Date20261104 extends SimpleMigrationStep {
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();
        if ($schema->hasTable('ticky_client_products')) {
            return null;
        }
        $table = $schema->createTable('ticky_client_products');
        $table->addColumn('id', 'integer', ['unsigned' => true, 'autoincrement' => true, 'notnull' => true]);
        $table->addColumn('uuid', 'string', ['length' => 36, 'notnull' => true]);
        $table->addColumn('client_id', 'integer', ['unsigned' => true, 'notnull' => true]);
        $table->addColumn('product_id', 'integer', ['unsigned' => true, 'notnull' => false]);
        $table->addColumn('product_number', 'string', ['length' => 64, 'notnull' => true]);
        $table->addColumn('name', 'string', ['length' => 255, 'notnull' => true]);
        $table->addColumn('unit', 'string', ['length' => 32, 'notnull' => true]);
        $table->addColumn('currency', 'string', ['length' => 3, 'notnull' => true]);
        $table->addColumn('quantity', 'decimal', ['precision' => 19, 'scale' => 4, 'notnull' => true]);
        foreach (['sales_price_net', 'purchase_price_net'] as $field) {
            $table->addColumn($field, 'decimal', ['precision' => 19, 'scale' => 4, 'notnull' => $field === 'sales_price_net']);
        }
        foreach (['sales_tax_rate', 'purchase_tax_rate'] as $field) {
            $table->addColumn($field, 'decimal', ['precision' => 5, 'scale' => 2, 'notnull' => $field === 'sales_tax_rate']);
        }
        $table->addColumn('created_at', 'datetime', ['notnull' => true]);
        $table->addColumn('updated_at', 'datetime', ['notnull' => true]);
        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['uuid'], 'ticky_cp_uuid_idx');
        $table->addIndex(['client_id'], 'ticky_cp_client_idx');
        $table->addIndex(['product_id'], 'ticky_cp_product_idx');
        $table->addForeignKeyConstraint($schema->getTable('ticky_clients'), ['client_id'], ['id'], ['onDelete' => 'CASCADE'], 'ticky_cp_client_fk');
        // Preserve agreed prices and labels when a catalog product is removed.
        $table->addForeignKeyConstraint($schema->getTable('ticky_products'), ['product_id'], ['id'], ['onDelete' => 'SET NULL'], 'ticky_cp_product_fk');
        return $schema;
    }
}
