<?php

$log_path = Yii::getPathOfAlias('application.data.logs');

// This is the configuration for yiic console application.
// Any writable CConsoleApplication properties can be configured here.
return array(
	'basePath' => dirname(__FILE__) . DIRECTORY_SEPARATOR . '..',
	'name' => 'My Console Application',
	'preload' => array('log'), // preloading 'log' component
	'components' => array(// application components
		'db' => array(
			'connectionString' => 'mysql:host=localhost;dbname=simple',
			'username' => 'simple',
			'password' => 'xxx',
			'charset' => 'utf8',
			'autoConnect' => false,
			'emulatePrepare' => true,
			'schemaCachingDuration' => 3600,
			'enableParamLogging' => true,
			'enableProfiling' => true
		),
		'testdb'=>array(
			'class'=>'CDbConnection',
			'connectionString' => 'mysql:host=localhost;dbname=simpletest',
			'username' => 'simpletest',
			'password' => 'xxx',
			'charset' => 'utf8',
			'autoConnect' => true,
			'emulatePrepare' => true,
			'schemaCachingDuration' => 3600,
			'enableParamLogging' => true,
			'enableProfiling' => true
		),
		'log' => array(
			'class' => 'CLogRouter',
			'routes' => array(
				array(
					'class' => 'CFileLogRoute',
					'levels' => 'error',
					'filter' => 'CLogFilter',
					'logFile' => 'error.log',
					'LogPath' => $log_path
				),
				array(
					'class' => 'CFileLogRoute',
					'levels' => 'warning',
					'filter' => 'CLogFilter',
					'logFile' => 'warning.log',
					'LogPath' => $log_path
				),
				array(
					'class' => 'CFileLogRoute',
					'levels' => 'info',
					'filter' => 'CLogFilter',
					'logFile' => 'info.log',
					'LogPath' => $log_path
				),
				array(
					'class' => 'CProfileLogRoute',
					'enabled' => true,
					'levels' => 'profile',
					'showInFireBug' => true,
				)
			),
		),
	),
	'commandMap' => array(
		'testdbmigrate' => array(
			'class' => 'application.commands.TestDbMigrateCommand',
			'migrationPath' => 'application.migrations',
			'interactive' => false,
			'connectionID' => 'testdb',
		),
	),
);
