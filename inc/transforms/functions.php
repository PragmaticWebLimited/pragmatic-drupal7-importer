<?php
declare(strict_types=1);

/**
 * Utilities to help convert Drupal content into something for WordPress.
 */
namespace Pragmatic\Drupal7Importer\Transforms;

use const ENT_COMPAT;
use const ENT_HTML5;

/**
 * Convert HTML entities to characters in certain fields.
 *
 * @param array $drupal_entity A Drupal entity (e.g. a Post).
 *
 * @return array Updated Drupal entity without HTML entities.
 */
function decode_html_entities( array $drupal_entity ) : array {

	$drupal_entity['post_content'] = html_entity_decode( $drupal_entity['post_content'], ENT_COMPAT | ENT_HTML5 );
	$drupal_entity['post_excerpt'] = html_entity_decode( $drupal_entity['post_excerpt'], ENT_COMPAT | ENT_HTML5 );
	$drupal_entity['post_title']   = html_entity_decode( $drupal_entity['post_title'], ENT_COMPAT | ENT_HTML5 );

	return $drupal_entity;
}

/**
 * Maps the drupal user role id with a WordPress one. Falls back to subscriber if role id doesn't exist.
 *
 * @param array $user_entity User to migrate.
 * @return array Updated user with WordPress role.
 */
function map_drupal_roles_to_wp( array $user_entity ) : array {
	$user_role_id = $user_entity['drupal_role_id'];

	/**
	 * Filters the drupal/wp mapping array.
	 *
	 * @param array $drupal_roles Array of key => value drupal/wp roles.
	 *                            Key is the drupal role id.
	 *                            Value is the WordPress role name.
	 * @param int   $user_role_id Drupal user role id.
	 * @param array $user_entity  User to migrate.
	 */
	$drupal_roles = apply_filters( 'pragmatic.drupal7_importer.map_drupal_roles_to_wp.roles_mapping', [], $user_role_id, $user_entity );

	$user_entity['role'] = $drupal_roles[ $user_role_id ] ?? 'subscriber';

	return $user_entity;
}

/**
 * Convert the unix timestamp to a valid date format WordPress can digest.
 *
 * @param array $drupal_entity A Drupal entity (e.g. a Post).
 *
 * @return array Updated Drupal entity with correct post dates.
 */
function format_post_date( array $drupal_entity ) : array {
	$drupal_entity['post_date']     = date( 'Y-m-d H:i:s', absint( $drupal_entity['post_date'] ) );
	$drupal_entity['post_modified'] = date( 'Y-m-d H:i:s', absint( $drupal_entity['post_modified'] ) );

	return $drupal_entity;
}

/**
 * Find the WordPress user id from the drupal user id and assign it as post author.
 *
 * @param array $drupal_entity A Drupal entity (e.g. a Post).
 *
 * @return array Updated Drupal entity with correct post author.
 */
function assign_post_author( array $drupal_entity ) : array {
	static $drupal_user_ids;

	// Run the query once for the import to speed up the process.
	if ( $drupal_user_ids === null ) {
		global $wpdb;

		// Get the drupal user id and WordPress user id for all users.
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT meta_value AS drupal_uid, user_id AS wordpress_uid FROM {$wpdb->usermeta} WHERE meta_key = %s",
				'_drupal_user_id'
			),
			ARRAY_A
		);

		// key => value array where key is the drupal user id and value is the WordPress user id.
		foreach ( $results as $data ) {
			$drupal_user_ids[ absint( $data['drupal_uid'] ) ] = absint( $data['wordpress_uid'] );
		}
	}

	// Get the WordPress user id from the drupal user id. Falls back to 0 if not found.
	$drupal_entity['post_author'] = $drupal_user_ids[ $drupal_entity['drupal_uid'] ] ?? 0;

	return $drupal_entity;
}

/**
 * Creates a separate meta key for each URL alias.
 *
 * @param array $drupal_entity A Drupal entity (e.g. a Post).
 *
 * @return array Updated Drupal entity with correct url aliases.
 */
function transform_url_aliases( array $drupal_entity ) : array {
	foreach ( $drupal_entity['url_alias'] as $alias ) {
		$drupal_entity['drupal_url_alias'] = $alias['alias'];
	}

	return $drupal_entity;
}
