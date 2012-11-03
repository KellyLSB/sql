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
use DateTimeZone;
use DateTime;

class QueryModel implements ArrayAccess {

	// Store statically model caches
	private static $__models = array();
	private static $__memory = 0;

	// Store the connection and data
	protected $__connection;
	protected $__data = array();
	private $__changed = array();
	private $__new = false;

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

		// If we already have a cached table row go ahead and load it
		if($id && !is_array($id) && isset(self::$__models[$table][$id]))
			$this->__data = self::$__models[$table][$id];

		// If no cache was found but we have a numeric id retrieve the row
		elseif($id && is_numeric($id))
			$this->__data = self::$__models[$table][$id] = $this->__connection->find($table, $id);

		// If an array was passed cache and use it
		elseif($id && is_array($id))
			$this->__data = self::$__models[$table][$id['id']] = $id;

		// Create a new model
		else {
			$this->__data = $this->__connection->getColumns($table, false);
			$this->__new = true;
		}

		// Get and cache memory usage difference
		self::$__memory += memory_get_usage(true) - $init_mem;
	}

	public function save($array = false) {

		try {
			// If there is a passed array then add the data to the model
			if(!empty($array) && is_array($array)) foreach($array as $key => $val)
				$this->$key = $val;

			// If the data did not change then stop
			if(empty($this->__changed) && $array !== true) return false;

			// If this is a new model add created timestamp
			if($this->__new) $this->created_timestamp = date('Y-m-d H:i:s');
			if($array === true) $this->updated_timestamp = date('Y-m-d H:i:s');

			// What data to pass
			$data = array();
			foreach($this->__changed as $key => $tf)
				$data[$key] = $this->__data[$key];

			// Run update query
			if(!$this->__new)
				$this->__connection->update($this->__table, $data, $this->__data['id']);
			else {
				$this->__connection->insert($this->__table, $data);
				$this->_data = $data;
			}
		}
		catch(Exception $e) {
			throw $e;
			if(get_class($e) === 'Bundles\SQL\QueryException')
				throw $e;
			else return false;
		}
		
		return true;
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
		$this->__changed[$var] = true;
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