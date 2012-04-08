<?php
/**
 * SpryPHP Database Abstraction Layer
 * 
 * This is the database abstraction layer for the SpryPHP framework, and 
 * provides a uniform method of performing common database actions regardless
 * of the database driver, such as inserting and updating data, and retrieving
 * information about tables
 *
 * @package SpryPHP
 * @subpackage Database
 * @version 1.3.0
 * @author Brandon Wamboldt
 */

// Define some constants
define( 'RESULT_AS_LIST', 'as_assoc' );
define( 'RESULT_AS_ARRAY', 'as_array' );
define( 'RESULT_AS_OBJECT', 'as_object' );

/**
 * Shorthand function used for accessing a database connection and executing
 * a function on it using chaining syntax
 * 
 * <code>
 * db( 'default', 'localhost', 'root', '', 'test' );
 * $result = db()->query( "SHOW TABLES()" );
 * 
 * foreach ( db()->result( $result ) as $row ) {
 *     echo $row->table;
 * }
 * </code>
 * 
 * @param string $identifier optional The name of the connection to use for the following database functions
 * @param string $db_host optional The hostname of the database server to connect to, defaults to localhost
 * @param string $db_user optional The username to use when connecting to the database server
 * @param string $db_pass optional The password for our database user
 * @param string $db_name optional The name of the database to select when a connection is established
 * @param string $type optional The type of database connection to establish, defaults to mysql
 */
function db( $identifier = 'default', $db_host = 'localhost', $db_user = '', $db_pass = '', $db_name = '', $type = 'mysql', $driver_class = NULL )
{
	// Contains an array of database connections that can be utilized
	static $connections, $database_driver;
	
	// The database driver class
	if ( empty( $database_driver ) ) {
		$database_driver = 'DatabaseDriver';
	}
	
	// Was a new class given?
	if ( ! is_null( $driver_class ) ) {
		$database_driver = $driver_class;
	}
	
	// If this is the first time the db() function is being called initialize 
	// the connections variable
	if ( ! is_array( $connections ) ) {
		$connections = array();
	}
	
	// Has the requested connection been established or is this the connection
	// call?
	if ( ! isset( $connections[$identifier] ) ) {
		$connections[$identifier] = new $database_driver( $type, $db_host, $db_user, $db_pass, $db_name );
	}
	
	// Return an instance of our database connection
	return $connections[$identifier];
}

/**
 * Represents a connection between PHP and a MySQL database, and contains
 * functions for executing SQL queries against this connection
 * 
 * @package SpryPHP
 * @subpackage Database Abstraction
 */
class DatabaseDriver
{
	/**
	 * An object which represents the connection to a MySQL Server.
	 * 
	 * @access protected
	 * @var object
	 */
	protected $connection = NULL;
	
	/**
	 * The database driver currently being used
	 * 
	 * @access protected
	 * @var object
	 */
	protected $database_driver = '';
	
	/**
	 * Whether or not to enable the table prefix keyword 
	 * 
	 * @access protected
	 * @var bool
	 */
	protected $enable_prefix_keyword = FALSE;
	
	/**
	 * The last error message that was generated
	 * 
	 * @access protected
	 * @var string
	 */
	protected $last_error = NULL;
	
	/**
	 * The last query that was run (The query string, not the result)
	 * 
	 * @access protected
	 * @var string
	 */
	protected $last_query = NULL;
	
	/**
	 * The result of the last run SQL query
	 * 
	 * @access protected
	 * @var object
	 */
	protected $last_result = NULL;
	
	/**
	 * The last prepared statement that was run
	 * 
	 * @access protected
	 * @var string
	 */
	protected $last_statement = NULL;
	
	/**
	 * An array of all queries that have been run so far
	 * 
	 * @access protected
	 * @var array
	 */
	protected $query_log = array();
	
	/**
	 * Whether or not to show error messages
	 * 
	 * @access protected
	 * @var string
	 */
	protected $show_errors = FALSE;
	
	/**
	 * The table prefix used in the current database
	 * 
	 * @access protected
	 * @var bool
	 */
	protected $table_prefix = 'prefix_';
	
