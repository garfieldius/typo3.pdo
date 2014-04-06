<?php
namespace GeorgGrossberger\Pdo\Database;
/*                                                                        *
 * This file is brought to you by Georg Großberger, (c) 2014              *
 *                                                                        *
 * It is free software; you can redistribute it and/or modify it under    *
 * the terms of the MIT- / X11 - License                                  *
 *                                                                        */

use GeorgGrossberger\Pdo\Configuration;
use PDO;
use PDOStatement;
use TYPO3\CMS\Core\Database\DatabaseConnection;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Using PDO to connect to the database
 *
 * @author Georg Großberger <georg.grossberger@cyberhouse.at>
 * @license http://opensource.org/licenses/MIT MIT - License
 */
class PdoConnection extends DatabaseConnection {

	/**
	 * @var PDO
	 */
	protected $link;

	/**
	 * @return PDO
	 */
	public function getLink() {
		if (!$this->isConnected()) {
			$this->connectDB();
		}
		return $this->link;
	}



	/**
	 * @param null|string $host
	 * @param null|string $username
	 * @param null|string $password
	 * @return null|PDO
	 * @throws \RuntimeException
	 */
	public function sql_pconnect($host = NULL, $username = NULL, $password = NULL) {
		if ($this->link) {
			return $this->link;
		}

		if (!class_exists('PDO')) {
			throw new \RuntimeException(
				'Database Error: PHP PDO extension not loaded. Remove this extension to use mysqli instead!',
				1385653525
			);
		}

		if ($host || $username || $password) {
			$this->handleDeprecatedConnectArguments($host, $username, $password);
		}

		$attributes = array();

		if (Configuration::getInstance()->enableExceptions()) {
			$attributes[ PDO::ATTR_ERRMODE ] = PDO::ERRMODE_EXCEPTION;
		} else {
			$attributes[ PDO::ATTR_ERRMODE ] = PDO::ERRMODE_WARNING;
		}

		if ($this->persistentDatabaseConnection) {
			$attributes[ PDO::ATTR_PERSISTENT ] = TRUE;
		}

		$dsn = 'mysql:charset=UTF8;dbname=' . $this->databaseName;

		if ($this->databaseSocket) {
			$dsn .= ';socket=' . $this->databaseSocket;
		} else {
			$dsn .= ';host=' . $this->databaseHost;

			if ($this->databasePort) {
				$dsn .= ';port=' . $this->databasePort;
			}
		}

		$this->link = new PDO($dsn, $this->databaseUsername, $this->databaseUserPassword, $attributes);

		if ($this->link instanceof PDO && !$this->sql_errno()) {
			$this->isConnected = TRUE;
			foreach ($this->initializeCommandsAfterConnect as $command) {
				$stmt = $this->link->query($command);
				if ($stmt->errorCode()) {
					GeneralUtility::sysLog(
						'Could not initialize DB connection with query "' . $command . '": ' . implode(',', $stmt->errorInfo()),
						'Core',
						GeneralUtility::SYSLOG_SEVERITY_ERROR
					);
				}
			}
			$this->setSqlMode();
		} else {
			if ($this->link instanceof PDO) {
				$error_msg = $this->sql_error();
			} else {
				$error_msg = $php_errormsg;
			}
			$this->link = NULL;
			GeneralUtility::sysLog(
				'Could not connect to MySQL server ' . $host . ' with user ' . $username . ': ' . $error_msg,
				'Core',
				GeneralUtility::SYSLOG_SEVERITY_FATAL
			);
		}
		return $this->link;
	}

	public function isConnected() {
		return $this->link instanceof PDO;
	}

	public function exec_SELECTgetRows($select_fields, $from_table, $where_clause, $groupBy = '', $orderBy = '', $limit = '', $uidIndexField = '') {
		if (!$this->isConnected()) {
			$this->connectDB();
		}

		if ($this->debugOutput) {
			$this->debug('exec_SELECTquery');
		}

		$stmt = $this->link->query($this->SELECTquery($select_fields, $from_table, $where_clause, $groupBy, $orderBy, $limit));
		if ($stmt instanceof PDOStatement) {
			$stmt->setFetchMode(PDO::FETCH_ASSOC);
			if (!$uidIndexField) {
				$data = $stmt->fetchAll();
			} else {
				$data = array();
				foreach ($stmt as $row) {
					$data[$row[$uidIndexField]] = $row;
				}
			}
			$stmt->closeCursor();
		} else {
			$data = NULL;
		}
		return $data;
	}

