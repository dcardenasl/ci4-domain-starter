<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateJobsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type' => 'BIGINT',
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'queue' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'default' => 'default',
            ],
            'payload' => [
                'type' => 'TEXT',
            ],
            'attempts' => [
                'type' => 'TINYINT',
                'unsigned' => true,
                'default' => 0,
            ],
            'reserved_at' => [
                'type' => 'INT',
                'unsigned' => true,
                'null' => true,
            ],
            'available_at' => [
                'type' => 'INT',
                'unsigned' => true,
            ],
            'created_at' => [
                'type' => 'INT',
                'unsigned' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey(['queue', 'reserved_at']);
        $this->forge->createTable('jobs');
    }

    public function down()
    {
        $this->forge->dropTable('jobs');
    }
}