	/**
	 * The table prefix keyword that when used in a query will be translated in
	 * to the specified table prefix (Defaults to {{prefix}})
	 * 
	 * @access protected
	 * @var bool
	 */
	protected $table_prefix_keyword = '{{prefix}}';
	
	/**
	 * Initializes the class by connecting to a new MySQL database via the 
	 * MySQLi functions
	 * 
	 * @throws Exception An exception is thrown if a connection cannot be established to the database
	 * 
	 * @param string $db_host The hostname of the database server to connect to
	 * @param string $db_user The username to use when connecting to the database server
	 * @param string $db_pass The password to use when connecting to the database server
	 * @param string $db_name The name of the database to select when a connection is established
	 * 
	 * @access public
	 */
	public function __construct( $driver = 'mysql', $db_host = 'localhost', $db_user = '', $db_pass = '', $db_name = '' ) 
	{
		// Try to connect to the database, and if the connection fails, throw 
		// an exception
		try {
			if ( $driver == 'mysql' ) {
				
				// Establish a new MySQL connection
				$this->connection = new PDO( $driver . ':host=' . $db_host . ';dbname=' . $db_name, $db_user, $db_pass );
				$this->database_driver = 'mysql';
			} else if ( $driver == 'oci' || $driver == 'oracle' ) {
				
				// Establish a new Oracle connection
				$address_port      = 1521;
				$address_protocol = 'TCP';
				
				$address = explode( ':', $db_host );
				
				$address_host = $address[0];
				
				if ( isset( $address[1] ) ) {
					$address_port = $address[1];
				}
				
				if ( isset( $address[2] ) ) {
					$address_protocol = $address[2];
				}
				
				$tns = "\n\t(DESCRIPTION =\n\t\t(ADDRESS_LIST =\n\t\t\t(ADDRESS = (PROTOCOL = {$address_protocol})(HOST = {$address_host})(PORT = {$address_port}))\n\t\t)\n\t\t(CONNECT_DATA =\n\t\t\t(SERVICE_NAME = {$db_name})\n\t\t)\n\t)\n";
				$this->connection = new PDO( 'oci:dbname=' . $tns, $db_user, $db_pass );
				$this->database_driver = 'oci';
			}
		} catch ( PDOException $e ) {
			
			// The connection failed, so throw an exception
			throw new Exception( 'Connect Error (' . $e->getCode() . ') ' . $e->getMessage(), $e->getCode() );
		}
	}
	
	/**
	 * Either gets or sets the table prefix to use for this database
	 * 
	 * @param string $table_prefix optional If specified will update the current table prefix
	 * @param bool $enable_prefix_keyword optional Whether or not to automatically enable prefix keywords if a prefix is specified
	 * @return string
	 * 
	 * @access public
	 */
	public function table_prefix( $table_prefix = NULL, $enable_prefix_keyword = TRUE )
	{
		// If no arguments are passed, just return the table prefix
		if ( is_null( $table_prefix ) ) {
			return $this->table_prefix;
		}
		
		// If a table prefix is given, update the table prefix variable
		$this->table_prefix = $table_prefix;
		$this->enable_prefix_keyword = $enable_prefix_keyword;
		
		return $this;
	}
	
	/**
	 * Error messages generated by a query will be displayed on the page
	 * 
	 * @access public
	 */
	public function show_errors()
	{
		$this->show_errors = TRUE;
		
		return $this;
	}
	
	/**
	 * Error messages generated by a query will NOT be displayed on the page
	 * 
	 * @access public
	 */
	public function hide_errors()
	{
		$this->show_errors = FALSE;
		
		return $this;
	}
	
	/**
	 * Returns the result of the last query that was run
	 * 
	 * @return NULL if no queries have been run yet, or a MySQL_Result object
	 * 
	 * @access public
	 */
	public function last_result()
	{
		return $this->last_result;
	}
	
	/**
	 * Returns the last query that was run (The query string, not the result)
	 * 
	 * @return NULL if no queries have been run yet, or String for the last query
	 * 
	 * @access public
	 */
	public function last_query()
	{
		return $this->last_query;
	}
	
