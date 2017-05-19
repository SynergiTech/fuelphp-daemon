<?php

namespace Fuel\Migrations;

class Create_queue_workers
{
	public function up()
	{
		\DBUtil::create_table('queue_workers', array(
			'id' => array('constraint' => 11, 'type' => 'int', 'auto_increment' => true, 'unsigned' => true),
			'name' => array('constraint' => 6, 'type' => 'varchar'),
			'type' => array('constraint' => '"manager","child"', 'type' => 'enum'),
			'last_heartbeat' => array('constraint' => 11, 'type' => 'int'),
			'status' => array('type' => 'text'),
			'created_at' => array('constraint' => 11, 'type' => 'int', 'null' => true),
			'updated_at' => array('constraint' => 11, 'type' => 'int', 'null' => true),

		), array('id'));
	}

	public function down()
	{
		\DBUtil::drop_table('queue_workers');
	}
}