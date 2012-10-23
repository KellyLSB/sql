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
		$this->table($table);
	}

	/**
	 * Set the table to query on
	 * @author Kelly Becker
	 * @since Oct 22nd, 2012
	 */
	public function table($table = false) {
		if(!$table) return $this->_table;

		// Set the table
		if(strpos($table, '`') === 0)
			$this->_table = $table;
		else $this->_table = "`$table`";
	}

	/**
	 * Replace the select field
	 * @author Kelly Becker
	 * @since Oct 22nd, 2012
	 */
	public function resetSelect($field = '*') {

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
	public function select($field = false) {
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
		if(strpos($field, '`') !== 0 && strpos($field, ' as ') === false) $field = "`$field`";

		// If not an array make it one and merge and return
		$this->_select = array_merge($this->_select, array($field));
		return $this;
	}

	/**
	 * Reset table joins
	 * @author Kelly Becker
	 * @since Oct 22nd, 2012
	 */
	public function resetJoins() {
		$this->_joins = array();
		return $this;
	}

	/**
	 * Add table joins
	 * @author Kelly Becker
	 * @since Oct 22nd, 2012
	 * @todo Convert join shortcuts into architect join shortcuts
	 */
	public function joins($join = false) {
		if(!$join) return $this;

		if(is_array($join)) {

			foreach($join as $j) {

				// Split the join string into three parts
				list($table, $dir, $field) = explode(' ', $j);

				// Tick off some variables
				if(strpos($table, '`') !== 0) $table = "`$table`";
				if(strpos($field, '`') !== 0) $field = "`$field`";

				// Add table to the field
				$field = $table.'.'.$field;

				// Always assume ID for base table
				$field1 = $this->_table.'.`id`';

				// Determine join direction
				if($dir === '<') $j = 'LEFT';
				if($dir === '>') $j = 'RIGHT';

				// Or assume center join
				$j .= 'JOIN '.$table.' ';
				$j .= 'ON '.$field1.' = '.$field;

				// Add join to the list
				$this->joins($j);
			}

			return $this;
		}

		// Append the join
		$this->_joins[] = $join;
		return $this;
	}

	/**
	 * Reset where conditions
	 * @author Kelly Becker
	 * @since Oct 22nd, 2012
	 */
	public function resetWhere() {
		$this->_conditions = array();
		return $this;
	}

	/**
	 * Add where conditions
	 * @author Kelly Becker
	 * @since Oct 22nd, 2012
	 */
	public function where() {
		$args = func_get_args();

		// Get the base query
		$query = array_shift($args);

		// With no query do nothing
		if(empty($query)) return $this;

		// Handle sprinting
		if(!empty($args)) {
			$query = str_replace('?', '\'%s\'', $query);
			$query = vsprintf($query, $args);
		}

		$this->_conditions[] = '('.$query.')';
		return $this;
	}

	/**
	 * Reset group by conditions
	 * @author Kelly Becker
	 * @since Oct 22nd, 2012
	 */
	public function resetGroup() {
		$this->_group = array();
		return $this;
	}

	/**
	 * Add group by conditions
	 * @author Kelly Becker
	 * @since Oct 22nd, 2012
	 */
	public function group($group = false) {
		if(!$group) return $this;

		if(is_array($group)) {

			// Add each to the group by
			foreach($group as $g)
				$this->group($g);

			return $this;
		}

		// If not ticked off then tick off
		if(strpos($group, '`') !== 0) $group = "`$group`";

		// Add to the group by
		$this->_group[] = $group;

		return $this;
	}

	/**
	 * Reset order by conditions
	 * @author Kelly Becker
	 * @since Oct 22nd, 2012
	 */
	public function resetOrder() {
		$this->_order = array();
		return $this;
	}

	/**
	 * Add order by conditions
	 * @author Kelly Becker
	 * @since Oct 22nd, 2012
	 */
	public function order($order = false, $dir = 'ASC') {
		if(!$order) return $this;

		if(is_array($order)) {

			// Add each to the order by
			foreach($order as $o => $d)
				$this->order($o, $d);

			return $this;
		}

		// If not ticked off then tick off
		if(strpos($order, '`') !== 0) $order = "`$order`";

		// Add to the order by
		$this->_order[] = $order.' '.$dir;

		return $this;
	}

	/**
	 * Reset limit conditions
	 * @author Kelly Becker
	 * @since Oct 22nd, 2012
	 */
	public function resetLimit() {
		$this->_limit = array();
		return $this;
	}

	/**
	 * Add limit condition
	 * @author Kelly Becker
	 * @since Oct 22nd, 2012
	 */
	public function limit($offset = false, $limit = false) {
		if($limit && !is_numeric($limit)) return $this;
		if(!is_numeric($offset)) return $this;

		// Add the limit
		$this->_limit = func_get_args();
		return $this;
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
		if(!empty($this->_conditions))
			$query[] = 'WHERE '.implode(' && ', $this->_conditions);

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