	/**
	 * Returns an array of all the queries that have been run so far
	 * 
	 * @return array
	 * 
	 * @access public
	 */
	public function queries()
	{
		return $this->query_log;
	}
	
	/**
	 * Returns an array of all the queries that have taken 1 or more seconds to run
	 * 
	 * @return array
	 * 
	 * @access public
	 */
	public function slow_queries( $query_time = 1.0 )
	{
		$slow_queries = array();
		
		foreach ( $this->query_log as $query ) {
			if ( floatval( $query['execution_time'] ) > $query_time ) {
				$slow_queries[] = $query;
			}
		}
		
		return $slow_queries;
	}
	
	/**
	 * Returns an array of all the queries that have taken 10 or more seconds to run
	 * 
	 * @return array
	 * 
	 * @access public
	 */
	public function long_queries()
	{
		return $this->slow_queries( 10.0 );
	}
	
	/**
	 * Escapes special characters in a string for use in an SQL statmeent, 
	 * taking into account the current charset of the connection
	 * 
	 * @param string $string The string to escape
	 * @return string
	 * 
	 * @access public
	 */
	public function escape_str( $string ) 
	{
		return $this->connection->quote( $string );
	}
	
	/**
	 * Escapes special characters in a string for use in an SQL statmeent, 
	 * taking into account the current charset of the connection
	 * 
	 * @param string $string The string to escape
	 * @return string
	 * 
	 * @access public
	 */
	public function quote_str( $string ) 
	{
		return $this->connection->quote( $string );
	}
	
	/**
	 * Executes a SQL query against the currently active connection. Optionally
	 * builds a query before executing by replacing parameters and escaping 
	 * them properly
	 * 
	 * @param string $sql_query The query to execute
	 * @param array $bind_params Parameters that will replace an occurence of ? in the $sql_query
	 * 
	 * @access public
	 */
	public function query( $sql_query, $bind_params = array() ) 
	{
		// If bind parameters were passed to this function, add a : to the front of them
		if ( ! empty( $bind_params ) ) {
			foreach ( $bind_params as $bind_name => $bind_value ) {
				unset( $bind_params[$bind_name] );
				$bind_params[':' . ltrim( $bind_name, ':' )] = $bind_value;
			}
		}

		// Should we use table prefix keywords?
		if ( $this->enable_prefix_keyword ) {
			
			// Replace all occurrences of the table prefix keyword with the table prefix
			$sql_query = str_replace( $this->table_prefix_keyword, $this->table_prefix, $sql_query );
		}
		
		$query_start_time = microtime( TRUE );
		
		if ( $sql_query == $this->last_query) {

			// Use the previous prepared statement to save processing time
			$stmt = $this->last_statement;
		} else {
			
			// Create a new prepared statement
			$this->last_statement = $stmt = $this->connection->prepare( $sql_query, array( PDO::ATTR_CURSOR => PDO::CURSOR_SCROLL ) );
		}
		
		// Execute the query
		$success = $stmt->execute( $bind_params );
		
		// Store the current query as the last run query
		$this->last_query = $sql_query;
		$this->query_log[] = array( 'execution_time' => number_format( microtime( TRUE ) - $query_start_time, 5 ) , 'query' => $sql_query );
		
		// Did the current query fail?
		if ( $success === FALSE ) {
			$this->last_error = $stmt->errorInfo();
			
			if ( $this->show_errors ) {
				$error  = '<p style="background: #eee;border: dashed 1px #f00;padding: 4px;">';
				$error .= $this->last_error[2];
				$error .= '</p>';
				
				echo $error;
			}
			
			return $this->last_result = FALSE;
		}

		// Our query was successful so let's create a new QueryResult object, 
		// store it as the last result and return it
		return $this->last_result = new QueryResult( $stmt );
	}
	
