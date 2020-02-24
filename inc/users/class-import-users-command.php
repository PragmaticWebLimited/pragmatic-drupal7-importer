<?php
declare(strict_types=1);

namespace Pragmatic\Drupal7_Importer\Users;

use WP_CLI;
use WP_CLI_Command;
use wpdb;
use function apply_filters;
use function wp_insert_user;
use function is_wp_error;
use function update_user_meta;
use const DB_USER;
use const DB_PASSWORD;
use const DB_HOST;
use const ARRAY_A;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Registers the Drupal7 Import Users command.
 */
class Import_Users_Command extends WP_CLI_Command {
	/**
	 * Users found by the SQL query.
	 *
	 * @var int
	 */
	private $found_users = 0;

	/**
	 * Current user index. Used to display the import progress.
	 *
	 * @var int
	 */
	private $current_user_index = 0;

	/**
	 * Total count of users imported. Used to display the import progress.
	 *
	 * @var int
	 */
	private $imported_users = 0;

	/**
	 * Runs the query to retrieve the drupal users from the database.
	 *
	 * @param array $args Array of arguments passed to the cli command.
	 * @return array Array of results.
	 */
	private function get_drupal_users( array $args = [] ) : array {
		/**
		 * Filters the drupal database name
		 *
		 * @param string $db_name Name of the drupal database.
		 */
		$db_name = apply_filters( 'pragmatic.drupal7_importer.drupal_database_name', 'drupal' );

		// Get the wpdb connection instance.
		$db = new wpdb(
			$args['d7db_user'] ?? DB_USER,
			$args['d7db_pass'] ?? DB_PASSWORD,
			$args['d7db_name'] ?? $db_name,
			$args['d7db_host'] ?? DB_HOST,
		);

		$select_sql = <<<'SQL'
		 SELECT
		    U.`uid`     AS _drupal_user_id,
		    U.`name`    AS user_login,
		    U.`mail`    AS user_email,
		    FROM_UNIXTIME(U.`created`, '%Y-%m-%d %H:%i:%s') AS user_registered,
		    U.`status`  AS _drupal_user_status,
		    UR.`rid`    AS _drupal_role_id
		 FROM users AS U
		 LEFT JOIN users_roles AS UR ON U.`uid` = UR.`uid`
		SQL;

		$where_sql = "\n WHERE U.`uid` <> 0";
		$order_sql = "\n ORDER BY U.`uid`";

		if ( ! empty( $args['limit'] ) ) {
			$limit_sql = "\n " . $db->prepare( 'LIMIT 0, %d', absint( $args['limit'] ) );
		}

		// Construct the SQL query.
		$query = $select_sql . $where_sql . $order_sql . $limit_sql;

		/**
		 * Filters the SQL query used to retrieve the drupal users.
		 *
		 * @param string $query SQL query.
		 */
		$query = apply_filters( 'pragmatic.drupal7_importer.get_users_query', $query );

		$results = $db->get_results( $query, ARRAY_A );

		if ( is_null( $results ) ) {
			WP_CLI::error( 'Something went wrong when running the query.' );
		}

		$this->found_users = count( $results );

		return $results;
	}

	/**
	 * Maps the drupal user role id with a WordPress one. Falls back to subscriber if role id doesn't exist.
	 *
	 * @param int $user_role_id Drupal user role id.
	 * @return string WordPress role name.
	 */
	private function map_wp_user_role( int $user_role_id ) : string {
		$drupal_roles = [
			1 => 'subscriber', // 'anonymous user'
			2 => 'subscriber', // 'authenticated user'
			3 => 'administrator', // 'administrator'
			4 => 'editor', // 'content editor'
			5 => 'subscriber', // 'sales and marketing'
			6 => 'subscriber', // 'customer services'
			7 => 'subscriber', // 'product owner'
			8 => 'subscriber', // 'PR Editor'
			9 => 'subscriber', // 'subscriber'
		];

		/**
		 * Filters the drupal/wp mapping array.
		 *
		 * @param array $drupal_roles Array of key => value drupal/wp roles.
		 *                            Key is the drupal role id.
		 *                            Value is the WordPress role name.
		 * @param int   $user_role_id Drupal user role id.
		 */
		$drupal_roles = apply_filters( 'pragmatic.drupal7_importer.drupal_user_roles_mapping_array', $drupal_roles, $user_role_id );

		return $drupal_roles[ $user_role_id ] ?? 'subscriber';
	}

	/**
	 * Import users from a drupal install.
	 *
	 * ## OPTIONS
	 *
	 * [--d7db_user=<value>]
	 * : Drupal database user.
	 *
	 * [--d7db_pass=<value>]
	 * : Drupal database password.
	 *
	 * [--d7db_name=<value>]
	 * : Drupal database name.
	 *
	 * [--d7db_host=<value>]
	 * : Drupal database host.
	 *
	 * [--limit=<value>]
	 * : How many users should import.
	 *
	 */
	public function import( $args, $associative_args ) {
		// Tell WordPress we're doing an import to allow skipping some filters/actions.
		define( 'WP_IMPORTING', true );

		// Get the CLI arguments.
		$command_args = array_merge( $args, $associative_args );

		// Get the users from the drupal database users table.
		$users = $this->get_drupal_users( $command_args );

		// Bail early if there are no users to import.
		if ( count( $users ) < 1 ) {
			WP_CLI::warning( 'No users found.' );
			WP_CLI::halt( 404 );
		}

		WP_CLI::line( sprintf( 'Found %d users to import', $this->found_users ) );

		foreach ( $users as $user_data ) {
			// Increment user index. This is used for logging purposes only.
			$this->current_user_index++;

			/**
			 * Map the drupal role to the related WordPress one.
			 *
			 * @todo handle multiple user roles.
			 */
			$user_data['role'] = $this->map_wp_user_role( absint( $user_data['_drupal_role_id'] ) );

			// Generate a random password for the user. They might need to do the recovery process afterwards.
			$user_data['user_pass'] = wp_generate_password( 12, false );

			/**
			 * NOTE: Generate a random email address to avoid spamming real users during our tests.
			 *
			 * @todo remove for "actual" import.
			 */
			$user_data['user_email'] = sanitize_title( $user_data['user_login'] ) . '@example.local';

			// Try and create the user in WP.
			$created_user = wp_insert_user( $user_data );

			// Inform the user if the user has been created or not. Prints the WP_Error if any.
			if ( is_wp_error( $created_user ) ) {

				$message = 'User creation failed for "%s" (DRUPAL ID %s).';
				$message .= sprintf( ' %s %s.', WP_CLI::colorize( '%PError:%n' ), $created_user->get_error_message() );

			} else {

				$this->imported_users++;
				$message = 'User created for "%s" (DRUPAL ID %s).';

				// Store drupal data in the usermeta table.
				array_filter( $user_data, function ( $meta_value, $meta_key ) use ( $created_user ) {
					if ( strpos( $meta_key, '_drupal_', 0 ) !== false ) {
						update_user_meta( $created_user, $meta_key, $meta_value );
					}
				}, ARRAY_FILTER_USE_BOTH );

			}

			// Print progress message.
			WP_CLI::line(
				sprintf(
					'%d/%d %s',
					$this->current_user_index,
					$this->found_users,
					sprintf( $message, $user_data['user_login'], $user_data['_drupal_user_id'] )
				)
			);
		}

		WP_CLI::success( sprintf( 'Imported %d of %d users.', $this->imported_users, $this->found_users ) );
	}
}
