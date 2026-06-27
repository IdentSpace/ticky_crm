<?php

namespace OCA\TickyCRM\Migration;

use OCP\Migration\IMigrationStep;
use OCP\Migration\IOutput;
use OCP\DB\ISchemaWrapper;
use Closure;

class Version1000Date20260626 implements IMigrationStep {

    public function name(): string {
        return 'Initiales Tabellen-Setup für Ticky CRM';
    }

    public function description(): string {
        return 'Erstellt die Tabellen ticky_clients und ticky_client_notes.';
    }

    public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
        // Nichts zu tun
    }

    /**
     * Voll qualifizierte Parameter, um Import-Fehler im Core auszuschließen
     */
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        $schema = $schemaClosure();

        // 1. Tabelle: Stammdaten der Kunden (Clients)
        if (!$schema->hasTable('ticky_clients')) {
            $table = $schema->createTable('ticky_clients');

            $table->addColumn('id', 'integer', ['autoincrement' => true, 'unsigned' => true]);
            $table->addColumn('uuid', 'string', ['length' => 36, 'notnull' => true]);
            $table->addColumn('client_number', 'string', ['length' => 64, 'notnull' => true]);

            $table->addColumn('name', 'string', ['length' => 255, 'notnull' => true]);
            $table->addColumn('type', 'string', ['length' => 32, 'default' => 'company']);
            $table->addColumn('status', 'string', ['length' => 32, 'default' => 'lead']);

            $table->addColumn('contact_email', 'string', ['length' => 255, 'notnull' => false]);
            $table->addColumn('invoice_email', 'string', ['length' => 255, 'notnull' => false]);
            $table->addColumn('phone', 'string', ['length' => 64, 'notnull' => false]);
            $table->addColumn('website', 'string', ['length' => 255, 'notnull' => false]);

            $table->addColumn('vat_id', 'string', ['length' => 32, 'notnull' => false]);
            $table->addColumn('tax_number', 'string', ['length' => 64, 'notnull' => false]);
            $table->addColumn('register_court', 'string', ['length' => 255, 'notnull' => false]);
            $table->addColumn('register_number', 'string', ['length' => 64, 'notnull' => false]);

            $table->addColumn('nc_file_id', 'integer', ['unsigned' => true, 'notnull' => false]);
            $table->addColumn('nc_folder_path', 'string', ['length' => 512, 'notnull' => false]);

            $table->addColumn('created_at', 'datetime', ['notnull' => false]);
            $table->addColumn('updated_at', 'datetime', ['notnull' => false]);

            $table->setPrimaryKey(['id']);
            $table->addUniqueIndex(['uuid'], 'ticky_crm_cl_uuid_idx');
            $table->addUniqueIndex(['client_number'], 'ticky_crm_cl_num_idx');
            $table->addIndex(['nc_file_id'], 'ticky_crm_cl_fid_idx');
        }

        // 2. Tabelle: Interaktionen & Notizen (Timeline)
        if (!$schema->hasTable('ticky_client_notes')) {
            $table = $schema->createTable('ticky_client_notes');

            $table->addColumn('id', 'integer', ['autoincrement' => true, 'unsigned' => true]);
            $table->addColumn('client_id', 'integer', ['unsigned' => true, 'notnull' => true]);
            $table->addColumn('user_id', 'string', ['length' => 64, 'notnull' => true]);

            $table->addColumn('type', 'string', ['length' => 32, 'default' => 'note']);
            $table->addColumn('title', 'string', ['length' => 255, 'notnull' => false]);
            $table->addColumn('content', 'text', ['notnull' => true]);

            $table->addColumn('created_at', 'datetime', ['notnull' => false]);
            $table->addColumn('updated_at', 'datetime', ['notnull' => false]);

            $table->setPrimaryKey(['id']);
            $table->addIndex(['client_id'], 'ticky_crm_nt_cid_idx');

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