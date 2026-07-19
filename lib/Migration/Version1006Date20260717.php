<?php
namespace OCA\TickyCRM\Migration;

use Closure;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;
use OCP\Server;

class Version1006Date20260717 extends SimpleMigrationStep {

    public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
    }

    public function changeSchema(IOutput $output, \Closure $schemaClosure, array $options) {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        $tickyMappingTable = 'ticky_client_contacts';
        $tickyClientsTable = 'ticky_clients';

        if (!$schema->hasTable($tickyMappingTable)) {
            $table = $schema->createTable($tickyMappingTable);

            $table->addColumn('id', 'integer', [
                'autoincrement' => true,
                'notnull' => true,
                'length' => 11,
            ]);

            $table->addColumn('client_id', 'integer', [
                'notnull' => true,
                'unsigned' => true,
                'comment' => 'ID aus der ticky_clients Tabelle',
            ]);

            $table->addColumn('card_id', 'integer', [
                'notnull' => true,
                'length' => 11,
                'comment' => 'ID aus der Nextcloud oc_cards Tabelle',
            ]);

            $table->addColumn('created_at', 'datetime', [
                'notnull' => true,
                'default' => 'CURRENT_TIMESTAMP',
            ]);

            $table->setPrimaryKey(['id']);
            $table->addIndex(['client_id'], 'ticky_client_idx');
            $table->addIndex(['card_id'], 'ticky_card_idx');

            $table->addUniqueIndex(['client_id', 'card_id'], 'ticky_uniq_mapping');
            $table->addForeignKeyConstraint($tickyClientsTable, ['client_id'], ['id'], ['onDelete' => 'CASCADE']);
        }

        return $schema;
    }

    public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {

    }
}