	/**
	 * Inserts an array of key value pairs into the database, with the keys 
	 * being columns and the values being the column values. Data is 
	 * automatically escaped.
	 * 
	 * @param string $table_name The name of the table to insert the data in
	 * @param array $values The column => value data to insert into the table
	 * @return int The number of rows inserted
	 * 
	 * @access public
	 */
	public function insert( $table_name, $values )
	{
		// Get all of the bind parameters keys
		$keys = array();
		
		foreach ( $values as $key => $value ) {
			$keys[] = ':' . $key;
		}
		
		// Compose our query
		$sql_query = "INSERT INTO {$table_name} (`" . implode( '`, `', array_keys( $values ) ) . '`) VALUES (' . implode( ', ', $keys ) . ')';
		
		$result = $this->query( $sql_query, $values );
		
		return $result->row_count();
	}
	
	/**
	 * Returns the ID of the last inserted row
	 * 
	 * @return int The ID of the last inserted row
	 * 
	 * @access public
	 */
	public function insert_id()
	{
		return $this->connection->lastInsertId();
	}
	
	/**
	 * Updates the specified database using the column/value pairs in the 
	 * $values array and column/value pairs in the $where array. 
	 * 
	 * @return int The number of affected rows
	 * 
	 * @access public
	 */
	public function update( $table_name, $values, $where = array() )
	{
		// Compose our query
		$sql_query = "UPDATE {$table_name} SET";
		
		foreach ( $values as $column => $value ) {
			$sql_query .= " `{$column}` = :{$column},";
		}
		
		// Remove trailing comma
		$sql_query = rtrim( $sql_query, ',' );
		
		// Where clause
		if ( ! empty( $where ) && is_array( $where ) ) {
			
			$sql_query .= ' WHERE';
			
			foreach ( $where as $column => $value ) {
				$sql_query .= " `{$column}` = :where_{$column} AND";
				$values['where_' . $column] = $value;
			}
		}
		
		// Remove trailing AND
		$sql_query = rtrim( $sql_query, 'AND' );
		
		$result = $this->query( $sql_query, $values );
		
		return $result->row_count();
	}
	
	/**
	 * Retrieves results as an array of key => value pairs (Using the specified
	 * columns), very useful for making select boxes and what not
	 * 
	 * @param string $query A query to execute that will return a result set
	 * @param array $bind_params optional Parameters to bind to the query
	 * @param integer $key_column optional The column from which to get the key values (Defaults to 0)
	 * @param integer $value_column optional The column from which to get the values (Defaults to 1)
	 * @return array
	 * 
	 * @access public
	 */
	public function get_pairs( $query, $bind_params = array(), $key_column = 0, $value_column = 1 )
	{
		$result = $this->query( $query, $bind_params );

		if ( $result ) {
			$rows = $result->as_array( TRUE );
			$pairs = array();
			
			foreach ( $rows as $row ) {
				$pairs[$row[$key_column]] = $row[$value_column];
			}
			
			return $pairs;
		} else {
			return array();
		}
	}
	
	/**
	 * Retrieves all rows from the result set of the given query
	 * 
	 * @param string $query A query to execute that will return a result set
	 * @param array $bind_params optional Parameters to bind to the query
	 * @param constant $output optional The type of the result to return (Object/Array/Assoc)
	 * @return array
	 * 
	 * @access public
	 */
	public function get_results( $query, $bind_params = array(), $output_type = RESULT_AS_OBJECT )
	{
		$result = $this->query( $query, $bind_params );

		if ( $result ) {
			return $result->{$output_type}( TRUE );
		} else {
			return array();
		}
	}
	
	/**
	 * Retrieves a single row from the result set of the given query
	 * 
	 * @param string $query A query to execute that will return a result set
	 * @param array $bind_params optional Parameters to bind to the query
	 * @param integer $row_offset optional The row to get from the result set, defaults to 0
	 * @param constant $output optional The type of the result to return (Object/Array/Assoc)
	 * @return array|object
	 * 
	 * @access public
	 */
	public function get_row( $query, $bind_params = array(), $row_offset = 0, $output_type = RESULT_AS_OBJECT )
	{
		if ( is_numeric( $bind_params ) ) {
			$row_offset = $bind_params;
			$bind_params = array();
		}
		
		$result = $this->query( $query, $bind_params );

		if ( $result ) {
			return $result->{$output_type}( FALSE, $row_offset );
		} else {
			if ( $output_type == RESULT_AS_OBJECT ) {
				return new stdClass();
			} else {
				return array();
			}
		}
	}
	
