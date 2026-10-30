<?php
/**
 * Shared adapter behaviour.
 *
 * @package QevoraLayouts
 * @since   1.0.0
 */

namespace QevoraLayouts\Integrations;

use QevoraLayouts\Core\Layout;
use QevoraLayouts\Core\Logger;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Base class implementing the parts of the contract that never change.
 *
 * @since 1.0.0
 */
abstract class Abstract_Builder_Adapter implements Builder_Adapter_Interface {

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 */
	public function register() {
		// Adapters without hooks need no registration.
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 */
	public function supports( Layout $layout ) {
		return $this->is_available();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 */
	public function supports_editing() {
		return false;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 */
	public function get_version() {
		return '';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 */
	public function get_requirements() {
		return array();
	}

	/**
	 * Runs a callable while guarding against third party errors.
	 *
	 * A builder that throws or fatals must never take the whole page down: the
	 * error is logged and an empty string is returned so the caller can fall
	 * back to another adapter.
	 *
	 * @since 1.0.0
	 *
	 * @param callable $callback Callable returning a string.
	 * @param string   $context  Description used in the debug log.
	 * @return string
	 */
	protected function guard( callable $callback, $context = '' ) {
		$result = $this->guard_value( $callback, $context );

		return is_string( $result ) ? $result : '';
	}

	/**
	 * Runs a callable while guarding against third party errors.
	 *
	 * Same as {@see guard()} but keeps the original return type, so objects and
	 * booleans coming from a builder API can be used safely.
	 *
	 * @since 1.0.0
	 *
	 * @param callable $callback Callable.
	 * @param string   $context  Description used in the debug log.
	 * @return mixed Null when the callable failed.
	 */
	protected function guard_value( callable $callback, $context = '' ) {
		try {
			return $callback();
		} catch ( \Throwable $e ) {
			Logger::error(
				'Builder adapter failed.',
				array(
					'adapter' => $this->get_id(),
					'context' => (string) $context,
					'error'   => $e->getMessage(),
				)
			);

			return null;
		}
	}
}
