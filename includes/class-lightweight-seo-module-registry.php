<?php
/**
 * Public module registry and context-aware loader.
 *
 * @since 1.1.0
 * @package Lightweight_SEO
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Stores module metadata and invokes factories only for enabled request contexts.
 */
class Lightweight_SEO_Module_Registry {

	/** @var Lightweight_SEO_Module_State */
	private $state;

	/** @var array */
	private $modules = array();

	/** @var array */
	private $loaded = array();

	/** @var bool */
	private $finalized = false;

	/**
	 * @param Lightweight_SEO_Module_State $state Module state service.
	 */
	public function __construct( $state ) {
		$this->state = $state;
	}

	/**
	 * Register module metadata and an optional lazy factory.
	 *
	 * @param string $module_id Module identifier.
	 * @param array  $args      Module definition.
	 * @return bool
	 */
	public function register( $module_id, $args ) {
		$module_id = sanitize_key( $module_id );

		if ( $this->finalized || '' === $module_id || isset( $this->modules[ $module_id ] ) || ! is_array( $args ) ) {
			return false;
		}

		$contexts = array_values( array_intersect( array( 'frontend', 'admin', 'editor', 'rest', 'cron' ), array_map( 'sanitize_key', (array) ( $args['contexts'] ?? array() ) ) ) );
		$factory  = $args['factory'] ?? null;

		if ( null !== $factory && ! is_callable( $factory ) ) {
			return false;
		}

		$this->modules[ $module_id ] = array(
			'id'           => $module_id,
			'name'         => sanitize_text_field( $args['name'] ?? $module_id ),
			'description'  => sanitize_text_field( $args['description'] ?? '' ),
			'experimental' => ! empty( $args['experimental'] ),
			'contexts'     => $contexts,
			'dependencies' => array_values( array_filter( array_map( 'sanitize_key', (array) ( $args['dependencies'] ?? array() ) ) ) ),
			'enabled'      => array_key_exists( 'enabled', $args ) ? (bool) $args['enabled'] : null,
			'factory'      => $factory,
		);

		return true;
	}

	/** Finalize registrations. */
	public function finalize() {
		$this->finalized = true;
	}

	/**
	 * Load enabled modules valid for a request context.
	 *
	 * @param string $context Request context.
	 * @return array Loaded module IDs.
	 */
	public function load_context( $context ) {
		$context = sanitize_key( $context );

		foreach ( $this->modules as $module_id => $module ) {
			if ( isset( $this->loaded[ $module_id ] ) || ! $this->is_enabled( $module_id, $module ) || ! in_array( $context, $module['contexts'], true ) ) {
				continue;
			}

			if ( ! empty( $module['factory'] ) ) {
				call_user_func( $module['factory'], $context, $module_id );
			}

			$this->loaded[ $module_id ] = true;
		}

		return array_keys( $this->loaded );
	}

	/**
	 * Return metadata safe for public consumers.
	 *
	 * @return array
	 */
	public function get_public_modules() {
		$public = array();

		foreach ( $this->modules as $module_id => $module ) {
			unset( $module['factory'] );
			$module['enabled']    = $this->is_enabled( $module_id, $module );
			$module['loaded']     = isset( $this->loaded[ $module_id ] );
			$public[ $module_id ] = $module;
		}

		return $public;
	}

	/**
	 * Resolve built-in state or an extension-owned explicit state.
	 *
	 * @param string $module_id Module identifier.
	 * @param array  $module    Module definition.
	 * @return bool
	 */
	private function is_enabled( $module_id, $module ) {
		return null !== $module['enabled'] ? $module['enabled'] : $this->state->is_enabled( $module_id );
	}
}