	/**
	 * Retrieves a single column from a single row from the result set of the
	 * given query
	 * 
	 * @param string $query A query to execute that will return a result set
	 * @param array $bind_params optional Parameters to bind to the query
	 * @param integer $row_offset optional The row to get from the result set, defaults to 0
	 * @param integer $column_offset optional The column to get from the row, defaults to 0
	 * @return mixed
	 * 
	 * @access public
	 */
	public function get_var( $query, $bind_params = array(), $row_offset = 0, $column_offset = 0 )
	{
		if ( is_numeric( $bind_params ) ) {
			$column_offset = $row_offset;
			$row_offset    = $bind_params;
			$bind_params   = array();
		}
		
		$result = $this->query( $query, $bind_params );

		if ( $result ) {
			$row = $result->as_array( FALSE, $row_offset );
			return $row[$column_offset];
		} else {
			return FALSE;
		}
	}
	
	/**
	 * Retrieves a single column from a all rows in the result set of the
	 * given query
	 * 
	 * @param string $query A query to execute that will return a result set
	 * @param array $bind_params optional Parameters to bind to the query
	 * @param integer $column_offset optional The column to get from the row, defaults to 0
	 * @return array
	 * 
	 * @access public
	 */
	public function get_column( $query, $bind_params = array(), $column_offset = 0 )
	{
		if ( is_numeric( $bind_params ) ) {
			$column_offset = $bind_params;
			$bind_params   = array();
		}
		
		$result = $this->query( $query, $bind_params );

		if ( $result ) {
			$rows = $result->as_array( TRUE );
			$columns = array();
			
			foreach ( $rows as $row ) {
				$columns[] = $row[$column_offset];
			}
			
			return $columns;
		} else {
			return array();
		}
	}
	
	/**
	 * Deallocates the contents of a table, quickly removing all data from the 
	 * table
	 * 
	 * @param string $table The name of the table to truncate
	 * @return boolean
	 * 
	 * @access public
	 */
	public function truncate( $table )
	{
		return ( $this->query( "TRUNCATE {$table}" ) === FALSE );
	}
	
	/**
	 * Destroys an existing table, index, or view
	 * 
	 * @param string $object_type The type of the object to drop (Table/View/Index)
	 * @param string $object_name The name of the object to drop
	 * @return boolean
	 * 
	 * @access public
	 */
	public function drop( $object_type = 'TABLE', $object_name )
	{
		return ( $this->query( "DROP {$object_type} {$object_name}" ) === FALSE );
	}
	
	/**
	 * Destroys an existing table
	 * 
	 * @param string $object_name The name of the table to drop
	 * @return boolean
	 * 
	 * @access public
	 */
	public function drop_table( $object_name )
	{
		return $this->drop( 'TABLE', $object_name );
	}
	
	/**
	 * Destroys an existing view
	 * 
	 * @param string $object_name The name of the table to drop
	 * @return boolean
	 * 
	 * @access public
	 */
	public function drop_view( $object_name )
	{
		return $this->drop( 'VIEW', $object_name );
	}
}

/**
 * Represents the result set obtained from a query against the database
 * 
 * @package SpryPHP
 * @subpackage Database Abstraction
 */
class QueryResult
{
	/**
	 * Contains the mysqli_result object
	 * 
	 * @access protected
	 * @var PDOStatement
	 */
	protected $result = NULL;
	
	/**
	 * Contains the position of the cursor for the result set
	 * 
	 * @access integer
	 * @var unknown_type
	 */
	protected $cursor = 0;
	
	/**
	 * Store the results locally since PDO/MySQL has poor cursor support
	 * 
	 * @access protected
	 * @var array
	 */
	protected $rows = array();
	
	/**
	 * Initializes the MySQL_Result object by assigning a mysqli_result object
	 * to an interal variable
	 * 
	 * @param mysqli_result $result The mysqli_result object from the query that was executed
	 * 
	 * @access public
	 */
	public function __construct( PDOStatement $result )
	{
		$this->result = $result;
		$this->rows = $this->result->fetchAll( PDO::FETCH_OBJ );
	}
	
