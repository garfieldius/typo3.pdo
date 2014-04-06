<?php

$EM_CONF[$_EXTKEY] = array(
	'title' => 'PDO Database connection',
	'description' => 'Overloads the default connection object to use a PDO instance instead of mysqli',
	'category' => 'misc',
	'shy' => 0,
	'dependencies' => '',
	'conflicts' => '',
	'priority' => '',
	'loadOrder' => '',
	'module' => '',
	'state' => 'beta',
	'internal' => 0,
	'uploadfolder' => 0,
	'createDirs' => '',
	'modify_tables' => '',
	'clearCacheOnLoad' => 1,
	'lockType' => '',
	'author' => 'Georg Großberger',
	'author_email' => 'contact@grossberger-ge.org',
	'author_company' => '',
	'CGLcompliance' => '',
	'CGLcompliance_note' => '',
	'version' => '0.0.0',
	'constraints' => array(
		'depends' => array(
			'typo3' => '6.2.0-6.2.99',
		),
		'conflicts' => array(),
		'suggests' => array()
	)
);

?>
