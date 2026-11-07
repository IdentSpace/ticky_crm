<?php

namespace OCA\TickyCRM\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version0005Date20261106 extends SimpleMigrationStep {
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        $schema = $schemaClosure();
        $table = $schema->getTable('ticky_clients');
        if (!$table->hasColumn('follow_up_mode')) {
            $table->addColumn('follow_up_mode', 'string', ['length' => 16, 'default' => 'automatic', 'notnull' => true]);
            $table->addColumn('follow_up_manual', 'string', ['length' => 16, 'default' => 'green', 'notnull' => true]);
            $table->addColumn('last_contact_date', 'string', ['length' => 10, 'notnull' => false]);
        }
        return $schema;
    }
}
