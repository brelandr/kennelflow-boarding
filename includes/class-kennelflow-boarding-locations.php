<?php
/**
 * KennelFlow Hub physical locations (`kf_location` CPT from Core — not a taxonomy).
 *
 * @package KennelFlow_Boarding
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class KennelFlow_Boarding_Locations
 */
class KennelFlow_Boarding_Locations {

	/**
	 * Hub location post type slug (KennelFlow Core).
	 *
	 * @return string
	 */
	public static function post_type_slug() {
		return function_exists( 'ltkf_get_location_post_type' ) ? ltkf_get_location_post_type() : 'kf_location';
	}

	/**
	 * Whether a post ID is a Hub location post.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public static function is_location_post( $post_id ) {
		$post_id = absint( $post_id );
		if ( $post_id < 1 ) {
			return false;
		}
		return self::post_type_slug() === get_post_type( $post_id );
	}

	/**
	 * Resolve a Hub location from REST/query param (numeric ID or post slug).
	 *
	 * @param mixed $location Raw (numeric string, ID, or slug).
	 * @return WP_Post|WP_Error
	 */
	public static function resolve_location_post( $location ) {
		$pt = self::post_type_slug();
		if ( ! post_type_exists( $pt ) ) {
			return new WP_Error(
				'kennelpress_hub_locations_inactive',
				__( 'Location post type is not registered. Is KennelFlow Core active?', 'kennelflow-boarding' ),
				array( 'status' => 503 )
			);
		}

		if ( is_numeric( $location ) ) {
			$post_id = absint( $location );
			if ( $post_id < 1 ) {
				return new WP_Error(
					'kennelpress_unknown_location',
					__( 'Unknown location.', 'kennelflow-boarding' ),
					array( 'status' => 404 )
				);
			}
			$post = get_post( $post_id );
			if ( ! $post || $pt !== $post->post_type ) {
				return new WP_Error(
					'kennelpress_unknown_location',
					__( 'Unknown location.', 'kennelflow-boarding' ),
					array( 'status' => 404 )
				);
			}
			return $post;
		}

		$slug = sanitize_title( (string) $location );
		if ( '' === $slug ) {
			return new WP_Error(
				'kennelpress_unknown_location',
				__( 'Unknown location.', 'kennelflow-boarding' ),
				array( 'status' => 404 )
			);
		}

		$q = new WP_Query(
			array(
				'post_type'              => $pt,
				'name'                   => $slug,
				'post_status'            => array( 'publish', 'draft', 'pending', 'private' ),
				'posts_per_page'         => 1,
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'fields'                 => 'all',
			)
		);

		if ( empty( $q->posts[0] ) || ! $q->posts[0] instanceof WP_Post ) {
			return new WP_Error(
				'kennelpress_unknown_location',
				__( 'Unknown location.', 'kennelflow-boarding' ),
				array( 'status' => 404 )
			);
		}

		return $q->posts[0];
	}
}
