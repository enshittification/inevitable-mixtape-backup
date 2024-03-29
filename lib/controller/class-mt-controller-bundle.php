<?php
/**
 * A Collection of Controllers, under the same prefix
 *
 * @package MT/Controller
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MT_Controller_Bundle
 */
class MT_Controller_Bundle implements MT_Interfaces_Controller_Bundle {

	/**
	 * The prefix of this bundle (required)
	 *
	 * @var string|null
	 */
	protected $prefix = null;

	/**
	 * Collection of Mixtape_Rest_Api_Controller subclasses
	 *
	 * @var array
	 */
	protected $endpoints = array();
	/**
	 * Our Endpoint Builders
	 *
	 * @var array
	 */
	private $endpoint_builders;
	/**
	 * Environment.
	 *
	 * @var MT_Environment
	 */
	private $environment;

	/**
	 * MT_Controller_Bundle_Definition constructor.
	 *
	 * @param string $bundle_prefix Prefix.
	 * @param array  $endpoints Builders.
	 */
	function __construct( $bundle_prefix, $endpoints ) {
		$this->prefix = $bundle_prefix;
		$this->endpoints = $endpoints;
	}

	/**
	 * Register this bundle with the environment.
	 *
	 * @param MT_Environment $environment The Environment.
	 * @return MT_Controller_Bundle $this
	 * @throws MT_Exception When no prefix is defined.
	 */
	function register( $environment ) {
		MT_Expect::that( null !== $this->prefix, 'prefix should be defined' );
		$this->environment = $environment;
		/**
		 * Add/remove endpoints. Useful for extensions
		 *
		 * @param array   $endpoints An array of MT_Interfaces_Controller
		 * @param $bundle MT_Controller_Bundle The bundle instance.
		 *
		 * @return array
		 */
		$endpoints = (array) apply_filters(
			'mt_rest_api_controller_bundle_get_endpoints',
			$this->endpoints,
			$this
		);

		foreach ( $endpoints as $endpoint ) {
			/**
			 * Controller
			 *
			 * @var MT_Interfaces_Controller
			 */
			$endpoint->register( $this, $this->environment );
		}

		return $this;
	}

	/**
	 * Get Prefix.
	 *
	 * @return string
	 */
	function get_prefix() {
		return $this->prefix;
	}
}

