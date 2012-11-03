<?php

namespace Bundles\SQL;
use Exception;
use e;

// Use all PDO Objects
use PDO;
use PDOStatement;
use PDOException;

/**
 * Connection Exception Class
 * @author Kelly Becker
 * @since Oct 22nd, 2012
 */
class ConnectionException extends Exception {
}

/**
 * Query Exception Class
 * @author Kelly Becker
 * @since Oct 22nd, 2012
 */
class QueryException extends Exception {

	protected $query = 'Unknown Query';

	/**
	 * Query Exceptions
	 * @author Kelly Becker
	 * @since Oct 22nd, 2012
	 */
	public function __construct($message = null, $query = null, $code = 0, Exception $previous = null) {
		$message .= ": \"$query\"";
		parent::__construct($message, $code, $previous);
		if(!is_null($query)) $this->query = $query;
	}

	/**
	 * Get the query at fault
	 * @author Kelly Becker
	 * @since Oct 22nd, 2012
	 */
	public function getQuery() {
		return $this->query;
	}
}

class Connection {

	private static $__connection = array();
	private static $__queryLog = array();
	private $__scheme;
	public $raw = false;
	private $slug;

	/**
	 * Parse Database Connection String
	 * @author Kelly Becker
	 * @since Oct 22nd, 2012
	 */
	private final function __parseDSN($dcs, &$username = null, &$password = null, &$scheme = null) {

		// Parse URL
		$dcs = parse_url($dcs);

		switch($dcs['scheme']) {

			// MySQL Connection
			case 'mysql':
			case 'mysqli':
				$dsn = 'mysql:';

				// Hostname for connection
				$dsn .= 'host='.$dcs['host'].';';

				// Database port
				if(!empty($dcs['port'])) $dsn .= 'port='.$dcs['port'].';';

				// Database name
				$dsn .= 'dbname='.substr($dcs['path'], 1).';';

				// DB Scheme
				$scheme = 'mysql';

				// User Authentication
				if(!empty($dcs['user'])) $username = $dcs['user'];
				if(!empty($dcs['pass'])) $password = $dcs['pass'];
			break;

			// Socketed MySQL Connection
			case 'mysql-socket':
			case 'mysqli-socket':
				$dsn = 'mysql:';

				// Database connection socket
				$dsn .= 'unix_socket='.$dcs['path'].';';

				// Database port
				if(!empty($dcs['port'])) $dsn .= 'port='.$dcs['port'].';';

				// Database name
				$dsn .= 'dbname='.$dcs['host'].';';

				// DB Scheme
				$scheme = 'mysql';

				// User Authentication
				if(!empty($dcs['user'])) $username = $dcs['user'];
				if(!empty($dcs['pass'])) $password = $dcs['pass'];
			break;

			// PostgreSQL Connection
			case 'pgsql':
				$dsn = 'pgsql:';

				// Hostname for connection
				$dsn .= 'host='.$dcs['host'].';';

				// Database port
				if(!empty($dcs['port'])) $dsn .= 'port='.$dcs['port'].';';

				// Database name
				$dsn .= 'dbname='.substr($dcs['path'], 1).';';

				// DB Scheme
				$scheme = 'pgsql';

				// User Authentication
				if(!empty($dcs['user'])) $dsn .= 'user='.$dcs['user'].';';
				if(!empty($dcs['pass'])) $dsn .= 'password='.$dcs['pass'].';';
			break;

			// SQLite 3 Connection
			case 'sqlite3':
			case 'sqlite':
				$dsn = 'sqlite:';

				// Store in memory
				if(!empty($dcs['host']) && $dcs['host'] === 'memory')
					$dsn .= ':memory:';

				// Use disk storage
				if(!empty($dcs['path']) && empty($dcs['host']))
					$dsn .= $dcs['path'];

				// DB Scheme
				$scheme = 'sqlite';
			break;

			// SQLite 2 Connection
			case 'sqlite2':
				$dsn = 'sqlite2:';

				// Store in memory
				if(!empty($dcs['host']) && $dcs['host'] === 'memory')
					$dsn .= ':memory:';

				// Use disk storage
				if(!empty($dcs['path']) && empty($dcs['host']))
					$dsn .= $dcs['path'];

				// DB Scheme
				$scheme = 'sqlite2';
			break;
		}

		// Return DSN
		return $dsn;
	}

	/**
	 * Construct Database Connection Instance
	 * @author Kelly Becker
	 * @since Nov 3rd, 2012
	 */
	public function __construct($slug) {

		// Set slug to the object
		$this->slug = $slug;

		// If we already have this connection instantiated then go ahead and return
		if(!empty(self::$__connection[$slug]) && self::$__connection[$slug] instanceof Connection)
			return;

		// Get the database connection
		$url = e::$environment->requireVar(
			"sql.connection.$slug",
			'service://username[:password]@hostname[:port]/database'
		);

		// Parse the connection uri
		$dsn = $this->__parseDSN($url, $username, $password, $scheme);

		try {
			// Create the PDO Instance
			self::$__connection[$slug] = new PDO($dsn, $username, $password);

			// Instantiate a new query log
			self::$__queryLog[$slug] = array();
			
			switch($scheme) {
				case 'mysql':
					// Run a test query to make sure everything is set
					$this->__query("SHOW TABLES", false, true);

					// Set the server timezone offset
					$this->__query("SET `time_zone` = '+00:00';", false, true);	
				break;
				case 'pgsql':
					// Run a test query to make sure everything is set
					$this->__query("SELECT * FROM `pg_catalog`.`pg_tables`", false, true);

					// Set the server timezone offset
					$this->__query("SET TIME ZONE UTC;", false, true);	
				break;
			}

			// Add the scheme to the connection class
			$this->__scheme = $scheme;
		}

		// If there was a PDOException
		catch(PDOException $e) {
			throw new ConnectionException("Could not connect to database `$slug`", 0, $e);
		}
	}