	public function exec_SELECTgetSingleRow($select_fields, $from_table, $where_clause, $groupBy = '', $orderBy = '', $numIndex = FALSE) {
		if (!$this->isConnected()) {
			$this->connectDB();
		}

		if ($this->debugOutput) {
			$this->debug('exec_SELECTquery');
		}

		$query = $this->SELECTquery($select_fields, $from_table, $where_clause, $groupBy, $orderBy, 1);
		$stmt = $this->link->query($query);
		$row = $stmt->fetch(PDO::FETCH_ASSOC);
		$stmt->closeCursor();
		return $row;
	}

	public function quoteName($field) {
		return '`' . str_replace('`', '``', $field) .'`';
	}

	protected function prepareInsert($table, $fields) {
		$fields = array_map(array($this, 'quoteName'), $fields);
		$placeholders = array_pad(array(), count($fields), '?');
		$query = 'INSERT INTO ' . $this->quoteName($table) . ' (' . implode(',', $fields) . ') VALUES (' . implode(', ', $placeholders) . ')';
		return $this->link->prepare($query);
	}

	public function exec_INSERTquery($table, $fields_values, $no_quote_fields = FALSE) {
		if (!$this->isConnected()) {
			$this->connectDB();
		}

		$stmt = $this->prepareInsert($table, array_keys($fields_values));
		$success = $stmt->execute(array_values($fields_values));
		$stmt->closeCursor();

		if ($this->debugOutput) {
			$this->debug('exec_INSERTquery');
		}

		foreach ($this->postProcessHookObjects as $hookObject) {
			/** @var $hookObject \TYPO3\CMS\Core\Database\PostProcessQueryHookInterface */
			$hookObject->exec_INSERTquery_postProcessAction($table, $fields_values, $no_quote_fields, $this);
		}
		return $success;
	}

	public function exec_INSERTmultipleRows($table, array $fields, array $rows, $no_quote_fields = FALSE) {
		if (!$this->isConnected()) {
			$this->connectDB();
		}

		$stmt = $this->prepareInsert($table, $fields);
		$success = TRUE;

		foreach ($rows as $row) {
			$success = $stmt->execute($row) && $success;
		}

		$stmt->closeCursor();

		if ($this->debugOutput) {
			$this->debug('exec_INSERTmultipleRows');
		}

		foreach ($this->postProcessHookObjects as $hookObject) {
			/** @var $hookObject \TYPO3\CMS\Core\Database\PostProcessQueryHookInterface */
			$hookObject->exec_INSERTmultipleRows_postProcessAction($table, $fields, $rows, $no_quote_fields, $this);
		}

		return $success;
	}

	public function sql_insert_id() {
		return $this->link->lastInsertId();
	}

	/**
	 * @param PDOStatement $res
	 * @param int                        $pointer
	 * @return bool|string
	 */
	public function sql_field_type($res, $pointer) {
		return FALSE;
	}

	public function admin_get_charsets() {
		return $this->getAllForQuery('SHOW CHARACTER SET', 'Charset');
	}

	public function admin_get_dbs() {
		$rows = $this->getAllForQuery('SELECT SCHEMA_NAME FROM information_schema.SCHEMATA');
		$rows = array_map(function($row) {
			return $row['SCHEMA_NAME'];
		}, $rows);
		return $rows;
	}

	public function admin_get_fields($tableName) {
		return $this->getAllForQuery('SHOW COLUMNS FROM ' . $this->quoteName($tableName), 'Field');
	}

	public function admin_get_keys($tableName) {
		return $this->getAllForQuery('SHOW KEYS FROM ' . $this->quoteName($tableName));
	}

	public function admin_get_tables() {
		return $this->getAllForQuery('SHOW TABLE STATUS FROM ' . $this->quoteName($this->databaseName), 'Name');
	}

	public function admin_query($query) {
		return $this->query($query);
	}

