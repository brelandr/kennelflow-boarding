<?php
/**
 * Base REST controller for KennelFlow Boarding (supports legacy namespace alias).
 *
 * @package KennelFlow_Boarding
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class KennelFlow_Boarding_REST_Controller
 */
abstract class KennelFlow_Boarding_REST_Controller extends WP_REST_Controller {

	/**
	 * REST namespace (mirrors WP_REST_Controller::$namespace for safe public access).
	 *
	 * @var string
	 */
	private $kf_rest_namespace = 'kennelflow-boarding/v1';

	/**
	 * Constructor.
	 *
	 * @param string $rest_base REST base slug (empty when routes use custom paths).
	 * @param string $namespace REST namespace.
	 */
	public function __construct( $rest_base = '', $namespace = 'kennelflow-boarding/v1' ) {
		$this->kf_rest_namespace = $namespace;
		$this->namespace         = $namespace;
		if ( '' !== $rest_base ) {
			$this->rest_base = $rest_base;
		}
	}

	/**
	 * REST namespace for route registration.
	 *
	 * @return string
	 */
	public function get_namespace() {
		return $this->kf_rest_namespace;
	}
}
