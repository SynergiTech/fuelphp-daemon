<?php

namespace Fuel\Migrations;

class Drop_daemon_workers
{
    public function up()
    {
        \DBUtil::drop_table('daemon_workers');
    }

    public function down()
    {
        \DBUtil::create_table('daemon_workers', array(
            'id' => array('constraint' => 11, 'type' => 'int', 'auto_increment' => true, 'unsigned' => true),
            'name' => array('constraint' => 255, 'type' => 'varchar'),
            'type' => array('constraint' => '"worker","supervisor"', 'type' => 'enum'),
            'last_heartbeat' => array('constraint' => 11, 'type' => 'int', 'null' => true, 'default' => null),
            'status' => array('type' => 'text'),
            'created_at' => array('constraint' => 11, 'type' => 'int', 'null' => true),
            'updated_at' => array('constraint' => 11, 'type' => 'int', 'null' => true),

        ), array('id'));
    }
}