	protected function getAllForQuery($query, $key = NULL) {
		if (!$this->isConnected()) {
			$this->connectDB();
		}
		$stmt = $this->link->query($query);
		$result = array();
		if ($stmt) {
			while ($row = $stmt->fetch()) {
				if ($key) {
					$result[$row[$key]] = $row;
				} else {
					$result[] = $row;
				}
			}
		}
		return $result;
	}

	/**
	 * Prepares a prepared query.
	 *
	 * @param string $query The query to execute
	 * @param array $queryComponents The components of the query to execute
	 * @return \PDOStatement|null
	 * @internal This method may only be called by \TYPO3\CMS\Core\Database\PreparedStatement
	 */
	public function prepare_PREPAREDquery($query, array $queryComponents) {
		if (!$this->isConnected()) {
			$this->connectDB();
		}
		return GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Database\\PreparedStatement', $query, NULL, array());
	}

	public function fullQuoteStr($str, $table, $allowNull = FALSE) {
		if (!$this->isConnected()) {
			$this->connectDB();
		}

		if ($allowNull && $str === NULL) {
			return 'NULL';
		}

		return $this->link->quote($str);
	}

	public function quoteStr($str, $table) {
		return trim($this->fullQuoteStr($str, $table), '\'');
	}

	protected function disconnectIfConnected() {
		if ($this->isConnected) {
			$this->link = NULL;
		}
	}

	public function sql_num_rows($res) {
		return $res->rowCount();
	}

	public function sql_fetch_assoc($res) {
		return $res->fetch(PDO::FETCH_ASSOC);
	}

	public function sql_fetch_row($res) {
		return $res->fetch(PDO::FETCH_BOTH);
	}

	public function sql_select_db($TYPO3_db = NULL) {
		return $this->isConnected();
	}

	/**
	 * @return null|string
	 */
	public function sql_error() {
		if (!$this->isConnected()) {
			$this->connectDB();
		}

		$data = $this->link->errorInfo();

		if (is_array($data) && $this->sql_errno()) {
			$data = implode(', ', $data);
		} else {
			$data = '';
		}
		return $data;
	}

	/**
	 * @return string
	 */
	public function sql_errno() {
		if (!$this->isConnected()) {
			$this->connectDB();
		}

		$code = $this->link->errorCode();

		if (trim($code, '0') === '') {
			return '';
		} else {
			return $code;
		}
	}

	public function prepare_SELECTquery($select_fields, $from_table, $where_clause, $groupBy = '', $orderBy = '', $limit = '', array $input_parameters = array()) {
		if (!$this->isConnected()) {
			$this->connectDB();
		}
		/** @var PreparedStatement $stmt */
		$stmt = parent::prepare_SELECTquery($select_fields, $from_table, $where_clause, $groupBy, $orderBy, $limit, $input_parameters);
		$stmt->setLink($this->link);
		return $stmt;
	}

	public function sql_free_result($res) {
		return $res->closeCursor();
	}

	public function debug_check_recordset($res) {
		if ($res instanceof PDOStatement) {
			return TRUE;
		} else {
			$msg = 'Invalid database result detected';
			$trace = debug_backtrace();
			array_shift($trace);
			array_shift($trace);
			$cnt = count($trace);

			for ($i = 0; $i < $cnt; $i++) {
				// Complete objects are too large for the log
				if (isset($trace['object'])) {
					unset($trace['object']);
				}
			}

			$msg .= ': function ' . $trace[0]['function'] . ' called from file ' . substr($trace[1]['file'], (strlen(PATH_site) + 2)) . ' in line ' . $trace[1]['line'];

			GeneralUtility::sysLog(
				$msg . '. Use a devLog extension to get more details.',
				'Core/t3lib_db',
				GeneralUtility::SYSLOG_SEVERITY_ERROR
			);

			if (TYPO3_DLOG) {
				$debugLogData = array(
					'SQL Error' => $this->sql_error(),
					'Backtrace' => $trace
				);
				if ($this->debug_lastBuiltQuery) {
					$debugLogData = array('SQL Query' => $this->debug_lastBuiltQuery) + $debugLogData;
				}
				GeneralUtility::devLog($msg . '.', 'Core/t3lib_db', 3, $debugLogData);
			}
			return FALSE;
		}
	}


}
