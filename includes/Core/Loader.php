<?php
/**
 * Hook collector.
 *
 * @package QevoraLayouts
 * @since   1.0.0
 */

namespace QevoraLayouts\Core;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Collects hooks so they can be registered in one place and inspected in tests.
 *
 * @since 1.0.0
 */
class Loader {

	/**
	 * Collected actions.
	 *
	 * @since 1.0.0
	 * @var   array<int,array<string,mixed>>
	 */
	private $actions = array();

	/**
	 * Collected filters.
	 *
	 * @since 1.0.0
	 * @var   array<int,array<string,mixed>>
	 */
	private $filters = array();

	/**
	 * Queues an action.
	 *
	 * @since 1.0.0
	 *
	 * @param string   $hook          Hook name.
	 * @param callable $callback      Callback.
	 * @param int      $priority      Priority.
	 * @param int      $accepted_args Accepted arguments.
	 * @return void
	 */
	public function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		$this->actions[] = array(
			'hook'          => (string) $hook,
			'callback'      => $callback,
			'priority'      => (int) $priority,
			'accepted_args' => (int) $accepted_args,
		);
	}

	/**
	 * Queues a filter.
	 *
	 * @since 1.0.0
	 *
	 * @param string   $hook          Hook name.
	 * @param callable $callback      Callback.
	 * @param int      $priority      Priority.
	 * @param int      $accepted_args Accepted arguments.
	 * @return void
	 */
	public function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		$this->filters[] = array(
			'hook'          => (string) $hook,
			'callback'      => $callback,
			'priority'      => (int) $priority,
			'accepted_args' => (int) $accepted_args,
		);
	}

	/**
	 * Registers every queued hook.
	 *
	 * @since 1.0.0
	 *
	 * @return int Number of hooks registered.
	 */
	public function run() {
		$count = 0;

		foreach ( $this->actions as $action ) {
			add_action( $action['hook'], $action['callback'], $action['priority'], $action['accepted_args'] );
			++$count;
		}

		foreach ( $this->filters as $filter ) {
			add_filter( $filter['hook'], $filter['callback'], $filter['priority'], $filter['accepted_args'] );
			++$count;
		}

		return $count;
	}

	/**
	 * Returns the queued hooks without registering them.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string,array<int,array<string,mixed>>>
	 */
	public function get_queued() {
		return array(
			'actions' => $this->actions,
			'filters' => $this->filters,
		);
	}

	/**
	 * Drops all queued hooks.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function reset() {
		$this->actions = array();
		$this->filters = array();
	}
}
