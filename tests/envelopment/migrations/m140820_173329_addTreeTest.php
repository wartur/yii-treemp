<?php

class m140820_173329_addTreeTest extends CDbMigration
{
	public function up()
	{
		$this->createTable('treetest', array(
			'id' => 'pk',
			'parent_id'		=> "INT				NULL		COMMENT 'Родитель'",
			'path'			=> "VARCHAR(255)	NOT NULL	COMMENT 'Материализованный путь'",
			'name'			=> "VARCHAR(255)	NOT NULL	COMMENT 'Имя'",
		), 'ENGINE = INNODB CHARACTER SET utf8 COLLATE utf8_general_ci');
		
		$this->createIndex('path', 'treetest', 'path', true);
		$this->addForeignKey('treetest_ifbk_treetest', 'treetest', 'parent_id', 'treetest', 'id', 'CASCADE', 'CASCADE');
	}

	public function down()
	{
		$this->dropForeignKey('treetest_ifbk_treetest', 'treetest');
		$this->dropTable('treetest');
	}

	/*
	// Use safeUp/safeDown to do migration with transaction
	public function safeUp()
	{
	}

	public function safeDown()
	{
	}
	*/
}