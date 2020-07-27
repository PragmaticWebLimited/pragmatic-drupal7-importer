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
 * Drupal 7 posts validator.
 */
class PostsValidator extends Iterator\DB\Base {

	/**
	 * Constructor.
	 *
	 * @param array $args  Importer options.
	 * @param string $type Is the iterator being used as a "validator" or an "importer"?
	 */
	public function __construct( $args = [], $type = 'validator' ) {

		parent::__construct( $args, $type );
		$this->args['debugger'] = 'error_log';
	}

	/**
	 * Get importer description.
	 *
	 * @return string
	 */
	public static function get_description() {
		return __( 'Drupal Posts validator description', 'pragmatic-drupal7-importer' );
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
	 * Get the total number of Posts to validate.
	 *
	 * @return int
	 */
	public function get_count() {
		$count = $this->db()->get_var(
			$this->db()->prepare(
				'SELECT COUNT(*) FROM wp_posts WHERE post_content LIKE %s',
				'%' . $this->db()->esc_like( '<img' ) . '%'
			)
		);

		return $count;
	}

	/**
	 * Get WordPress posts.
	 *
	 * @param mixed $offset How many pages of items do we need to offset?
	 * @param mixed $count  How many items do we fetch per query?
	 *
	 * @return array WordPress posts to validate.
	 */
	public function get_items( $offset, $count ) {

		if ( $count === '-1' ) {
			$count = 100;
		}

		$query = $this->db()->prepare(
			'SELECT * FROM wp_posts WHERE post_content LIKE %s ORDER BY ID LIMIT %d,%d',
			'%' . $this->db()->esc_like( '<img' ) . '%',
			(int) $offset,
			(int) $count
		);

		$results = $this->db()->get_results( $query, \ARRAY_A );

		return $results;
	}

	/**
	 * Transform the to-be-validated entity.
	 *
	 * @param array $item The validate item.
	 *
	 * @return array The validate item.
	 */
	public function parse_item( $item ) {

		if ( ! $item ) {
			return false;
		}

		$item['body_content_images'] = Integration\get_drupal_images_from_post_content( $item['post_content'] );

		return $item;
	}

	/**
	 * Processes the validate item.
	 *
	 * @param array $item The validate item.
	 *
	 * @return bool False on failure, true on success.
	 */
	protected function process_item( $item ) {
		global $wpdb;

		$images = $item['body_content_images'];

		if ( ! empty( $images ) ) {

			foreach ( $images as $image_data ) {

				/** @todo apply_filters for the paths to be replaced */
				$search = [
					'/sites/default/files/',
					'/sites/igamingbusiness.com/files/',
				];

				$attachment_id = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_drupal_file_path' AND meta_value = %s",
						str_replace( $search, '', urldecode( $image_data['path'] ) )
					)
				);

				if ( $attachment_id ) {
					// Set image.
					$attachment_src = wp_get_attachment_url( $attachment_id );
				} else {
					// Download image. @todo
					// $attachment_id = \HMCI\Inserter\WP\Attachment::insert_from_path( $image_data['src'] );
				}

				if ( isset( $attachment_src ) ) {
					$item['post_content'] = str_replace( $image_data['src'], $attachment_src, $item['post_content'] );
				}
			}

			if ( isset( $attachment_src ) ) {
				$update_post = wp_update_post( $item, true );
			}
		}

		unset( $item['body_content_images'] );

		/** @todo figure out why $progressbar is null. */
		// Manually tick the progress bar, as we're dealing with multiple entities per batch.
		CLI\HMCI::$progressbar->tick( 1 );
		Utils\clear_local_object_cache();

		if ( isset( $update_post ) && is_wp_error( $update_post ) ) {
			$this->debug( $update_post->get_error_message() );
			return false;
		}

		return true;
	}
}
