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
	protected $__connection;
	protected $__statement;

	// Pointer location and results
	protected $__cursor = 0;
	protected $__results = array();

	/**
	 * Construct Query Result
	 * @author Kelly Becker
	 * @since Oct 22nd, 2012
	 */
	public function __construct(Connection $connection, PDOStatement $statement) {
		$this->__connection = $connection;
		$this->__statement = $statement;
		$this->__results = $this->all();
	}

	/**
	 * Retrieve the insert id
	 * @author Kelly Becker
	 * @since Oct 22nd, 2012
	 */
	public final function insertId() {
		return $this->__connection->lastInsertId();
	}

	/**
	 * Fetch a row in the statment
	 * @author Kelly Becker
	 * @since Oct 22nd, 2012
	 */
	public function fetch($reset = false) {
		if($reset) $this->rewind();
		if(!$this->valid()) return false;
		$result = $this->current();
		$this->next();
		return $result;
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
		$this->__cursor = 0;
	}

	/**
	 * Get the current row in the statment
	 * @author Kelly Becker
	 * @since Oct 22nd, 2012
	 */
	public function current() {
		return $this->__results[$this->__cursor];
	}

	/**
	 * Get the key of the result
	 * @author Kelly Becker
	 * @since Oct 22nd, 2012
	 */
	public function key() {
		return $this->__cursor;
	}

	/**
	 * Increase the iterator
	 * @author Kelly Becker
	 * @since Oct 22nd, 2012
	 */
	public function next() {
		$this->__cursor++;
	}
	
	/**
	 * Is the result valid
	 * @author Kelly Becker
	 * @since Oct 22nd, 2012
	 */
	public function valid() {
		return !is_null($this->current());
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