<?php

/**
 * RealActiveRecordTreeBehavior class file.
 *
 * @author		Krivtsov Artur (wartur) <gwartur@gmail.com> | Made in Russia
 * @copyright	Krivtsov Artur © 2014
 * @link		http://wartur.ru/me	(ПОМЕНЯТЬ НА GITHUB)
 * @license		http://wartur.ru/license
 */
Yii::import('system.cli.commands.MigrateCommand');

/**
 * Command for drop all testdb tables and mitrate all migration from blank db
 */
class TestDbMigrateCommand extends MigrateCommand {

	public function init() {
		$this->defaultAction = 'initdb';
	}

	/**
	 * Parce dsn parametr
	 * @param string $dsn parametr for pdo
	 * @param string $valuename search param
	 * @return mixed result
	 */
	private static function parceDsn($dsn, $valuename) {
		$driverName = explode(':', $dsn); // example mysql:host=localhost;dbname=simpletest
		if (empty($driverName[1])) {   // host=localhost;dbname=simpletest
			return null;
		}

		$parametrs = explode(';', $dsn);
		foreach ($parametrs as $entry) {
			$paramPair = explode('=', $entry); // dbname=simpletest

			if ($paramPair[0] == $valuename) {
				return isset($paramPair[1]) ? $paramPair[1] : null;
			}
		}
	}

	public function actionInitdb() {

		// get table name from config
		$dbname = self::parceDsn($this->getDbConnection()->connectionString, 'dbname');

		// drop all tables use http://stackoverflow.com/questions/12403662/drop-all-tables
		$this->getDbConnection()->createCommand("
			SET FOREIGN_KEY_CHECKS = 0; 
			SET @tables = NULL;
			SELECT GROUP_CONCAT(table_schema, '.', table_name) INTO @tables
			  FROM information_schema.tables 
			  WHERE table_schema = '$dbname';

			SET @tables = CONCAT('DROP TABLE ', @tables);
			PREPARE stmt FROM @tables;
			EXECUTE stmt;
			DEALLOCATE PREPARE stmt;
			SET FOREIGN_KEY_CHECKS = 1;
		")->execute();

		// execute all migration
		$this->actionUp(array());
	}

}
