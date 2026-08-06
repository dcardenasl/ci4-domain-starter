<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Creates the `translations` sidecar table backing
 * `dcardenasl\Ci4ApiCore\Localization\LocalizedTranslationStore` (LOC-006).
 *
 * Schema matches the one `ci4-api-core` itself tests against
 * (`tests/Support/DatabaseTestCase.php`) so collation and index behavior are
 * identical to the package's own coverage. Relies on the connection's
 * default `utf8mb4_general_ci` collation — case- and accent-insensitive —
 * so slug/value comparisons behave the same in tests and production.
 */
class CreateTranslationsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'translatable_type' => [
                'type'       => 'VARCHAR',
                'constraint' => 80,
                'null'       => false,
            ],
            'translatable_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => false,
            ],
            'locale' => [
                'type'       => 'VARCHAR',
                'constraint' => 35,
                'null'       => false,
            ],
            'field' => [
                'type'       => 'VARCHAR',
                'constraint' => 80,
                'null'       => false,
            ],
            'value' => [
                'type' => 'MEDIUMTEXT',
                'null' => false,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(
            ['translatable_type', 'translatable_id', 'locale', 'field'],
            'uq_translations_resource_locale_field'
        );
        $this->forge->addKey(['translatable_type', 'translatable_id', 'locale']);
        $this->forge->createTable('translations');
    }

    public function down(): void
    {
        $this->forge->dropTable('translations', true);
    }
}
