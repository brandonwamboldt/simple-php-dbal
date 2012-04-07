SpryPHP Database Abstraction Layer
==================================

This is a simple database abstraction layer utilizing PDO, with helper functions for many common operations (Inserting, updating and retrieving data), and support for MySQL & Oracle.

### Connecting to a MySQL Database

	db( 'default', 'localhost', 'root', 'password', 'test_db' );
	
### Connecting to an Oracle Database

	db( 'default', 'localhost:1521', 'root', 'password', 'TESTSERVICE', 'oracle' );
	
### Extending the default class

	class CustomDatabaseDriver extends DatabaseDriver {}
	
	db( 'default', 'localhost', 'root', 'password', 'test_db', 'mysql', 'CustomDatabaseDriver' );
	
### Basic Queries

	$result = db()->query( "SELECT * FROM users" );
	
	// Get all rows as an associative array
	print_r( $result->as_assoc( TRUE ) );
	
	// Loop through the results
	foreach ( $result->as_object() as $user ) {
		echo $user->display_name;
	}
	
	// Get a specific row
	print_r( $result->as_assoc( FALSE, 5 ) ); // Gets row 5
	
### Get all results

	// Most operations support bind parameters specified as the second argument
	$rows = db()->get_results( "SELECT * FROM users WHERE status = :status", array( 'status' => 'active' ) );
	
### Get key => value pairs

	$rows = db()->get_pairs( "SELECT `ID`, `username` FROM `users`" );
	
	foreach ( $rows as $id => $username ) {
		echo "<option value='{$id}'>{$username}</option>\n";
	}
	
### Get a single column from all rows

	$usernames = db()->get_column( "SELECT `username` FROM `users`" );
	
### Get a single row

	$user = db()->get_row( "SELECT * FROM `users` WHERE `ID` = :user_id", array( 'user_id' => 5 ) );
	
### Get a single column from a single row

	$status = db()->get_var( "SELECT * FROM `users` WHERE `ID` = :user_id", array( 'user_id' => 5 ) );
	
### Insert a new row

	db()->insert( 'users', array( 'username' => 'jdoe', 'display_name => 'John Doe', 'status' => 'active' ) );
	
### Update a row

	db()->update( 'users', array( 'status' => 'inactive' ), array( 'ID' => 5 ) );
	
### Update all rows

	db()->update( 'users', array( 'status' => 'active' ) );
	
# Notes / Advanced Usage

I highly recommend browsing the source code to see all available commands and arguments

## Table Prefixing

You can set a prefix and easily append it to your table names using the table prefix keyword (Defaults to {{prefix}})

	db()->table_prefix( 'app_' );
	
	db()->query( "SELECT * FROM {{prefix}}users" );
	db->insert( '{{prefix}}users', array( 'username' => 'jdoe', 'display_name => 'John Doe', 'status' => 'active' ) );
	
## See all queries that have been run and their execution time

	db()->queries();
	
See all queries that have taken longer than 1 second to execute:

	db()->slow_queries();
	
See all queries that have taken longer than 10 seconds to execute:

	db()->long_queries();
	
## Multiple Database Connections

Using the first parameter of the db() function allows you to easily use multiple databases without worrying about global variables or ugly syntax

	db( 'default', 'localhost', 'root', 'password', 'test_db' );
	db( 'other', 'localhost', 'root', 'password', 'other_db' );
	
	db()->query( "SHOW TABLES()" );
	db('other')->query( "SHOW TABLES()" );