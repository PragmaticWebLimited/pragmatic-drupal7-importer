<?php
declare(strict_types=1);

/**
 * Bootstraps the Drupal 7 Importer plugin.
 *
 * Most? of the methods in this class are not type-hinted because the upstream
 * files were not written as such, and I don't want us to accidentally
 * break things.
 */
namespace Pragmatic\Drupal7Importer;

use HMCI\CLI;
use HMCI\Iterator;
use HMCI\Utils;
use Pragmatic\Drupal7Importer\Integration;

/**
 * Drupal 7 users importer.
 */
class UsersImporter extends Iterator\DB\Base {

	/**
	 * Constructor.
	 *
	 * @param array $args  Importer options.
	 * @param string $type Is the iterator being used as a "validator" or an "importer"?
	 */
	public function __construct( $args = [], $type = 'importer' ) {

		parent::__construct( $args, $type );
		$this->args['debugger'] = 'error_log';
	}

	/**
	 * Get importer description.
	 *
	 * @return string
	 */
	public static function get_description() {
		return __( 'Drupal DB Importer description', 'pragmatic-drupal7-importer' );
	}

	/**
	 * Get the total number of Drupal 7 users to migrate.
	 *
	 * @return int
	 */
	public function get_count() {

		return Integration\get_drupal_users_count(
			$this->db()
		);
	}

	/**
	 * Get Drupal entities.
	 *
	 * @param mixed $offset How many pages of items do we need to offset?
	 * @param mixed $count  How many items do we fetch per query?
	 *
	 * @return array Drupal entities to migrate.
	 */
	public function get_items( $offset, $count ) {

		if ( $count === '-1' ) {
			$count = 100;
		}

		return Integration\get_drupal_users(
			$this->db(),
			(int) $count,
			(int) $offset
		);
	}

	/**
	 * Transform the to-be-migrated user.
	 *
	 * @param array $item The import item.
	 *
	 * @return array The import item.
	 */
	public function parse_item( $item ) {

		if ( ! $item ) {
			return false;
		}

		$item['user_pass'] = wp_generate_password( 12, false );

		return apply_filters(
			'pragmatic.users_importer.parse_item',
			$item
		);
	}

	/**
	 * Processes the import item.
	 *
	 * @param array $item The import item.
	 *
	 * @return bool False on failure, true on success.
	 */
	protected function process_item( $item ) {

		$imported_user = wp_insert_user( $item );

		if ( is_wp_error( $imported_user ) ) {
			$this->debug( sprintf( 'Imported user error: %s', print_r( $item, true ) ) );
			return false;
		}

		// Set user meta.
		foreach ( array_keys( $item ) as $meta_key ) {
			if ( strpos( $meta_key, 'drupal_' ) !== 0 ) {
				continue;
			}

			// Prefix all the meta keys with "_drupal_".
			add_user_meta( $imported_user, "_{$meta_key}", $item[ $meta_key ] );
		}

		// Manually tick the progress bar, as we're dealing with multiple entities per batch.
		CLI\HMCI::$progressbar->tick( 1 );
		Utils\clear_local_object_cache();

		return true;
	}
}