	/**
	 * Main Run SQL Query
	 * @author Kelly Becker
	 * @since Oct 22nd, 2012
	 */
	private final function __query($query, $raw = false, $test = false) {

		// Start the timer
		$timerStart = microtime(true);

		try {
			// Prepare query
			$result = $this->getConnection()->prepare($query);

			// Run query
			$result->execute();

			// If there is any kind of error info throw an exception
			$errorInfo = $result->errorInfo();
			if($errorInfo[2] !== NULL) throw new PDOException($errorInfo[2]);
		}

		// Handle query errors
		catch(PDOException $e) {

			// Throw QueryException
			throw new QueryException(
				"SQL Query failed on `$this->slug` database connection.",
				$query, 0, $e
			);
		}

		// Stop the timer
		$timerStop = microtime(true);

		// Time taken to run query
		$time = ($timerStop - $timerStart) * 1000;

		// Add query to the query log
		if(!$test) self::$__queryLog[$this->slug][] = array(
			'dateTime' => date("Y-m-d H:i:s"),
			'timeTaken' => $time,
			'sqlQuery' => $query
		);

		// If test return success
		else return true;

		// If we should return PDOStatement Directly
		if($raw) {
			$this->raw = false;
			return $result;
		}

		// Return QueryResult
		return new QueryResult($this, $result);
	}

	/**
	 * Get SQL Connection
	 * @author Kelly Becker
	 * @since Nov 3rd, 2012
	 */
	public final function getConnection() {
		return self::$__connection[$this->slug];
	}

	/**
	 * Public Run SQL Query
	 * @author Kelly Becker
	 * @since Oct 22nd, 2012
	 */
	public function query() {

		// Get function arguments
		$args = func_get_args();

		// Get Query from arguments
		$query = array_shift($args);

		// Handle sprinting
		if(!empty($args)) {
			$query = str_replace('?', '\'%s\'', $query);
			$query = vsprintf($query, $args);
		}

		// Enter Trace
		e\trace_enter("SQL Query on `$this->slug`", $query, $args, 7);

		// Run Main Query Function
		$result = $this->__query($query, $this->raw);

		// Exit Trace
		e\trace_exit();

		// Return result
		return $result;
	}

	/**
	 * Basic select
	 * @author Kelly Becker
	 * @since Oct 22nd, 2012
	 */
	public function select() {

		// Handle arguments
		$args = func_get_args();
		$table = array_shift($args);
		$where = array_shift($args);

		// Preare select query
		array_unshift($args, "SELECT * FROM `$table` $where");

		// Run query
		return call_user_func_array(array($this, 'query'), $args);
	}

	/**
	 * Get a row by table, id
	 * @author Kelly Becker
	 * @since Oct 22nd, 2012
	 */
	public function find($table, $id) {
		return $this->select($table, 'WHERE `id` = ?', $id)->fetch();
	}

	/**
	 * Update a table row
	 * @author Kelly Becker
	 * @since Nov 3rd, 2012
	 */
	public function update($table, $data, $where) {
		$query = array("UPDATE `$table`");

		// Set Args
		$sets = Utility::querify($this->getConnection(), $table, $data, ', ');
		if(!empty($sets)) $query[] = 'SET '.$sets;

		// Where Args
		if(is_array($where))
			$wheres = Utility::querify($this->getConnection(), $table, $where);
			if(!empty($wheres)) $query[] = 'WHERE '.$wheres;
		elseif(Utility::is_numeric($where))
			$query[] = "WHERE `$table`.`id` = $where";

		return $this->query(implode(' ',$query));
	}

	/**
	 * Insert a table row
	 * @author Kelly Becker
	 * @since Nov 3rd, 2012
	 */
	public function insert($table, $data) {
		$query = array("REPLACE INTO `$table`");

		// Set Args
		$sets = Utility::querify($this->getConnection(), $table, $data, ', ');
		if(!empty($sets)) $query[] = 'SET '.$sets;

		return $this->query(implode(' ',$query));
	}

	/**
	 * Upsert
	 * @author Kelly Becker
	 * @since Nov 3rd, 2012
	 */
	public function upsert($table, $data) {
		return $this->query($table, $data);
	}

	/**
	 * Get a list object
	 * @author Kelly Becker
	 * @since Oct 22nd, 2012
	 */
	public function newList($table) {
		return new QueryList($this, $table);
	}

	/**
	 * Get a model object
	 * @author Kelly Becker
	 * @since Oct 22nd, 2012
	 */
	public function newModel($table, $id = false) {
		return new QueryModel($this, $table, $id);
	}

	/**
	 * Get the table columns
	 * @author Kelly Becker
	 * @since Nov 3rd, 2012
	 */
	public function getColumns($table, $more = true) {

		// See if we have a cache of the columns
		if(e::$cache->timestamp('sql_table_columns', $table) > (time() - 86400)) {

			// Load from cache
			$data = e::$cache->get('sql_table_columns', $table);

			// Return just fields
			if(!$more) foreach($data as &$val) $val = null;
			return $data;
		}

		// Describe the table
		$columns = $this->query("DESCRIBE `$table`;");

		// If there is more then 0 columns
		if($columns->count() > 0) {
			$fields = array();

			// Loop through the columns
			foreach($columns as $column)
				$fields[$column['Field']] = $column;

			// Cache the columns and return
			e::$cache->store('sql_table_columns', $table, $fields);

			// Return just fields
			if(!$more) foreach($fields as &$val) $val = null;

			return $fields;
		}

		// Return false if none found
		else return false;
	}

}