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
	private $slug;

	/**
	 * Parse Database Connection String
	 * @author Kelly Becker
	 * @since Oct 22nd, 2012
	 */
	private final function __parseDSN($dcs, &$username = null, &$password = null) {

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

				// User Authentication
				if(!empty($dcs['user'])) $dsn .= 'user='.$dcs['user'].';';
				if(!empty($dcs['pass'])) $dsn .= 'password='.$dcs['pass'].';';
			break;
		}

		// Return DSN
		return $dsn;
	}

	/**
	 * Construct Database Connection Instance
	 * @author Kelly Becker
	 * @since Oct 22nd, 2012
	 */
	public function __construct($slug) {

		// Set slug to the object
		$this->slug = $slug;

		// Get the database connection
		$url = e::$environment->requireVar(
			"sql.connection.$slug",
			'service://username[:password]@hostname[:port]/database'
		);

		// Parse the connection uri
		$dsn = $this->__parseDSN($url, $username, $password);

		try {
			// Create the PDO Instance
			self::$__connection[$slug] = new PDO($dsn, $username, $password);

			// Instantiate a new query log
			self::$__queryLog[$slug] = array();

			// Run a test query to make sure everything is set
			$this->__query('SHOW TABLES', true);
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
	private final function __query($query, $test = false) {

		// Start the timer
		$timerStart = microtime(true);

		try {
			// Prepare and run query
			$result = self::$__connection[$this->slug]->prepare($query);
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

		// Return QueryResult
		return QueryResult($this, $result);
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

		dump($query);

		// Enter Trace
		e\trace_enter("SQL Query on `$this->slug`", $query, $args, 7);

		// Run Main Query Function
		$result = $this->__query($query);

		// Exit Trace
		e\trace_exit();

		// Return result
		return $result;
	}

}