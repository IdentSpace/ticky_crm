<?php

namespace OCA\TickyCRM\Migration;

use OCP\Migration\IMigrationStep;
use OCP\Migration\IOutput;
use OCP\DB\ISchemaWrapper;
use Closure;

class Version1002Date20260630 implements IMigrationStep {

    public function name(): string {
        return 'Tabelle für Kundenadressen hinzufügen';
    }

    public function description(): string {
        return 'Erstellt die Tabelle ticky_client_addresses für Multi-Standort-Support.';
    }

    public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
        // Nichts zu tun
    }

    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        $schema = $schemaClosure();

        if (!$schema->hasTable('ticky_client_addresses')) {
            $table = $schema->createTable('ticky_client_addresses');

            $table->addColumn('id', 'integer', ['autoincrement' => true, 'unsigned' => true]);
            $table->addColumn('client_id', 'integer', ['unsigned' => true, 'notnull' => true]);
            $table->addColumn('type', 'string', ['length' => 32, 'default' => 'main']); // billing, shipping, branch
            $table->addColumn('label', 'string', ['length' => 255, 'notnull' => false]); // z.B. "Hauptsitz"
            $table->addColumn('street', 'string', ['length' => 255, 'notnull' => true]);
            $table->addColumn('house_number', 'string', ['length' => 32, 'notnull' => false]);
            $table->addColumn('address_addition', 'string', ['length' => 255, 'notnull' => false]);
            $table->addColumn('postal_code', 'string', ['length' => 32, 'notnull' => true]);
            $table->addColumn('city', 'string', ['length' => 255, 'notnull' => true]);
            $table->addColumn('country_code', 'string', ['length' => 2, 'default' => 'DE']); // ISO-2
            $table->addColumn('created_at', 'datetime', ['notnull' => false]);
            $table->addColumn('updated_at', 'datetime', ['notnull' => false]);

            $table->setPrimaryKey(['id']);

            $table->addIndex(['client_id'], 'ticky_crm_adr_cid_idx');
            $table->addIndex(['client_id', 'type'], 'ticky_crm_adr_c_type_idx');

            $table->addForeignKeyConstraint(
                $schema->getTable('ticky_clients'),
                ['client_id'],
                ['id'],
                ['onDelete' => 'CASCADE']
            );
        }

        return $schema;
    }

    public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
        // Nichts zu tun
    }
}