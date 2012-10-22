<?php

namespace Bundles\SQL;
use Exception;
use e;

// Use all PDO Objects
use PDO;
use PDOStatement;
use PDOException;

// Utility Classes
use Iterator;
use Countable;

class QueryResult implements Iterator, Countable {

	// Store the connection and statement
	private $__connection;
	private $__statement;

	// Pointer location
	private $_cursor = 0;

	/**
	 * Construct Query Result
	 * @author Kelly Becker
	 * @since Oct 22nd, 2012
	 */
	public function __construct(Connection $connection, PDOStatement $statement) {
		$this->__connection = $connection;
		$this->__statement = $statement;
	}

	/**
	 * Retrieve the insert id
	 * @author Kelly Becker
	 * @since Oct 22nd, 2012
	 */
	public function insertId() {
		return $this->__connection->lastInsertId();
	}

	/**
	 * Get all results
	 * @author Kelly Becker
	 * @since Oct 22nd, 2012
	 */
	public function all() {
		return $this->__statement->fetchAll(PDO::FETCH_ASSOC);
	}

	/**
	 * Rewind the iterator
	 * @author Kelly Becker
	 * @since Oct 22nd, 2012
	 */
	public function rewind() {
		$this->_cursor = 0;
	}

	/**
	 * Return the current result
	 * @author Kelly Becker
	 * @since Oct 22nd, 2012
	 */
	public function current() {
		return $this->__statement->fetch(
			PDO::FETCH_ASSOC,
			PDO::FETCH_ORI_ABS,
			$this->_cursor
		);
	}

	/**
	 * Get the key of the result
	 * @author Kelly Becker
	 * @since Oct 22nd, 2012
	 */
	public function key() {
		return $this->_cursor;
	}

	/**
	 * Increase the iterator
	 * @author Kelly Becker
	 * @since Oct 22nd, 2012
	 */
	public function next() {
		$this->_cursor++;
	}
	
	/**
	 * Is the result valid
	 * @author Kelly Becker
	 * @since Oct 22nd, 2012
	 */
	public function valid() {
		return $this->current() !== false;
	}

	/**
	 * How many results are in this query
	 * @author Kelly Becker
	 * @since Oct 22nd, 2012
	 */
	public function count() {
		return $this->__statement->rowCount();
	}

}