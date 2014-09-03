<?php

// This is the main Web application configuration. Any writable
// CWebApplication properties can be configured here.
return array(
	'basePath' => realpath(dirname(__FILE__) . '/..'),
	'name' => 'simple project',
	'sourceLanguage' => 'ru',
	'preload' => array('log'),

	// autoloading model and component classes
	'import'=>array(
		'application.models.*',
		'application.components.*',
		'ext.wartur.yii-tree-mp-behavior.*',
	),

	'modules'=>array(
		'gii'=>array(
			'class'=>'system.gii.GiiModule',
			'password'=>'gii',
			// If removed, Gii defaults to localhost only. Edit carefully to taste.
			'ipFilters'=>array('127.0.0.1'),
		),
	),

	// application components
	'components'=>array(
		'user' => array(
			'class' => 'CWebUser',
			'loginUrl' => array('site/login'),
			'allowAutoLogin' => true,
		),
		'urlManager' => array(
			'showScriptName' => false,
			'urlFormat' => 'path',
			'rules' => array(
				'gii' => 'gii',
				'gii/<controller:\w+>' => 'gii/<controller>',
				'gii/<controller:\w+>/<action:\w+>' => 'gii/<controller>/<action>',
			),
		),
		'db'=>array(
			'connectionString' => 'mysql:host=localhost;dbname=simple',
			'username' => 'simple',
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
);