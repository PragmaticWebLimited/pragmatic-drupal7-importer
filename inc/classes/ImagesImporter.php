<?php
declare(strict_types=1);

/**
 * Bootstraps the Drupal 7 Images Importer plugin.
 *
 * Most? of the methods in this class are not type-hinted because the upstream
 * files were not written as such, and I don't want us to accidentally
 * break things.
 */
namespace Pragmatic\Drupal7Importer;

use HMCI\CLI;
use HMCI\Iterator;
use HMCI\Utils;
use HMCI\Inserter\WP\Attachment;
use Pragmatic\Drupal7Importer\Integration;

/**
 * Drupal 7 images importer.
 */
class ImagesImporter extends Iterator\DB\Base {

	/**
	 * Constructor.
	 *
	 * @param array $args  Importer options.
	 * @param string $type Is the iterator being used as a "validator" or an "importer"?
	 */
	public function __construct( $args = [], $type = 'importer' ) {

		parent::__construct( $args, $type );
		$this->args['debugger'] = 'error_log';

		// Set the path where the files are being picked up from.
		$this->import_filepath = apply_filters(
			'pragmatic.drupal7_images_importer.import_path',
			wp_get_upload_dir()['basedir'] . '/import/'
		);
	}

	/**
	 * Get importer description.
	 *
	 * @return string
	 */
	public static function get_description() {
		return __( 'Drupal Images Importer description', 'pragmatic-drupal7-importer' );
	}

	/**
	 * Get custom CLI arguments for the importer.
	 *
	 * @return array
	 */
	public static function get_custom_args() {

		return [];
	}

	/**
	 * Get the total number of Drupal images to migrate.
	 *
	 * @return int
	 */
	public function get_count() {
		return Integration\get_drupal_images_count(
			$this->db()
		);
	}

	/**
	 * Get Drupal images.
	 *
	 * @param mixed $offset How many pages of items do we need to offset?
	 * @param mixed $count  How many items do we fetch per query?
	 *
	 * @return array Drupal images to migrate.
	 */
	public function get_items( $offset, $count ) {

		if ( $count === '-1' ) {
			$count = 100;
		}

		return Integration\get_drupal_images(
			$this->db(),
			(int) $count,
			(int) $offset
		);
	}

	/**
	 * Transform the to-be-migrated image.
	 *
	 * @param array $item The import item.
	 *
	 * @return array The import item.
	 */
	public function parse_item( $item ) {

		if ( ! $item ) {
			return false;
		}

		// Transform file path.
		$item['drupal_file_path'] = \str_replace( 'public://', '', $item['drupal_file_path'] );

		// Transform date into a WordPress digestible format.
		$item['post_date']     = date( 'Y-m-d H:i:s', absint( $item['post_date'] ) );
		$item['post_modified'] = date( 'Y-m-d H:i:s', absint( $item['post_date'] ) );

		/** @todo apply_filters */
		return $item;
	}

	/**
	 * Processes the import item.
	 *
	 * @param array $item The import item.
	 *
	 * @return bool False on failure, true on success.
	 */
	protected function process_item( $item ) {

		// Upload item to the media library.
		$imported_attachment = Attachment::insert_from_path(
			$this->import_filepath . $item['drupal_file_path'],
			$item,
			false
		);

		if ( is_wp_error( $imported_attachment ) ) {
			$error_message = sprintf(
				'imported attachment error: %s. item: %s',
				$imported_attachment->get_error_message(),
				print_r( $item, true )
			);

			$this->debug( $error_message );
			return false;
		}

		// Set item meta.
		foreach ( array_keys( $item ) as $field_name ) {
			if ( strpos( $field_name, 'drupal_' ) !== 0 ) {
				continue;
			}

			// Stores: _drupal_file_id, _drupal_user_id, _drupal_file_path.
			add_post_meta( $imported_attachment, "_{$field_name}", $item[ $field_name ] );
			unset( $item[ $field_name ] );
		}

		// Manually tick the progress bar, as we're dealing with multiple entities per batch.
		CLI\HMCI::$progressbar->tick( 1 );
		Utils\clear_local_object_cache();

		return true;
	}
}
