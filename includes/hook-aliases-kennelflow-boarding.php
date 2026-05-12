<?php
/**
 * Fires both legacy kennelpress_* and kennelflow_boarding_* hooks.
 *
 * @package KennelFlow_Boarding
 */

defined( 'ABSPATH' ) || exit;

/**
 * Fire a KennelPress-namespaced and KennelFlow Boarding action (same args).
 *
 * @param string $tag_suffix Suffix after kennelpress_ / kennelflow_boarding_.
 * @param mixed  ...$args     Extra arguments.
 * @return void
 */
function kennelflow_boarding_do_action( $tag_suffix, ...$args ) {
	do_action( 'kennelpress_' . $tag_suffix, ...$args );
	do_action( 'kennelflow_boarding_' . $tag_suffix, ...$args );
}

/**
 * Run legacy and new filter hooks, passing value through both chains.
 *
 * @param string $tag_suffix Suffix after kennelpress_ / kennelflow_boarding_.
 * @param mixed  $value      Value to filter.
 * @param mixed  ...$args    Extra arguments.
 * @return mixed
 */
function kennelflow_boarding_apply_filters( $tag_suffix, $value, ...$args ) {
	$value = apply_filters( 'kennelpress_' . $tag_suffix, $value, ...$args );
	return apply_filters( 'kennelflow_boarding_' . $tag_suffix, $value, ...$args );
}
