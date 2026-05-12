<?php
/**
 * KennelFlow_Boarding public helpers.
 *
 * @package KennelFlow_Boarding
 */

defined( 'ABSPATH' ) || exit;

/**
 * Top-level Front Desk admin menu slug (`add_menu_page` / CPT `show_in_menu` parent).
 *
 * @return string
 */
function kennelflow_boarding_get_front_desk_menu_slug() {
	return (string) kennelflow_boarding_apply_filters( 'front_desk_menu_slug', 'kennelpress-desk' );
}

/**
 * Admin `$hook_suffix` / screen id fragment for a Front Desk submenu page.
 *
 * @param string $page_slug Submenu slug passed to `add_submenu_page`.
 * @return string
 */
function kennelflow_boarding_get_front_desk_page_hook_suffix( $page_slug ) {
	return kennelflow_boarding_get_front_desk_menu_slug() . '_page_' . $page_slug;
}

/**
 * @return string
 */
function kennelpress_get_front_desk_menu_slug() {
	return kennelflow_boarding_get_front_desk_menu_slug();
}

/**
 * @param string $page_slug Submenu slug.
 * @return string
 */
function kennelpress_get_front_desk_page_hook_suffix( $page_slug ) {
	return kennelflow_boarding_get_front_desk_page_hook_suffix( $page_slug );
}
