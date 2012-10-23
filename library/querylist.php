<?php

namespace Bundles\SQL;
use Exception;
use e;

// Use all PDO Objects
use PDO;
use PDOStatement;
use PDOException;

/**
 * Exceptions for use in the QueryList
 * @author Kelly Becker
 * @since Oct 22nd, 2012
 */
class QueryListException extends Exception {
}

class QueryList extends QueryResult {

	// Query Partials
	private $_pre = array();
	private $_select = array('*');
	private $_table = array();
	private $_joins = array();
	private $_conditions = array();
	private $_group = array();
	private $_order = array();
	private $_limit = array();
	private $_post = array();

	// Compiled Query
	private $_query = null;

	// Iterate Models
	private $_iterateModels = true;

	/**
	 * Construct Query List
	 * @author Kelly Becker
	 * @since Oct 22nd, 2012
	 */
	public function __construct(Connection $connection, $table) {
		$this->__connection = $connection;
		$this->setTable($table);
	}

	/**
	 * Replace the select field
	 * @author Kelly Becker
	 * @since Oct 22nd, 2012
	 */
	public function replaceSelect($field = '*') {

		// Empty out the select array
		$this->_select = array();

		// Forward over to add select
		return $this->addSelect($field);
	}

	/**
	 * Add items to the select array
	 * @author Kelly Becker
	 * @since Oct 22nd, 2012
	 */
	public function addSelect($field = false) {
		if(!$field) return $this;

		// If the passed field is an array
		if(is_array($field)) {

			// Make sure all selected fields are ticked off properly
			foreach($field as &$f) if(strpos($f, '`') !== 0 && $f !== '*') $f = "`$f`";

			// Merge and return
			$this->_select = array_merge($this->_select, $field);
			return $this;
		}

		// If no ticks are on the field
		elseif(strpos($field, '`') !== 0) $field = "`$field`";

		// If not an array make it one and merge and return
		$this->_select = array_merge($this->_select, array($field));
		return $this;
	}

	/**
	 * Set the table to query on
	 * @author Kelly Becker
	 * @since Oct 22nd, 2012
	 */
	public function setTable($table) {
		if(strpos($table, '`') === 0)
			$this->_table = $table;
		else $this->_table = "`$table`";
	}

	/**
	 * Build the SQL Query to run
	 * @author Kelly Becker
	 * @since Oct 22nd, 2012
	 */
	public function buildQuery() {

		// Begin an empty query
		$query = array();

		// Add the prefixes
		$query[] = implode(' ', $this->_pre);

		// Add the select fields
		$query[] = 'SELECT '.implode(', ', $this->_select);

		// Get the table
		$query[] = 'FROM '.$this->_table;

		// Add the joins
		$query[] = implode(' ', $this->_joins);

		// Add the conditions with and
		$query[] = implode(' && ', $this->_conditions);

		// Add group by
		if(!empty($this->_group))
			$query[] = 'GROUP BY '.implode(', ', $this->_group);

		// Add order by
		if(!empty($this->_order))
			$query[] = 'ORDER BY '.implode(', ', $this->_order);

		// Add limits
		if(!empty($this->_limit))
			$query[] = 'LIMIT '.implode(', ', $this->_limit);

		// Add the postfixes
		$query[] = implode(' ', $this->_post);

		// Join into a single query
		$this->_query = trim(implode(' ', $query));

		return $this;
	}

	/**
	 * Run the SQL Query
	 * @author Kelly Becker
	 * @since Oct 22nd, 2012
	 */
	public function runQuery($build = false) {

		// Build if set true
		if($build) $this->buildQuery();

		// Dont let null queries run
		if(is_null($this->_query))
			throw new QueryListException("Error: Trying to run a `null` query.");

		// Run a raw query
		$this->__connection->raw = true;
		$result = $this->__connection->query($this->_query);

		// Set the result to statement
		$this->__statement = $result;

		// Get and return all results
		$this->__results = parent::all();

		return $this;
	}

	/**
	 * Get all the results
	 * @author Kelly Becker
	 * @since Oct 22nd, 2012
	 */
	public function all($model = true, $build = false) {

		// Build and run query
		if(empty($this->__results) || $build)
			$this->runQuery(true);

		// Return results
		return $this->__results;
	}

	/**
	 * Iterate Models?
	 * @author Kelly Becker
	 * @since Oct 22nd, 2012
	 */
	public function iterateModels($tf = true) {
		$this->_iterateModels = $tf;
		return $this;
	}

	/**
	 * Rewind the iterator
	 * @author Kelly Becker
	 * @since Oct 22nd, 2012
	 */
	public function rewind() {
		$this->all($this->_iterateModels, true);
		$this->_cursor = 0;
	}

}