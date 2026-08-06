<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Creates the `public_slugs` sidecar table backing
 * `dcardenasl\Ci4ApiCore\Localization\PublicSlugStore` (LOC-006).
 *
 * Schema matches the one `ci4-api-core` itself tests against
 * (`tests/Support/DatabaseTestCase.php`). Slug uniqueness relies on the
 * connection's `utf8mb4_general_ci` collation (case- and
 * accent-insensitive) — do not create this table under a case-sensitive
 * collation or `Hola`/`hola` will not collide the way production expects.
 */
class CreatePublicSlugsTable extends Migration
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
            'resource_type' => [
                'type'       => 'VARCHAR',
                'constraint' => 80,
                'null'       => false,
            ],
            'resource_id' => [
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
            'slug' => [
                'type'       => 'VARCHAR',
                'constraint' => 191,
                'null'       => false,
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
            ['resource_type', 'locale', 'slug'],
            'uq_public_slugs_locale_slug'
        );
        $this->forge->addUniqueKey(
            ['resource_type', 'resource_id', 'locale'],
            'uq_public_slugs_resource_locale'
        );
        $this->forge->createTable('public_slugs');
    }

    public function down(): void
    {
        $this->forge->dropTable('public_slugs', true);
    }
}
