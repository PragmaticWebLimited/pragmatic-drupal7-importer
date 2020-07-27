<?php
declare(strict_types=1);

/**
 * Integration helpers to teach WordPress to be besties with Drupal.
 *
 * We use "entities" and "nodes" here to refer to the Drupal 7 data objects of the same name.
 * For more info, read https://www.drupal.org/docs/7/api/entity-api/an-introduction-to-entitiess
 */
namespace Pragmatic\Drupal7Importer\Integration;

use const ARRAY_A;
use wpdb;

/**
 * Get the total number of Drupal 7 entities to migrate.
 *
 * @param wpdb   $db          Database connection to the Drupal 7 database.
 * @param string $entity_type Drupal entity type to migrate (aka Post Type).
 *                            This may be comma-seperated.
 *
 * @return int
 */
function get_drupal_entity_counts( wpdb $db, string $entity_type ) : int {

	$where_conditions = [
		'n.status = 1',  // "Boolean indicating whether the node is published (visible to non-administrators)".
	];

	// Handle multiple entity types.
	$entities = explode( ',', $entity_type );
	$entities = array_map( 'trim', $entities );

	if ( ! empty( $entities[0] ) && $entities[0] !== 'all' ) {

		$prepared_entities = [];

		// Pass everything through prepare for security and to safely quote strings.
		foreach ( $entities as $entity ) {
			$prepared_entities[] = $db->prepare( '%s', $entity );
		}

		// Build SQL 'IN' operator syntax.
		if ( count( $prepared_entities ) ) {
			$where_conditions[] = sprintf( 'n.type IN ( %s )', implode( ',', $prepared_entities ) );
		}
	}

	// Allow modification of SQL WHERE conditions before querying.
	$where_conditions = apply_filters(
		'pragmatic.drupal7_importer.get_drupal_entity_counts.where_conditions',
		$where_conditions,
		$db,
		$entity_type
	);

	$where_sql = 'WHERE ' . join( ' AND ', $where_conditions );

	// Exclude entities without the equivalent of a post_content or post_excerpt field.
	$join_sql = 'INNER JOIN field_data_body fdb ON n.nid = fdb.entity_id';

	return (int) $db->get_var( "SELECT COUNT(*) FROM node n {$join_sql} {$where_sql}" );
}

/**
 * Get entities (aka Posts) from Drupal 7.
 *
 * @param wpdb   $db          Database connection to the Drupal 7 database.
 * @param mixed  $offset      How many pages of items do we need to offset?
 * @param mixed  $count       How many items do we fetch per query?
 * @param string $entity_type Drupal entity type to migrate (aka Post Type).
 *                            This may be comma-seperated.
 *
 * @return array
 */
function get_drupal_entities( wpdb $db, int $count, int $offset, string $entity_type ) : array {

	// Get full entity data (title, content, excerpt, etc).
	$node_ids = get_node_ids( $db, $count, $offset, $entity_type );
	$entities = get_hydrated_entities( $db, $node_ids );
	$entities = get_entities_urls( $db, $entities );

	// Allow modification of results.
	return apply_filters(
		'pragmatic.drupal7_importer.get_drupal_entities',
		$entities,
		$db,
		$count,
		$offset,
		$entity_type
	);
}

/**
 * Get node (aka Post) IDs from Drupal 7.
 *
 * @param wpdb   $db          Database connection to the Drupal 7 database.
 * @param mixed  $offset      How many pages of items do we need to offset?
 * @param mixed  $count       How many items do we fetch per query?
 * @param string $entity_type Drupal entity type to migrate (aka Post Type).
 *                            This may be comma-seperated.
 *
 * @return array
 */
function get_node_ids( wpdb $db, int $count, int $offset, string $entity_type ) : array {

	$where_conditions = [
		'n.status = 1',  // "Boolean indicating whether the node is published (visible to non-administrators)".
	];

	// Handle multiple entity types.
	$entities = explode( ',', $entity_type );
	$entities = array_map( 'trim', $entities );

	if ( ! empty( $entities[0] ) && $entities[0] !== 'all' ) {

		$prepared_entities = [];

		// Pass everything through prepare for security and to safely quote strings.
		foreach ( $entities as $entity ) {
			$prepared_entities[] = $db->prepare( '%s', $entity );
		}

		// Build SQL 'IN' operator syntax.
		if ( count( $prepared_entities ) ) {
			$where_conditions[] = sprintf( 'n.type IN ( %s )', implode( ',', $prepared_entities ) );
		}
	}

	// Allow modification of SQL WHERE conditions before querying.
	$where_conditions = apply_filters(
		'pragmatic.drupal7_importer.get_node_ids.where_conditions',
		$where_conditions,
		$db,
		$count,
		$offset,
		$entity_type
	);

	$where_sql = 'WHERE ' . join( ' AND ', $where_conditions );

	// Exclude entities without the equivalent of a post_content or post_excerpt field.
	$join_sql = 'INNER JOIN field_data_body fdb ON n.nid = fdb.entity_id';

	return $db->get_col(
		$db->prepare(
			"SELECT n.nid FROM node n {$join_sql} {$where_sql} LIMIT %d, %d",
			$offset,
			$count
		)
	);
}

