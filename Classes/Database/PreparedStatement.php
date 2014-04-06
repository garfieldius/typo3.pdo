<?php
namespace GeorgGrossberger\Pdo\Database;
/*                                                                        *
 * This file is brought to you by Georg Großberger, (c) 2014              *
 *                                                                        *
 * It is free software; you can redistribute it and/or modify it under    *
 * the terms of the MIT- / X11 - License                                  *
 *                                                                        */

use PDO;
use PDOStatement;
use TYPO3\CMS\Core\Database\PreparedStatement as Statement;

/**
 * TYPO3 extensions to the default prepared statement of PDO
 *
 * @author Georg Großberger <georg.grossberger@cyberhouse.at>
 * @license http://opensource.org/licenses/MIT MIT - License
 */
class PreparedStatement extends Statement {

	/**
	 * @var PDO
	 */
	protected $link;

	/**
	 * @var PDOStatement
	 */
	protected $stmt;

	/**
	 * @var string
	 */
	protected $query;

	public function setLink(PDO $link) {
		$this->link = $link;
	}

	public function __construct($query, $table, array $precompiledQueryParts = array()) {
		$this->query = $query;
	}

	/**
	 * @return PDOStatement
	 */
	protected function getStatement() {
		if (!$this->stmt) {
			$this->stmt = $this->link->prepare($this->query);
		}
		return $this->stmt;
	}


	public function bindValue($parameter, $value, $data_type = Statement::PARAM_AUTOTYPE) {
		$data_type = $this->getType($value, $data_type);
		switch ($data_type) {
			case self::PARAM_INT:
				if (!is_int($value)) {
					throw new \InvalidArgumentException('$value is not an integer as expected: ' . $value, 1281868686);
				}
				break;
			case self::PARAM_BOOL:
				if (!is_bool($value)) {
					throw new \InvalidArgumentException('$value is not a boolean as expected: ' . $value, 1281868687);
				}
				break;
			case self::PARAM_NULL:
				if (!is_null($value)) {
					throw new \InvalidArgumentException('$value is not NULL as expected: ' . $value, 1282489834);
				}
				break;
		}

		if (!is_int($parameter) && !preg_match('/^:[\\w]+$/', $parameter)) {
			throw new \InvalidArgumentException('Parameter names must start with ":" followed by an arbitrary number of alphanumerical characters.', 1395055513);
		}

		$this->getStatement()->bindValue($parameter, $value,$data_type);
		return $this;
	}

	protected function getType($value, $type) {
		if ($type === self::PARAM_AUTOTYPE) {
			if (is_int($value)) {
				return PDO::PARAM_INT;
			} elseif (is_bool($value)) {
				return PDO::PARAM_BOOL;
			} elseif (is_null($value)) {
				return PDO::PARAM_NULL;
			}
		} elseif ($type === self::PARAM_INT) {
			return PDO::PARAM_INT;
		} elseif ($type === self::PARAM_BOOL) {
			return PDO::PARAM_BOOL;
		} elseif ($type === self::PARAM_NULL) {
			return PDO::PARAM_NULL;
		}
		return PDO::PARAM_STR;
	}

	public function execute(array $input_parameters = array()) {
		if (empty($input_parameters) || !is_array($input_parameters)) {
			$input_parameters = NULL;
		}
		return $this->getStatement()->execute($input_parameters);
	}

	public function fetch($fetch_style = 0) {
		return $this->getStatement()->fetch($fetch_style);
	}

	public function seek($rowNumber) {
		while ($rowNumber--) {
			$success = $this->getStatement()->nextRowset();
			if (!$success) {
				return FALSE;
			}
		}
		return TRUE;
	}

	public function fetchAll($fetch_style = 0) {
		return $this->getStatement()->fetchAll($fetch_style);
	}

	public function free() {
		$this->getStatement()->closeCursor();
		$this->stmt = NULL;
	}

	public function rowCount() {
		return $this->getStatement()->rowCount();
	}

	public function errorCode() {
		$code = $this->getStatement()->errorCode();
		if (trim($code, '0') === '') {
			return 0;
		} else {
			return $code;
		}
	}

	public function errorInfo() {
		return $this->getStatement()->errorInfo();
	}

	public function setFetchMode($mode) {
		return $this->getStatement()->setFetchMode($mode);
	}

}
