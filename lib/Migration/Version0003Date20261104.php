<?php

namespace OCA\TickyCRM\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version0003Date20261104 extends SimpleMigrationStep {
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();
        if ($schema->hasTable('ticky_products')) {
            return null;
        }

        $table = $schema->createTable('ticky_products');
        $table->addColumn('id', 'integer', ['autoincrement' => true, 'unsigned' => true, 'notnull' => true]);
        $table->addColumn('uuid', 'string', ['length' => 36, 'notnull' => true]);
        $table->addColumn('product_number', 'string', ['length' => 64, 'notnull' => true]);
        $table->addColumn('name', 'string', ['length' => 255, 'notnull' => true]);
        $table->addColumn('description', 'text', ['notnull' => false]);
        $table->addColumn('type', 'string', ['length' => 32, 'notnull' => true, 'default' => 'product']);
        $table->addColumn('procurement_type', 'string', ['length' => 32, 'notnull' => false]);
        $table->addColumn('category', 'string', ['length' => 255, 'notnull' => false]);
        $table->addColumn('unit', 'string', ['length' => 32, 'notnull' => true]);
        $table->addColumn('status', 'string', ['length' => 32, 'notnull' => true, 'default' => 'active']);
        $table->addColumn('currency', 'string', ['length' => 3, 'notnull' => true]);

        // Decimal values must remain strings in PHP and JSON, never floats.
        $table->addColumn('sales_price_net', 'decimal', ['precision' => 19, 'scale' => 4, 'notnull' => true]);
        $table->addColumn('sales_tax_rate', 'decimal', ['precision' => 5, 'scale' => 2, 'notnull' => true]);
        $table->addColumn('purchase_price_net', 'decimal', ['precision' => 19, 'scale' => 4, 'notnull' => false]);
        $table->addColumn('purchase_tax_rate', 'decimal', ['precision' => 5, 'scale' => 2, 'notnull' => false]);

        $table->addColumn('internal_note', 'text', ['notnull' => false]);
        $table->addColumn('created_at', 'datetime', ['notnull' => true]);
        $table->addColumn('updated_at', 'datetime', ['notnull' => true]);

        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['uuid'], 'ticky_pr_uuid_idx');
        $table->addUniqueIndex(['product_number'], 'ticky_pr_number_idx');
        $table->addIndex(['name'], 'ticky_pr_name_idx');
        $table->addIndex(['status'], 'ticky_pr_status_idx');

        return $schema;
    }
}
