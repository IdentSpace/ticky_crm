<?php

namespace OCA\TickyCRM\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Bringt Installationen auf den Stand, die Version0002Date20260717 in ihrer
 * ursprünglichen Fassung teilweise ausgeführt haben.
 *
 * Der fehlerhafte Foreign Key referenzierte die Zieltabelle über ihren Namen
 * statt über das Schema-Objekt, wodurch das Tabellenpräfix fehlte. Auf
 * MySQL/MariaDB erzeugt Doctrine daraus zwei Statements – CREATE TABLE und ein
 * separates ALTER TABLE ADD CONSTRAINT. Da DDL dort nicht transaktional ist,
 * konnte die Tabelle entstehen, während der Constraint scheiterte. Solche
 * Instanzen laufen anschließend in den hasTable()-Guard von Version0002 und
 * bekämen weder den Foreign Key noch die unsigned-Spalten je nachgereicht.
 *
 * Der Schritt ist idempotent: Er prüft den tatsächlichen Datenbankzustand und
 * gibt null zurück, wenn nichts zu tun ist. Auf frischen Installationen und
 * bei einem erneuten Durchlauf nach einem Teilfehler ist er damit folgenlos.
 */
class Version0002Date20260730 extends SimpleMigrationStep {

    private const MAPPING_TABLE = 'ticky_client_contacts';
    private const CLIENTS_TABLE = 'ticky_clients';
    private const UNSIGNED_COLUMNS = ['id', 'card_id'];

    public function __construct(
        private IDBConnection $db,
    ) {
    }

    /**
     * Verwaiste Zeilen entfernen, bevor der Constraint gesetzt wird –
     * andernfalls verweigert die Datenbank das ALTER TABLE.
     */
    public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        if (!$schema->hasTable(self::MAPPING_TABLE) || !$schema->hasTable(self::CLIENTS_TABLE)) {
            return;
        }

        $qb = $this->db->getQueryBuilder();
        $qb->selectDistinct('client_id')->from(self::MAPPING_TABLE);
        $result = $qb->executeQuery();
        $mappedIds = array_map('intval', $result->fetchAll(\PDO::FETCH_COLUMN));
        $result->closeCursor();

        if ($mappedIds === []) {
            return;
        }

        $qb = $this->db->getQueryBuilder();
        $qb->select('id')->from(self::CLIENTS_TABLE);
        $result = $qb->executeQuery();
        $clientIds = array_map('intval', $result->fetchAll(\PDO::FETCH_COLUMN));
        $result->closeCursor();

        $orphans = array_values(array_diff($mappedIds, $clientIds));
        if ($orphans === []) {
            return;
        }

        $output->warning(sprintf(
            'Ticky CRM: entferne %d verwaiste Kontaktverknüpfung(en) vor dem Setzen des Foreign Keys.',
            count($orphans)
        ));

        $qb = $this->db->getQueryBuilder();
        $qb->delete(self::MAPPING_TABLE)
            ->where($qb->expr()->in(
                'client_id',
                $qb->createNamedParameter($orphans, IQueryBuilder::PARAM_INT_ARRAY)
            ));
        $qb->executeStatement();
    }

    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        // Frische Installationen: Version0002 hat bereits alles korrekt angelegt.
        if (!$schema->hasTable(self::MAPPING_TABLE) || !$schema->hasTable(self::CLIENTS_TABLE)) {
            return null;
        }

        $table = $schema->getTable(self::MAPPING_TABLE);
        $changed = false;

        $hasClientForeignKey = false;
        foreach ($table->getForeignKeys() as $foreignKey) {
            $localColumns = array_map('strtolower', $foreignKey->getLocalColumns());
            if (in_array('client_id', $localColumns, true)) {
                $hasClientForeignKey = true;
                break;
            }
        }

        if (!$hasClientForeignKey) {
            $output->info('Ticky CRM: ergänze fehlenden Foreign Key auf ' . self::MAPPING_TABLE . '.');
            $table->addForeignKeyConstraint(
                $schema->getTable(self::CLIENTS_TABLE),
                ['client_id'],
                ['id'],
                ['onDelete' => 'CASCADE'],
                'ticky_crm_cc_client_fk'
            );
            $changed = true;
        }

        // Auf PostgreSQL wirkungslos: unsigned ist ein MySQL-Konzept, Doctrine
        // ignoriert das Flag dort und meldet die Spalten stets als signed.
        foreach (self::UNSIGNED_COLUMNS as $columnName) {
            if (!$table->hasColumn($columnName)) {
                continue;
            }

            $column = $table->getColumn($columnName);
            if ($column->getUnsigned()) {
                continue;
            }

            $column->setUnsigned(true);
            $changed = true;
        }

        if (!$changed) {
            return null;
        }

        return $schema;
    }

    public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
    }
}