	/**
	 * Returns the current row of the result set, alternatively returns the 
	 * specified row of the result set 
	 * 
	 * @param int $offset optional If specified returns the row at the specified offset
	 * @param constant $output_type optional The type of the result to return (Object/Array/Assoc)
	 * @return mixed
	 * 
	 * @access public
	 */
	public function row( $offset = NULL, $output_type = RESULT_AS_OBJECT ) 
	{
		return $this->{$output_type}( FALSE, $offset );
	}
	
	/**
	 * Returns all rows in the result set as the specified type
	 * 
	 * @param constant $output_type optional The type of the result to return (Object/Array/Assoc)
	 * @return mixed
	 * 
	 * @access public
	 */
	public function all( $output_type = RESULT_AS_OBJECT ) 
	{
		return $this->{$output_type}( TRUE );
	}
	
	/**
	 * Returns the current row of the result set as an object, alternatively
	 * returns the specified row of the result set as an object
	 * 
	 * @param bool $all_rows optional Whether or not to return all rows or just the current row
	 * @param int $offset optional If specified returns the row at the specified offset
	 * @return object
	 * 
	 * @access public
	 */
	public function as_object( $all_rows = FALSE, $offset = NULL )
	{
		// Should we return one row or an array of rows?
		if ( $all_rows ) {
			
			return $this->rows;
		} else {

			// Was an offset passed?
			if ( is_null( $offset ) ) {
				if ( ! isset( $this->rows[$this->cursor] ) ) {
					return FALSE;
				}
				
				return $this->rows[$this->cursor++];
			} else {
				if ( ! isset( $this->rows[$offset] ) ) {
					return FALSE;
				}
				
				return $this->rows[$offset];
			}
		}
	}
	
	/**
	 * Returns the current row of the result set as a numeric array, 
	 * alternatively returns the specified row of the result set a numeric
	 * array
	 *
	 * @param bool $all_rows optional Whether or not to return all rows or just the current row
	 * @param int $offset optional If specified returns the row at the specified offset
	 * @return array
	 *
	 * @access public
	 */
	public function as_array( $all_rows = FALSE, $offset = 0 )
	{
		// Should we return one row or an array of rows?
		if ( $all_rows ) {
			$rows = array();
			
			foreach ( $this->rows as $row ) {
				$rows[] = array_values( get_object_vars( $row ) );
			}

			return $rows;
		} else {
		
			// Was an offset passed?
			if ( is_null( $offset ) ) {
				if ( ! isset( $this->rows[$this->cursor] ) ) {
					return FALSE;
				}
				
				return array_values( get_object_vars( $this->rows[$this->cursor++] ) );
			} else {
				if ( ! isset( $this->rows[$offset] ) ) {
					return FALSE;
				}
				
				return array_values( get_object_vars( $this->rows[$offset] ) );
			}
		}
	}
	
	/**
	 * Returns the current row of the result set as an associative array, 
	 * alternatively returns the specified row of the result set as an 
	 * associative array
	 *
	 * @param bool $all_rows optional Whether or not to return all rows or just the current row
	 * @param int $offset optional If specified returns the row at the specified offset
	 * @return array
	 *
	 * @access public
	 */
	public function as_assoc( $all_rows = FALSE, $offset = 0 )
	{
		// Should we return one row or an array of rows?
		if ( $all_rows ) {
			$rows = array();
			
			foreach ( $this->rows as $row ) {
				$rows[] = get_object_vars( $row );
			}

			return $rows;
		} else {
		
			// Was an offset passed?
			if ( is_null( $offset ) ) {
				if ( ! isset( $this->rows[$this->cursor] ) ) {
					return FALSE;
				}
				
				return get_object_vars( $this->rows[$this->cursor++] );
			} else {
				if ( ! isset( $this->rows[$offset] ) ) {
					return FALSE;
				}
				
				return get_object_vars( $this->rows[$offset] );
			}
		}
	}
	
	/**
	 * Returns the number of rows returned by the query that generated this 
	 * result set
	 * 
	 * @return int
	 * 
	 * @access public
	 */
	public function row_count() 
	{
		return $this->result->rowCount();
	}
}