/**
 * Hydrate node IDs into an associative array containing entity data, and return.
 *
 * Examples of data we're returning include an entity's title, content, and excerpt.
 *
 * @param wpdb  $db       Database connection to the Drupal 7 database.
 * @param int[] $node_ids Node IDs.
 *
 * @return array Array of hydrated entities. Some of the array keys are named after WP_Post-equivalent fields.
 */
function get_hydrated_entities( wpdb $db, array $node_ids ) : array {

	/*
	 * n.changed - The Unix timestamp when the node was most recently saved.
	 * n.comment - Whether comments are allowed on this node: 0 = no, 1 = closed (read only), 2 = open (read/write).
	 * n.created - The Unix timestamp when the node was created.
	 * n.sticky  - Boolean indicating whether the node should be displayed at the top of lists in which it appears.
	 * n.uid     - The users.uid that owns this node; initially, this is the user that created it.
	 */
	$select_sql = <<<'SQL'
		SELECT n.changed as post_modified
		     , IF(n.comment = 2, 'open', 'closed') as comment_status
		     , n.created as post_date
		     , n.nid as drupal_nid
		     , n.sticky as drupal_sticky
		     , n.title as post_title
		     , n.type as post_type
		     , n.uid as drupal_uid
		     , fdb.body_value as post_content
		     , fdb.body_summary as post_excerpt
		FROM node AS n
		INNER JOIN field_data_body as fdb ON n.nid = fdb.entity_id
	SQL;

	// Pass everything through prepare for security and to safely quote strings.
	$prepared_node_ids = [];
	foreach ( $node_ids as $id ) {
		$prepared_node_ids[] = $db->prepare( '%d', $id );
	}

	$where_sql = sprintf( 'WHERE n.nid IN ( %s )', implode( ',', $prepared_node_ids ) );

	return $db->get_results(
		"{$select_sql} {$where_sql}",
		ARRAY_A
	);
}

/**
 * Queries the url_alias table to retrieve url aliases for a specific entity.
 *
 * Will add them as an array under the 'url_alias' key.
 *
 * @param wpdb  $db       Database connection to the Drupal 7 database.
 * @param array $entities Associative array containing entities data.
 *
 * @return array Array of hydrated entities. Some of the array keys are named after WP_Post-equivalent fields.
 */
function get_entities_urls( wpdb $db, array $entities ) : array {
	foreach ( $entities as &$entity ) {
		$query = $db->prepare(
			'SELECT alias FROM url_alias WHERE source = %s',
			sprintf( 'node/%d', $entity['drupal_nid'] )
		);

		$entity['url_alias'] = $db->get_results( $query, ARRAY_A );
	}

	return $entities;
}

/**
 * Get the total number of Drupal 7 images to migrate.
 *
 * @param wpdb $db Database connection to the Drupal 7 database.
 *
 * @return int
 */
function get_drupal_images_count( wpdb $db ) : int {

	$where_conditions = [
		'fm.status = 1', // A field indicating the status of the file. Two status are defined in core: temporary (0) and permanent (1). Temporary files older than DRUPAL_MAXIMUM_TEMP_FILE_AGE will be removed during a cron run.
	];

	// Allow modification of SQL WHERE conditions before querying.
	$where_conditions = apply_filters(
		'pragmatic.drupal7_importer.get_drupal_images_count.where_conditions',
		$where_conditions,
		$db
	);

	$where_sql = 'WHERE ' . join( ' AND ', $where_conditions );

	return (int) $db->get_var( "SELECT COUNT(*) FROM file_managed AS fm {$where_sql}" );
}

/**
 * Get images from Drupal 7.
 *
 * @param wpdb  $db     Database connection to the Drupal 7 database.
 * @param mixed $offset How many pages of items do we need to offset?
 * @param mixed $count  How many items do we fetch per query?
 *
 * @return array
 */
