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
 * Drupal 7 content importer.
 */
class PostsImporter extends Iterator\DB\Base {

	/**
	 * Constructor.
	 *
	 * @param array $args  Importer options.
	 * @param string $type Is the iterator being used as a "validator" or an "importer"?
	 */
	public function __construct( $args = [], $type = 'importer' ) {

		parent::__construct( $args, $type );
		$this->args['debugger'] = 'error_log';

		// Suspend bunches of stuff in WP core.
		\wp_defer_term_counting( true );
		\wp_defer_comment_counting( true );
		\wp_suspend_cache_invalidation( true );
	}

	/**
	 * Generic callback called when an import has been completed.
	 *
	 * The method name is misleading - it doesn't refer to an iteration of a group of posts within
	 * a larger set. For the Beano importers, this is called once the import run has been completed.
	 */
	public function iteration_complete() {

		parent::iteration_complete();

		// Re-enable stuff in core.
		\wp_suspend_cache_invalidation( false );
		\wp_cache_flush();

		foreach ( \get_taxonomies() as $tax ) {
			\delete_option( "{$tax}_children" );
			\_get_term_hierarchy( $tax );
		}

		\wp_defer_term_counting( false );
		\wp_defer_comment_counting( false );
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
	 * Get custom CLI arguments for the importer.
	 *
	 * @return array
	 */
	public static function get_custom_args() {

		return [
			'entity_type' => [
				'default'     => 'all',
				'description' => __( 'Drupal entity type to migrate (comma-separated).', 'pragmatic-drupal7-importer' ),
				'type'        => 'string',
			],
		];
	}

	/**
	 * Get the total number of Drupal 7 entities to migrate.
	 *
	 * @return int
	 */
	public function get_count() {
		return Integration\get_drupal_entity_counts(
			$this->db(),
			$this->args['entity_type']
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

		return Integration\get_drupal_entities(
			$this->db(),
			(int) $count,
			(int) $offset,
			$this->args['entity_type']
		);
	}

	/**
	 * Transform the to-be-migrated entity.
	 *
	 * @param array $item The import item.
	 *
	 * @return array The import item.
	 */
	public function parse_item( $item ) {

		if ( ! $item ) {
			return false;
		}

		// These are strings but sometimes have a null value!
		$item['post_content'] = (string) $item['post_content'];
		$item['post_excerpt'] = (string) $item['post_excerpt'];
		$item['post_title']   = (string) $item['post_title'];

		// We know these are integers.
		$item['drupal_nid']   = absint( $item['drupal_nid'] );
		$item['drupal_uid']   = absint( $item['drupal_uid'] );

		return apply_filters(
			'pragmatic.drupal7_importer.parse_item',
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

		// Temporary!
		$item['post_type']   = 'post';  /** @todo Post types aren't set yet! */
		$item['post_status'] = 'publish'; /** @todo Get the status from the query! */
		$item['tax_input']   = [];

		$imported_post = wp_insert_post( $item, true );
		if ( is_wp_error( $imported_post ) ) {
			error_log( 'imported post error: ' . print_r( $item, true ) );
			return false;
		}

		// Set item meta.
		foreach ( array_keys( $item ) as $field_name ) {
			if ( strpos( $field_name, 'drupal_' ) !== 0 ) {
				continue;
			}

			// Stores: _drupal_node_id, _drupal_sticky, _drupal_user_id, _drupal_url_alias.
			add_post_meta( $imported_post, "_{$field_name}", $item[ $field_name ] );
			unset( $item[ $field_name ] );
		}

		// Manually tick the progress bar, as we're dealing with multiple entities per batch.
		CLI\HMCI::$progressbar->tick( 1 );
		Utils\clear_local_object_cache();

		if ( is_wp_error( $imported_post ) ) {
			$this->debug( $imported_post->get_error_message() );
			return false;
		}

		return true;
	}
}
