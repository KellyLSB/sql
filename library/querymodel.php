<?php

namespace Bundles\SQL;
use Exception;
use e;

// Use all PDO Objects
use PDO;
use PDOStatement;
use PDOException;

// Utility Classes
use ArrayAccess;

class QueryModel implements ArrayAccess {

	// Store statically model caches
	private static $__models = array();
	private static $__memory = 0;

	// Store the connection and data
	protected $__connection;
	protected $__data = array();

	// Store Table Name
	protected $__table;

	/**
	 * Construct Query Result
	 * @author Kelly Becker
	 * @since Oct 22nd, 2012
	 */
	public function __construct(Connection $connection, $table, $id = false) {

		// Get initial memory usage
		$init_mem = memory_get_usage(true);

		// Store the connection and table
		$this->__connection = $connection;
		$this->__table = $table;

		// Instantiate table cache if not already
		if(!isset(self::$__models[$table]))
			self::$__models[$table] = array();

		// Get cached data or get and cache data
		if($id && !is_array($id) && isset(self::$__models[$table][$id]))
			$this->__data = self::$__models[$table][$id];
		elseif($id && is_numeric($id))
			$this->__data = self::$__models[$table][$id] = $this->__connection->find($table, $id);
		elseif($id && is_array($id))
			$this->__data = self::$__models[$table][$id['id']] = $id;
		else $this->__data = $this->__connection->getColumns($table);

		// Get and cache memory usage difference
		self::$__memory += memory_get_usage(true) - $init_mem;
	}

	/**
	 * Access Methods
	 * @author Kelly Becker
	 * @since Oct 22nd, 2012
	 */
	public function __isset($var) {
		return isset($this->__data[$var]);
	}

	public function __get($var) {
		return $this->__data[$var];
	}

	public function __set($var, $val) {
		return $this->__data[$var] = $val;
	}

	public function offsetExists($var) {
		return $this->__isset($var);
	}

	public function offsetGet($var) {
		return $this->__get($var);
	}

	public function offsetSet($var, $val) {
		return $this->__set($var, $val);
	}

	public function offsetUnset($var) {
		$this->__set($var, null);
	}

}