function get_drupal_images( wpdb $db, int $count, int $offset ) : array {

	$select_conditions = [
		'fm.fid as drupal_file_id',
		'fm.uid as drupal_user_id',
		'fm.uri as drupal_file_path',
		'fm.filename as post_title',
		'fm.timestamp as post_date',
		'fm.filemime as post_mime_type',
	];

	// Allow modification of SQL SELECT conditions before querying.
	$select_conditions = apply_filters(
		'pragmatic.drupal7_importer.get_drupal_images.select_conditions',
		$select_conditions,
		$db,
		$count,
		$offset
	);

	$select_sql = 'SELECT ' . join( ', ', $select_conditions );

	$where_conditions = [
		'fm.status = 1', // A field indicating the status of the file. Two status are defined in core: temporary (0) and permanent (1). Temporary files older than DRUPAL_MAXIMUM_TEMP_FILE_AGE will be removed during a cron run.
	];

	// Allow modification of SQL WHERE conditions before querying.
	$where_conditions = apply_filters(
		'pragmatic.drupal7_importer.get_drupal_images.where_conditions',
		$where_conditions,
		$db,
		$count,
		$offset
	);

	$where_sql = 'WHERE ' . join( ' AND ', $where_conditions );

	$images = $db->get_results(
		$db->prepare(
			"{$select_sql} FROM file_managed AS fm {$where_sql} LIMIT %d, %d",
			$offset,
			$count
		),
		\ARRAY_A
	);

	// Allow modification of results.
	return apply_filters(
		'pragmatic.drupal7_importer.get_drupal_images',
		$images,
		$db,
		$count,
		$offset
	);
}

/**
 * Find images in the post content using regexs and return an array
 * containing the path and the source attribute to be swapped.
 *
 * @see https://github.com/buddypress/BuddyPress/blob/master/src/bp-core/classes/class-bp-media-extractor.php#L344
 */
function get_drupal_images_from_post_content( string $post_content ) : array {
	$images = [];

	if ( stripos( $post_content, 'src=' ) !== false ) {
		global $wpdb;

		preg_match_all( '#src=(["\'])([^"\']+)\1#i', $post_content, $img_srcs );  // Matches src="text" and src='text'.

		// <img>.
		if ( ! empty( $img_srcs[2] ) ) {
			$img_srcs[2] = array_unique( $img_srcs[2] );

			foreach ( $img_srcs[2] as $image_src ) {
				// Skip data URIs.
				if ( strtolower( substr( $image_src, 0, 5 ) ) === 'data:' ) {
					continue;
				}

				$parsed_url = wp_parse_url( $image_src );

				// Skip external domain URIs. @todo apply_filter for the host
				if ( isset( $parsed_url['host'] ) && $parsed_url['host'] !== 'www.igamingbusiness.com' ) {
					continue;
				}

				if ( ! isset( $parsed_url['host'] ) ) {
					continue;
				}

				$image_src = esc_url_raw( $image_src );
				if ( ! $image_src ) {
					continue;
				}

				$images[] = [
					'src'  => $image_src,
					'path' => $parsed_url['path'],
				];
			}
		}
	}

	return $images;
}

/**
 * Get the total number of Drupal 7 users to migrate.
 *
 * @param wpdb $db Database connection to the Drupal 7 database.
 *
 * @return int
 */
function get_drupal_users_count( wpdb $db ) : int {

	$select_sql = 'SELECT COUNT(*) FROM users AS u';

	$select_sql = apply_filters(
		'pragmatic.drupal7_importer.get_drupal_users_count.select_sql',
		$select_sql,
		$db
	);

	$where_conditions = [
		'u.status = 1', // Boolean indicating "Whether the user is active(1) or blocked(0)."
	];

	// Allow modification of SQL WHERE conditions before querying.
	$where_conditions = apply_filters(
		'pragmatic.drupal7_importer.get_drupal_users_count.where_conditions',
		$where_conditions,
		$db
	);

	$where_sql = 'WHERE ' . join( ' AND ', $where_conditions );

	return (int) $db->get_var( "{$select_sql} {$where_sql}" );
}

/**
 * Get users from Drupal 7.
 *
 * @param wpdb   $db     Database connection to the Drupal 7 database.
 * @param mixed  $offset How many pages of items do we need to offset?
 * @param mixed  $count  How many items do we fetch per query?
 *
 * @return array
 */
function get_drupal_users( wpdb $db, int $count, int $offset ) : array {
	$select_sql = <<<'SQL'
		 SELECT
		    u.uid     AS drupal_user_id,
		    u.name    AS user_login,
		    u.mail    AS user_email,
		    FROM_UNIXTIME(u.created, '%%Y-%%m-%%d %%H:%%i:%%s') AS user_registered,
		    u.status  AS drupal_user_status,
		    ur.rid    AS drupal_role_id
		 FROM users AS u
		SQL;

	// Get the user roles ids.
	$join_sql = 'LEFT JOIN users_roles AS ur ON u.uid = ur.uid';

	$where_conditions = [
		'u.uid <> 0',
	];

	// Allow modification of SQL WHERE conditions before querying.
	$where_conditions = apply_filters(
		'pragmatic.drupal7_importer.get_drupal_users.where_conditions',
		$where_conditions,
		$db
	);

	$where_sql = 'WHERE ' . join( ' AND ', $where_conditions );

	return $db->get_results(
		$db->prepare(
			"{$select_sql} {$join_sql} {$where_sql} LIMIT %d, %d",
			$offset,
			$count
		),
		ARRAY_A
	);
}
