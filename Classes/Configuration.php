<?php
namespace GeorgGrossberger\Pdo;
/*                                                                        *
 * This file is brought to you by Georg Großberger, (c) 2014              *
 *                                                                        *
 * It is free software; you can redistribute it and/or modify it under    *
 * the terms of the MIT- / X11 - License                                  *
 *                                                                        */

use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Simple access to configuration
 *
 * @author Georg Großberger <georg.grossberger@cyberhouse.at>
 * @license http://opensource.org/licenses/MIT MIT - License
 */
class Configuration implements SingletonInterface {

	private $configuration = array(
		'enableExceptions' => FALSE
	);

	/**
	 * @return Configuration
	 */
	public static function getInstance() {
		return GeneralUtility::makeInstance(get_called_class());
	}

	final public function __construct() {
		$configuration = (array) @unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['pdo']);
		$this->configuration = array_replace_recursive($this->configuration, $configuration);
	}

	private function __clone() {

	}

	/**
	 * @return boolean
	 */
	public function enableExceptions() {
		return !empty($this->configuration['enableExceptions']);
	}
}
