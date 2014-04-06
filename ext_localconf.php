<?php
/*                                                                        *
 * This file is brought to you by Georg Großberger, (c) 2014              *
 *                                                                        *
 * It is free software; you can redistribute it and/or modify it under    *
 * the terms of the MIT- / X11 - License                                  *
 *                                                                        */

$GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects']['TYPO3\CMS\Core\Database\DatabaseConnection']['className'] =
	'GeorgGrossberger\Pdo\Database\PdoConnection';

$GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects']['TYPO3\CMS\Core\Database\PreparedStatement']['className'] =
	'GeorgGrossberger\Pdo\Database\PreparedStatement';
