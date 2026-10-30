<?php
/**
 * Minimal WordPress stubs for the standalone test runner.
 *
 * These stubs implement just enough of WordPress to exercise the plugin's pure
 * logic (sanitizers, the condition engine, the importer, the repository) without
 * a database or a running site. They are NOT a WordPress replacement: the real
 * integration checklist lives in `tests/INTEGRATION.md` and must be run on a
 * WordPress install before a release.
 *
 * Anything that needs MySQL, the REST server, the block parser or a theme is
 * deliberately out of scope here.
 *
 * @package QevoraLayouts
 * @since   1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Mutable test environment.
 *
 * Tests configure capabilities, query conditionals, active plugins and options
 * through this class and reset it in `setUp()`.
 */
class Qevora_Test_Env {

	/** @var array<string,mixed> Options. */
	public static $options = array();

	/** @var array<string,mixed> Transients. */
	public static $transients = array();

	/** @var array<string,array<string,mixed>> Object cache: group => key => value. */
	public static $cache = array();

	/**
	 * TTL recorded for every object-cache write. Parallel to `$cache`.
	 *
	 * @var array<string,array<string,int>>
	 */
	public static $cache_ttl = array();

	/**
	 * Canned HTTP responses, keyed by request URL.
	 *
	 * Each value is either a response array (`body`, `response`, `headers`) or a
	 * `WP_Error`. Anything without an entry fails like an unreachable host.
	 *
	 * @var array<string,array<string,mixed>|\WP_Error>
	 */
	public static $http = array();

	/**
	 * Every outbound HTTP request as `array( url, args )`.
	 *
	 * @var array<int,array<int,mixed>>
	 */
	public static $http_requests = array();

	/**
	 * Registered and enqueued assets, keyed by handle.
	 *
	 * @var array<string,array<string,mixed>>
	 */
	public static $assets = array();

	/**
	 * Redirects requested through wp_safe_redirect().
	 *
	 * @var array<int,array<string,mixed>>
	 */
	public static $redirects = array();

	/**
	 * The last payload handed to wp_send_json_success()/wp_send_json_error().
	 *
	 * @var array<string,mixed>|null
	 */
	public static $json = null;

	/**
	 * Registered REST routes as `namespace/route => handler args`.
	 *
	 * @var array<string,array<string,mixed>>
	 */
	public static $rest_routes = array();

	/**
	 * Registered blocks, keyed by block name.
	 *
	 * @var array<string,array<string,mixed>>
	 */
	public static $blocks = array();

	/**
	 * Registered shortcodes, keyed by tag.
	 *
	 * @var array<string,callable>
	 */
	public static $shortcodes = array();

	/**
	 * Admin pages, meta boxes and settings registered by the plugin.
	 *
	 * @var array<string,array<int,mixed>>
	 */
	public static $admin = array(
		'menus'     => array(),
		'meta_boxes' => array(),
		'sections'  => array(),
		'fields'    => array(),
		'settings'  => array(),
	);

	/**
	 * Everything echoed by the stubs instead of rendering real markup.
	 *
	 * @var string
	 */
	public static $output = '';

	/**
	 * The current screen, mimicking get_current_screen().
	 *
	 * @var object|null
	 */
	public static $screen = null;

	/**
	 * A tiny stand-in for $_SERVER.
	 *
	 * @var array<string,mixed>
	 */
	public static $server = array();

	/** @var array<int,object> Posts: ID => WP_Post. */
	public static $posts = array();

	/** @var array<int,array<string,mixed>> Post meta: ID => key => value. */
	public static $meta = array();

	/** @var int Next post ID. */
	public static $next_id = 1;

	/** @var array<string,bool> Capabilities the current user has. */
	public static $caps = array();

	/** @var int Current user ID. */
	public static $user_id = 1;

	/** @var object[] Users. */
	public static $users = array();

	/** @var array<string,bool> Query conditionals. */
	public static $query = array();

	/** @var string[] Active plugin basenames. */
	public static $active_plugins = array();

	/** @var array<string,int> Actions that ran. */
	public static $did_actions = array();

	/** @var array<string,array<int,array<int,callable>>> Filters: tag => priority => callbacks. */
	public static $filters = array();

	/** @var bool Whether the active theme is a block theme. */
	public static $block_theme = false;

	/** @var string[] Post type slugs that exist. */
	public static $post_types = array( 'post', 'page', 'attachment', 'qevora_layout' );

	/** @var string[] Taxonomy slugs that exist. */
	public static $taxonomies = array( 'category', 'post_tag' );

	/** @var array<int,object> Terms. */
	public static $terms = array();

	/** @var string[] Messages passed to _doing_it_wrong(). */
	public static $doing_it_wrong = array();

	/** @var string[] Messages passed to wp_die(). */
	public static $died = array();

	/**
	 * Resets everything.
	 *
	 * @return void
	 */
	public static function reset() {
		self::$options        = array();
		self::$transients     = array();
		self::$cache          = array();
		self::$cache_ttl      = array();
		self::$http             = array();
		self::$http_requests    = array();
		self::$assets           = array();
		self::$redirects        = array();
		self::$json             = null;
		self::$rest_routes      = array();
		self::$blocks           = array();
		self::$shortcodes       = array();
		self::$admin            = array(
			'menus'      => array(),
			'meta_boxes' => array(),
			'sections'   => array(),
			'fields'     => array(),
			'settings'   => array(),
		);
		self::$output           = '';
		self::$screen           = null;
		self::$server           = array();
		self::$posts          = array();
		self::$meta           = array();
		self::$next_id        = 1;
		self::$caps           = array(
			'edit_qevora_layouts'   => true,
			'publish_qevora_layouts' => true,
			'delete_qevora_layouts' => true,
			'manage_options'        => true,
			'edit_post'             => true,
			'delete_post'           => true,
			'publish_post'          => true,
			'unfiltered_html'       => true,
			'upload_files'          => true,
			'list_users'            => true,
			'edit_pages'            => true,
		);
		self::$user_id        = 1;
		self::$users          = array();
		self::$query          = array();
		self::$active_plugins = array();
		self::$did_actions    = array();
		self::$filters        = array();
		self::$block_theme    = false;
		self::$post_types     = array( 'post', 'page', 'attachment', 'qevora_layout' );
		self::$taxonomies     = array( 'category', 'post_tag' );
		self::$terms          = array();
		self::$doing_it_wrong = array();
		self::$died           = array();
	}

	/**
	 * Adds a post to the in-memory store.
	 *
	 * @param array<string,mixed> $args Post arguments.
	 * @return int Post ID.
	 */
	public static function add_post( array $args ) {
		$id = isset( $args['ID'] ) && (int) $args['ID'] > 0 ? (int) $args['ID'] : self::$next_id++;

		if ( $id >= self::$next_id ) {
			self::$next_id = $id + 1;
		}

		$post = new WP_Post(
			array(
				'ID'            => $id,
				'post_title'    => isset( $args['post_title'] ) ? (string) $args['post_title'] : '',
				'post_content'  => isset( $args['post_content'] ) ? (string) $args['post_content'] : '',
				'post_status'   => isset( $args['post_status'] ) ? (string) $args['post_status'] : 'publish',
				'post_type'     => isset( $args['post_type'] ) ? (string) $args['post_type'] : 'post',
				'post_author'   => isset( $args['post_author'] ) ? (int) $args['post_author'] : 1,
				'post_date'     => isset( $args['post_date'] ) ? (string) $args['post_date'] : gmdate( 'Y-m-d H:i:s' ),
				'post_name'     => isset( $args['post_name'] ) ? (string) $args['post_name'] : '',
				'post_parent'   => isset( $args['post_parent'] ) ? (int) $args['post_parent'] : 0,
			)
		);

		self::$posts[ $id ] = $post;

		return $id;
	}

	/**
	 * Returns a query conditional.
	 *
	 * @param string $key     Conditional name.
	 * @param mixed  $default Default.
	 * @return mixed
	 */
	public static function is( $key, $default = false ) {
		return isset( self::$query[ $key ] ) ? self::$query[ $key ] : $default;
	}
}

/**
 * Post object.
 */
class WP_Post {

	/** @var int */
	public $ID = 0;

	/** @var string */
	public $post_title = '';

	/** @var string */
	public $post_content = '';

	/** @var string */
	public $post_status = 'publish';

	/** @var string */
	public $post_type = 'post';

	/** @var int */
	public $post_author = 1;

	/** @var string */
	public $post_date = '';

	/** @var string */
	public $post_name = '';

	/** @var int */
	public $post_parent = 0;

	/**
	 * Constructor.
	 *
	 * @param array<string,mixed> $data Data.
	 */
	public function __construct( array $data = array() ) {
		foreach ( $data as $key => $value ) {
			if ( property_exists( $this, $key ) ) {
				$this->$key = $value;
			}
		}
	}

	/**
	 * Array access helper used by some WordPress code paths.
	 *
	 * @param string $key Key.
	 * @return mixed
	 */
	public function __get( $key ) {
		return isset( $this->$key ) ? $this->$key : null;
	}
}

/**
 * Term object.
 */
class WP_Term {

	/** @var int */
	public $term_id = 0;

	/** @var string */
	public $name = '';

	/** @var string */
	public $slug = '';

	/** @var string */
	public $taxonomy = 'category';

	/** @var int */
	public $parent = 0;

	/** @var int */
	public $count = 0;

	/**
	 * Constructor.
	 *
	 * @param array<string,mixed> $data Data.
	 */
	public function __construct( array $data = array() ) {
		foreach ( $data as $key => $value ) {
			if ( property_exists( $this, $key ) ) {
				$this->$key = $value;
			}
		}
	}
}

/**
 * User object.
 */
class WP_User {

	/** @var int */
	public $ID = 0;

	/** @var string */
	public $display_name = '';

	/** @var string[] */
	public $roles = array();

	/**
	 * Constructor.
	 *
	 * @param array<string,mixed> $data Data.
	 */
	public function __construct( array $data = array() ) {
		foreach ( $data as $key => $value ) {
			if ( property_exists( $this, $key ) ) {
				$this->$key = $value;
			}
		}
	}

	/**
	 * Whether the user has a role.
	 *
	 * @param string $role Role slug.
	 * @return bool
	 */
	public function has_cap( $role ) {
		return ! empty( Qevora_Test_Env::$caps[ $role ] );
	}
}

/**
 * Error object.
 */
class WP_Error {

	/** @var array<string,string[]> */
	public $errors = array();

	/** @var array<string,mixed> */
	public $error_data = array();

	/** @var array<string,array<int,mixed>> */
	protected $additional_data = array();

	/**
	 * Constructor.
	 *
	 * @param string $code    Code.
	 * @param string $message Message.
	 * @param mixed  $data    Data.
	 */
	public function __construct( $code = '', $message = '', $data = '' ) {
		if ( '' === $code ) {
			return;
		}

		$this->add( $code, $message, $data );
	}

	/**
	 * Adds an error.
	 *
	 * @param string $code    Code.
	 * @param string $message Message.
	 * @param mixed  $data    Data.
	 * @return void
	 */
	public function add( $code, $message, $data = '' ) {
		$this->errors[ $code ][] = (string) $message;

		if ( '' !== $data ) {
			if ( isset( $this->error_data[ $code ] ) ) {
				$this->additional_data[ $code ][] = $data;
			} else {
				$this->error_data[ $code ] = $data;
			}
		}
	}

	/**
	 * Returns all error codes.
	 *
	 * @return string[]
	 */
	public function get_error_codes() {
		return array_keys( $this->errors );
	}

	/**
	 * Returns the first error code.
	 *
	 * @return string
	 */
	public function get_error_code() {
		$codes = $this->get_error_codes();

		return empty( $codes ) ? '' : (string) $codes[0];
	}

	/**
	 * Returns the messages of one code.
	 *
	 * @param string $code Code.
	 * @return string[]
	 */
	public function get_error_messages( $code = '' ) {
		if ( '' === $code ) {
			$all = array();

			foreach ( $this->errors as $messages ) {
				$all = array_merge( $all, $messages );
			}

			return $all;
		}

		return isset( $this->errors[ $code ] ) ? $this->errors[ $code ] : array();
	}

	/**
	 * Returns the first message.
	 *
	 * @param string $code Code.
	 * @return string
	 */
	public function get_error_message( $code = '' ) {
		if ( '' === $code ) {
			$code = $this->get_error_code();
		}

		$messages = $this->get_error_messages( $code );

		return empty( $messages ) ? '' : (string) $messages[0];
	}

	/**
	 * Returns the error data.
	 *
	 * @param string $code Code.
	 * @return mixed
	 */
	public function get_error_data( $code = '' ) {
		if ( '' === $code ) {
			$code = $this->get_error_code();
		}

		return isset( $this->error_data[ $code ] ) ? $this->error_data[ $code ] : null;
	}

	/**
	 * Whether any error is stored.
	 *
	 * @return bool
	 */
	public function has_errors() {
		return ! empty( $this->errors );
	}
}

/**
 * Query object.
 *
 * Supports the small subset of arguments the plugin uses.
 */
class WP_Query {

	/** @var array<int,mixed> */
	public $posts = array();

	/** @var int */
	public $found_posts = 0;

	/** @var int */
	public $max_num_pages = 0;

	/** @var int */
	public $post_count = 0;

	/** @var array<string,mixed> */
	public $query_vars = array();

	/**
	 * Constructor.
	 *
	 * @param array<string,mixed> $args Query arguments.
	 */
	public function __construct( array $args = array() ) {
		$this->query( $args );
	}

	/**
	 * Runs a query against the in-memory store.
	 *
	 * @param array<string,mixed> $args Query arguments.
	 * @return array<int,mixed>
	 */
	public function query( array $args ) {
		$this->query_vars = $args;

		$post_type  = isset( $args['post_type'] ) ? (array) $args['post_type'] : array( 'post' );
		$status     = isset( $args['post_status'] ) ? (array) $args['post_status'] : array( 'publish' );
		$ids_only   = isset( $args['fields'] ) && 'ids' === $args['fields'];
		$per_page   = isset( $args['posts_per_page'] ) ? (int) $args['posts_per_page'] : 10;
		$paged      = isset( $args['paged'] ) ? max( 1, (int) $args['paged'] ) : 1;
		$search     = isset( $args['s'] ) ? strtolower( (string) $args['s'] ) : '';
		$order      = isset( $args['order'] ) ? strtoupper( (string) $args['order'] ) : 'DESC';
		$orderby    = isset( $args['orderby'] ) ? $args['orderby'] : 'date';
		$post_in    = isset( $args['post__in'] ) ? array_map( 'intval', (array) $args['post__in'] ) : array();
		$post_notin = isset( $args['post__not_in'] ) ? array_map( 'intval', (array) $args['post__not_in'] ) : array();
		$author     = isset( $args['author'] ) ? (int) $args['author'] : 0;
		$meta_query = isset( $args['meta_query'] ) && is_array( $args['meta_query'] ) ? $args['meta_query'] : array();

		if ( in_array( 'any', $status, true ) ) {
			$status = array( 'publish', 'draft', 'pending', 'private', 'future' );
		}

		$matched = array();

		foreach ( Qevora_Test_Env::$posts as $post ) {
			if ( ! in_array( $post->post_type, $post_type, true ) ) {
				continue;
			}

			if ( ! in_array( $post->post_status, $status, true ) ) {
				continue;
			}

			if ( ! empty( $post_in ) && ! in_array( (int) $post->ID, $post_in, true ) ) {
				continue;
			}

			if ( ! empty( $post_notin ) && in_array( (int) $post->ID, $post_notin, true ) ) {
				continue;
			}

			if ( $author > 0 && (int) $post->post_author !== $author ) {
				continue;
			}

			if ( '' !== $search && false === strpos( strtolower( $post->post_title ), $search ) ) {
				continue;
			}

			if ( ! self::matches_meta( $post, $meta_query ) ) {
				continue;
			}

			$matched[] = $post;
		}

		$matched = self::sort_posts( $matched, $orderby, $order, $args );

		$this->found_posts = count( $matched );

		if ( $per_page > 0 && empty( $args['no_found_rows'] ) ) {
			$this->max_num_pages = (int) ceil( $this->found_posts / $per_page );
		}

		if ( $per_page > 0 ) {
			$matched = array_slice( $matched, ( $paged - 1 ) * $per_page, $per_page );
		}

		$this->posts      = $ids_only ? array_map( 'intval', wp_list_pluck( $matched, 'ID' ) ) : $matched;
		$this->post_count = count( $this->posts );

		return $this->posts;
	}

	/**
	 * Evaluates a (single level) meta query.
	 *
	 * @param WP_Post              $post       Post.
	 * @param array<int,mixed>     $meta_query Meta query.
	 * @return bool
	 */
	private static function matches_meta( $post, array $meta_query ) {
		$relation = isset( $meta_query['relation'] ) ? strtoupper( (string) $meta_query['relation'] ) : 'AND';
		$results  = array();

		foreach ( $meta_query as $key => $clause ) {
			if ( 'relation' === $key || ! is_array( $clause ) || ! isset( $clause['key'] ) ) {
				continue;
			}

			$value = get_post_meta( $post->ID, (string) $clause['key'], true );
			$want  = isset( $clause['value'] ) ? $clause['value'] : null;

			if ( null === $want ) {
				$results[] = ( '' !== $value && null !== $value );
				continue;
			}

			if ( is_array( $want ) ) {
				$results[] = in_array( (string) $value, array_map( 'strval', $want ), true );
				continue;
			}

			$compare = isset( $clause['compare'] ) ? strtoupper( (string) $clause['compare'] ) : '=';

			switch ( $compare ) {
				case '!=':
					$results[] = (string) $value !== (string) $want;
					break;
				case '>':
					$results[] = (float) $value > (float) $want;
					break;
				case '<':
					$results[] = (float) $value < (float) $want;
					break;
				case 'LIKE':
					$results[] = false !== strpos( (string) $value, (string) $want );
					break;
				case 'NOT LIKE':
					$results[] = false === strpos( (string) $value, (string) $want );
					break;
				case 'EXISTS':
					$results[] = isset( Qevora_Test_Env::$meta[ $post->ID ][ $clause['key'] ] );
					break;
				case 'NOT EXISTS':
					$results[] = ! isset( Qevora_Test_Env::$meta[ $post->ID ][ $clause['key'] ] );
					break;
				default:
					$results[] = (string) $value === (string) $want;
			}
		}

		if ( empty( $results ) ) {
			return true;
		}

		return 'OR' === $relation ? in_array( true, $results, true ) : ! in_array( false, $results, true );
	}

	/**
	 * Sorts posts.
	 *
	 * @param array<int,WP_Post> $posts   Posts.
	 * @param mixed              $orderby Orderby argument.
	 * @param string             $order   ASC|DESC.
	 * @param array<string,mixed> $args   Query arguments.
	 * @return array<int,WP_Post>
	 */
	private static function sort_posts( array $posts, $orderby, $order, array $args ) {
		// WordPress honours a direction per key when `orderby` is an array.
		$plan = array();

		if ( is_array( $orderby ) && ! empty( $orderby ) ) {
			foreach ( $orderby as $key => $direction ) {
				$plan[] = array( (string) $key, strtoupper( (string) $direction ) );
			}
		} else {
			$plan[] = array( is_array( $orderby ) ? 'date' : (string) $orderby, strtoupper( (string) $order ) );
		}

		usort(
			$posts,
			function ( $a, $b ) use ( $plan, $args ) {
				foreach ( $plan as $step ) {
					$result = self::compare( $a, $b, $step[0], $args );

					if ( 0 !== $result ) {
						return 'ASC' === $step[1] ? $result : -1 * $result;
					}
				}

				return 0;
			}
		);

		return $posts;
	}

	/**
	 * Compares two posts on one key.
	 *
	 * @param WP_Post            $a       First post.
	 * @param WP_Post            $b       Second post.
	 * @param string             $key     Orderby key.
	 * @param array<string,mixed> $args   Query arguments.
	 * @return int
	 */
	private static function compare( $a, $b, $key, array $args ) {
		switch ( $key ) {
			case 'ID':
				return ( (int) $a->ID < (int) $b->ID ) ? -1 : ( (int) $a->ID > (int) $b->ID ? 1 : 0 );

			case 'title':
			case 'name':
				return strcmp( (string) $a->post_title, (string) $b->post_title );

			case 'date':
				return strcmp( (string) $a->post_date, (string) $b->post_date );

			case 'meta_value':
				$meta_key = isset( $args['meta_key'] ) ? (string) $args['meta_key'] : '';
				return strcmp( (string) get_post_meta( $a->ID, $meta_key, true ), (string) get_post_meta( $b->ID, $meta_key, true ) );

			case 'meta_value_num':
				$meta_key = isset( $args['meta_key'] ) ? (string) $args['meta_key'] : '';
				$left  = (float) get_post_meta( $a->ID, $meta_key, true );
				$right = (float) get_post_meta( $b->ID, $meta_key, true );
				return ( $left < $right ) ? -1 : ( $left > $right ? 1 : 0 );
		}

		return 0;
	}

	/**
	 * Whether the query found posts.
	 *
	 * @return bool
	 */
	public function have_posts() {
		return ! empty( $this->posts );
	}
}

/* -------------------------------------------------------------------------
 * Hooks
 * ---------------------------------------------------------------------- */

/**
 * Registers a filter.
 *
 * @param string   $tag      Tag.
 * @param callable $callback Callback.
 * @param int      $priority Priority.
 * @param int      $args     Accepted arguments.
 * @return true
 */
function add_filter( $tag, $callback, $priority = 10, $args = 1 ) {
	Qevora_Test_Env::$filters[ (string) $tag ][ (int) $priority ][] = array( $callback, (int) $args );
	ksort( Qevora_Test_Env::$filters[ (string) $tag ] );

	return true;
}

/**
 * Registers an action.
 *
 * @param string   $tag      Tag.
 * @param callable $callback Callback.
 * @param int      $priority Priority.
 * @param int      $args     Accepted arguments.
 * @return true
 */
function add_action( $tag, $callback, $priority = 10, $args = 1 ) {
	return add_filter( $tag, $callback, $priority, $args );
}

/**
 * Removes a filter.
 *
 * @param string   $tag      Tag.
 * @param callable $callback Callback.
 * @param int      $priority Priority.
 * @return bool
 */
function remove_filter( $tag, $callback, $priority = 10 ) {
	if ( empty( Qevora_Test_Env::$filters[ $tag ][ $priority ] ) ) {
		return false;
	}

	foreach ( Qevora_Test_Env::$filters[ $tag ][ $priority ] as $index => $entry ) {
		if ( $entry[0] === $callback ) {
			unset( Qevora_Test_Env::$filters[ $tag ][ $priority ][ $index ] );

			return true;
		}
	}

	return false;
}

/**
 * Removes an action.
 *
 * @param string   $tag      Tag.
 * @param callable $callback Callback.
 * @param int      $priority Priority.
 * @return bool
 */
function remove_action( $tag, $callback, $priority = 10 ) {
	return remove_filter( $tag, $callback, $priority );
}

/**
 * Whether a filter is registered.
 *
 * @param string $tag Tag.
 * @return bool
 */
function has_filter( $tag ) {
	return ! empty( Qevora_Test_Env::$filters[ $tag ] );
}

/**
 * Whether an action is registered.
 *
 * @param string $tag Tag.
 * @return bool
 */
function has_action( $tag ) {
	return has_filter( $tag );
}

/**
 * Applies filters.
 *
 * @param string $tag   Tag.
 * @param mixed  $value Value.
 * @return mixed
 */
function apply_filters( $tag, $value ) {
	$args = func_get_args();
	array_shift( $args );

	if ( empty( Qevora_Test_Env::$filters[ $tag ] ) ) {
		return $value;
	}

	foreach ( Qevora_Test_Env::$filters[ $tag ] as $callbacks ) {
		foreach ( $callbacks as $entry ) {
			list( $callback, $accepted ) = $entry;
			$call = array_slice( $args, 0, max( 1, $accepted ) );

			if ( is_string( $callback ) && false !== strpos( $callback, '::' ) ) {
				$callback = explode( '::', $callback );
			}

			$value    = call_user_func_array( $callback, $call );
			$args[0]  = $value;
		}
	}

	return $value;
}

/**
 * Applies filters by reference (WordPress uses the same implementation).
 *
 * @param string $tag   Tag.
 * @param mixed  $value Value.
 * @return mixed
 */
function apply_filters_ref_array( $tag, $args ) {
	return apply_filters_array( $tag, (array) $args );
}

/**
 * Applies filters with an explicit argument list.
 *
 * @param string       $tag  Tag.
 * @param array<int,mixed> $args Arguments.
 * @return mixed
 */
function apply_filters_array( $tag, array $args ) {
	$value = isset( $args[0] ) ? $args[0] : null;

	if ( empty( Qevora_Test_Env::$filters[ $tag ] ) ) {
		return $value;
	}

	foreach ( Qevora_Test_Env::$filters[ $tag ] as $callbacks ) {
		foreach ( $callbacks as $entry ) {
			list( $callback, $accepted ) = $entry;
			$value   = call_user_func_array( $callback, array_slice( $args, 0, max( 1, $accepted ) ) );
			$args[0] = $value;
		}
	}

	return $value;
}

/**
 * Runs an action.
 *
 * @param string $tag Tag.
 * @return void
 */
function do_action( $tag ) {
	Qevora_Test_Env::$did_actions[ (string) $tag ] = isset( Qevora_Test_Env::$did_actions[ (string) $tag ] )
		? Qevora_Test_Env::$did_actions[ (string) $tag ] + 1
		: 1;

	// WordPress strips the tag before handing the arguments to the callbacks.
	$args = func_get_args();
	array_shift( $args );

	apply_filters_array( (string) $tag, $args );
}

/**
 * Runs an action with an argument array.
 *
 * @param string       $tag  Tag.
 * @param array<int,mixed> $args Arguments.
 * @return void
 */
function do_action_ref_array( $tag, $args ) {
	Qevora_Test_Env::$did_actions[ (string) $tag ] = 1;

	apply_filters_array( (string) $tag, (array) $args );
}

/**
 * How many times an action ran.
 *
 * @param string $tag Tag.
 * @return int
 */
function did_action( $tag ) {
	return isset( Qevora_Test_Env::$did_actions[ (string) $tag ] ) ? (int) Qevora_Test_Env::$did_actions[ (string) $tag ] : 0;
}

/**
 * Runs an action only when it was registered (alias used by some plugins).
 *
 * @param string $tag Tag.
 * @return void
 */
function doing_action( $tag ) {
	return did_action( $tag ) > 0;
}

/**
 * Runs a filter only when registered (alias).
 *
 * @param string $tag Tag.
 * @return bool
 */
function doing_filter( $tag = null ) {
	unset( $tag );

	return false;
}

/* -------------------------------------------------------------------------
 * i18n
 * ---------------------------------------------------------------------- */

/**
 * Translates a string (returns it unchanged in tests).
 *
 * @param string $text   Text.
 * @param string $domain Domain.
 * @return string
 */
function __( $text, $domain = 'default' ) {
	unset( $domain );

	return (string) $text;
}

/**
 * Translates a string with context.
 *
 * @param string $text    Text.
 * @param string $context Context.
 * @param string $domain  Domain.
 * @return string
 */
function _x( $text, $context, $domain = 'default' ) {
	unset( $context, $domain );

	return (string) $text;
}

/**
 * Translates and escapes a string.
 *
 * @param string $text   Text.
 * @param string $domain Domain.
 * @return string
 */
function esc_html__( $text, $domain = 'default' ) {
	return esc_html( __( $text, $domain ) );
}

/**
 * Translates and escapes an attribute.
 *
 * @param string $text   Text.
 * @param string $domain Domain.
 * @return string
 */
function esc_attr__( $text, $domain = 'default' ) {
	return esc_attr( __( $text, $domain ) );
}

/**
 * Echoes a translated, escaped string.
 *
 * @param string $text   Text.
 * @param string $domain Domain.
 * @return void
 */
function esc_html_e( $text, $domain = 'default' ) {
	echo esc_html__( $text, $domain );
}

/**
 * Echoes a translated, escaped attribute.
 *
 * @param string $text   Text.
 * @param string $domain Domain.
 * @return void
 */
function esc_attr_e( $text, $domain = 'default' ) {
	echo esc_attr__( $text, $domain );
}

/**
 * Echoes a translated string.
 *
 * @param string $text   Text.
 * @param string $domain Domain.
 * @return void
 */
function _e( $text, $domain = 'default' ) {
	echo __( $text, $domain );
}

/**
 * Translates a singular/plural pair.
 *
 * @param string $single Singular.
 * @param string $plural Plural.
 * @param int    $number Number.
 * @param string $domain Domain.
 * @return string
 */
function _n( $single, $plural, $number, $domain = 'default' ) {
	unset( $domain );

	return 1 === (int) $number ? (string) $single : (string) $plural;
}

/**
 * Translates and escapes a singular/plural pair.
 *
 * @param string $single Singular.
 * @param string $plural Plural.
 * @param int    $number Number.
 * @param string $domain Domain.
 * @return string
 */
function esc_html_n( $single, $plural, $number, $domain = 'default' ) {
	return esc_html( _n( $single, $plural, $number, $domain ) );
}

/**
 * Translates a singular/plural pair with context.
 *
 * @param string $single  Singular.
 * @param string $plural  Plural.
 * @param int    $number  Number.
 * @param string $context Context.
 * @param string $domain  Domain.
 * @return string
 */
function _nx( $single, $plural, $number, $context, $domain = 'default' ) {
	unset( $context );

	return _n( $single, $plural, $number, $domain );
}

/**
 * Returns the locale.
 *
 * @return string
 */
function get_locale() {
	return 'en_US';
}

/**
 * Translates a role name.
 *
 * @param string $name Name.
 * @return string
 */
function translate_user_role( $name ) {
	return (string) $name;
}

/**
 * Loads a text domain (no-op).
 *
 * @param string $domain Domain.
 * @param string $path   Path.
 * @return bool
 */
function load_plugin_textdomain( $domain, $path = '' ) {
	unset( $domain, $path );

	return true;
}

/* -------------------------------------------------------------------------
 * Escaping and sanitizing
 * ---------------------------------------------------------------------- */

/**
 * Escapes HTML.
 *
 * @param string $text Text.
 * @return string
 */
function esc_html( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}

/**
 * Escapes an attribute.
 *
 * @param string $text Text.
 * @return string
 */
function esc_attr( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}

/**
 * Escapes textarea content.
 *
 * @param string $text Text.
 * @return string
 */
function esc_textarea( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}

/**
 * Escapes a URL.
 *
 * @param string $url       URL.
 * @param array  $protocols Protocols.
 * @return string
 */
function esc_url( $url, $protocols = null ) {
	unset( $protocols );

	return (string) $url;
}

/**
 * Escapes a URL for storage.
 *
 * @param string $url       URL.
 * @param array  $protocols Protocols.
 * @return string
 */
function esc_url_raw( $url, $protocols = null ) {
	$url = trim( (string) $url );

	if ( '' === $url ) {
		return '';
	}

	$allowed = is_array( $protocols ) && ! empty( $protocols ) ? $protocols : array( 'http', 'https' );
	$parsed  = parse_url( $url );

	if ( false === $parsed || empty( $parsed['scheme'] ) ) {
		return '';
	}

	if ( ! in_array( strtolower( (string) $parsed['scheme'] ), $allowed, true ) ) {
		return '';
	}

	return str_replace( array( '"', "'", '<', '>', '\\', "\n", "\r", "\t" ), '', $url );
}

/**
 * Filters content with the post allow list.
 *
 * The real implementation is `wp_kses()`. For the tests the important part is
 * that the plugin does not *rely* on it for dangerous markup: those cases are
 * rejected earlier by `Template_Validator::find_forbidden_patterns()`. This stub
 * therefore only removes `<script>` and `<iframe>` elements so tests can assert
 * the plugin's own behaviour.
 *
 * @param string $content Content.
 * @return string
 */
function wp_kses_post( $content ) {
	$content = (string) $content;
	$content = preg_replace( '#<\s*script[^>]*>.*?<\s*/\s*script\s*>#is', '', $content );
	$content = preg_replace( '#<\s*iframe[^>]*>.*?<\s*/\s*iframe\s*>#is', '', $content );

	return (string) $content;
}

/**
 * Filters content with an explicit allow list.
 *
 * @param string $content Content.
 * @param array  $allowed Allowed HTML.
 * @return string
 */
function wp_kses( $content, $allowed ) {
	unset( $allowed );

	return wp_kses_post( $content );
}

/**
 * Sanitizes a text field.
 *
 * @param string $value Value.
 * @return string
 */
function sanitize_text_field( $value ) {
	if ( is_array( $value ) || is_object( $value ) ) {
		return '';
	}

	$value = (string) $value;
	$value = wp_strip_all_tags( $value );
	$value = str_replace( array( "\n", "\r", "\t", '%00' ), ' ', $value );
	$value = preg_replace( '/\s+/', ' ', $value );

	return trim( $value );
}

/**
 * Sanitizes a textarea field.
 *
 * @param string $value Value.
 * @return string
 */
function sanitize_textarea_field( $value ) {
	if ( is_array( $value ) || is_object( $value ) ) {
		return '';
	}

	$value = (string) $value;
	$value = wp_strip_all_tags( $value );
	$value = str_replace( array( "\r\n", "\r" ), "\n", $value );
	$value = preg_replace( "/\n{3,}/", "\n\n", $value );

	return trim( $value );
}

/**
 * Sanitizes a key.
 *
 * @param string $value Value.
 * @return string
 */
function sanitize_key( $value ) {
	if ( is_array( $value ) || is_object( $value ) ) {
		return '';
	}

	$value = strtolower( (string) $value );

	return (string) preg_replace( '/[^a-z0-9_\-]/', '', $value );
}

/**
 * Sanitizes a title.
 *
 * @param string $value Value.
 * @return string
 */
function sanitize_title( $value ) {
	$value = strtolower( trim( (string) $value ) );
	$value = preg_replace( '/[^a-z0-9_\-]+/', '-', $value );

	return trim( (string) $value, '-' );
}

/**
 * Sanitizes an HTML class.
 *
 * @param string $class Class.
 * @return string
 */
function sanitize_html_class( $class ) {
	return (string) preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $class );
}

/**
 * Sanitizes a file name.
 *
 * @param string $name Name.
 * @return string
 */
function sanitize_file_name( $name ) {
	return preg_replace( '#[^A-Za-z0-9_\-.]#', '', (string) $name );
}

/**
 * Strips all tags.
 *
 * @param string $text           Text.
 * @param bool   $remove_breaks  Remove line breaks.
 * @return string
 */
function wp_strip_all_tags( $text, $remove_breaks = false ) {
	$text = preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', (string) $text );
	$text = strip_tags( (string) $text );

	if ( $remove_breaks ) {
		$text = preg_replace( '/[\r\n\t ]+/', ' ', $text );
	}

	return trim( (string) $text );
}

/**
 * Converts a value to a positive integer.
 *
 * @param mixed $value Value.
 * @return int
 */
function absint( $value ) {
	return abs( (int) $value );
}

/**
 * Merges arguments over defaults.
 *
 * @param mixed $args     Arguments.
 * @param mixed $defaults Defaults.
 * @return array<string,mixed>
 */
function wp_parse_args( $args, $defaults = array() ) {
	if ( is_object( $args ) ) {
		$args = get_object_vars( $args );
	}

	if ( ! is_array( $args ) ) {
		parse_str( (string) $args, $parsed );
		$args = $parsed;
	}

	return array_merge( (array) $defaults, (array) $args );
}

/**
 * Adds slashes recursively.
 *
 * @param mixed $value Value.
 * @return mixed
 */
function wp_slash( $value ) {
	if ( is_array( $value ) ) {
		return array_map( 'wp_slash', $value );
	}

	return addslashes( (string) $value );
}

/**
 * Removes slashes recursively.
 *
 * @param mixed $value Value.
 * @return mixed
 */
function wp_unslash( $value ) {
	if ( is_array( $value ) ) {
		return array_map( 'wp_unslash', $value );
	}

	return stripslashes( (string) $value );
}

/**
 * Encodes JSON the WordPress way.
 *
 * @param mixed $data Data.
 * @param int   $options Options.
 * @param int   $depth Depth.
 * @return string|false
 */
function wp_json_encode( $data, $options = 0, $depth = 512 ) {
	return json_encode( $data, $options, $depth );
}

/**
 * Whether a value is a WP_Error.
 *
 * @param mixed $thing Thing.
 * @return bool
 */
function is_wp_error( $thing ) {
	return $thing instanceof WP_Error;
}

/**
 * Plucks one field from a list of objects or arrays.
 *
 * @param array<int,mixed> $list  List.
 * @param string|int       $field Field.
 * @param string|int|null  $index_key Index key.
 * @return array<int,mixed>
 */
function wp_list_pluck( $list, $field, $index_key = null ) {
	$out = array();

	foreach ( (array) $list as $item ) {
		$value = null;

		if ( is_object( $item ) && isset( $item->$field ) ) {
			$value = $item->$field;
		} elseif ( is_array( $item ) && isset( $item[ $field ] ) ) {
			$value = $item[ $field ];
		}

		if ( null === $index_key ) {
			$out[] = $value;
			continue;
		}

		$key = null;

		if ( is_object( $item ) && isset( $item->$index_key ) ) {
			$key = $item->$index_key;
		} elseif ( is_array( $item ) && isset( $item[ $index_key ] ) ) {
			$key = $item[ $index_key ];
		}

		$out[ $key ] = $value;
	}

	return $out;
}

/**
 * Parses a URL.
 *
 * @param string $url       URL.
 * @param int    $component Component.
 * @return mixed
 */
function wp_parse_url( $url, $component = -1 ) {
	return parse_url( (string) $url, $component );
}

/**
 * Adds or replaces query arguments.
 *
 * @param mixed ...$args Arguments.
 * @return string
 */
function add_query_arg( ...$args ) {
	if ( 2 === count( $args ) && is_array( $args[0] ) ) {
		list( $params, $url ) = $args;
	} elseif ( 3 === count( $args ) ) {
		$params = array( $args[0] => $args[1] );
		$url    = $args[2];
	} else {
		return '';
	}

	$url    = (string) $url;
	$parts  = explode( '?', $url, 2 );
	$query  = array();

	if ( isset( $parts[1] ) && '' !== $parts[1] ) {
		parse_str( $parts[1], $query );
	}

	$query = array_merge( $query, (array) $params );

	return $parts[0] . '?' . http_build_query( $query );
}

/* -------------------------------------------------------------------------
 * Options and transients
 * ---------------------------------------------------------------------- */

/**
 * Reads an option.
 *
 * @param string $name    Name.
 * @param mixed  $default Default.
 * @return mixed
 */
function get_option( $name, $default = false ) {
	$name = (string) $name;

	return array_key_exists( $name, Qevora_Test_Env::$options ) ? Qevora_Test_Env::$options[ $name ] : $default;
}

/**
 * Writes an option.
 *
 * @param string $name     Name.
 * @param mixed  $value    Value.
 * @param string $autoload Autoload flag.
 * @return bool
 */
function update_option( $name, $value, $autoload = null ) {
	unset( $autoload );

	Qevora_Test_Env::$options[ (string) $name ] = $value;

	return true;
}

/**
 * Adds an option when it does not exist.
 *
 * @param string $name     Name.
 * @param mixed  $value    Value.
 * @param string $deprecated Deprecated.
 * @param string $autoload Autoload flag.
 * @return bool
 */
function add_option( $name, $value = '', $deprecated = '', $autoload = 'yes' ) {
	unset( $deprecated, $autoload );

	if ( array_key_exists( (string) $name, Qevora_Test_Env::$options ) ) {
		return false;
	}

	Qevora_Test_Env::$options[ (string) $name ] = $value;

	return true;
}

/**
 * Deletes an option.
 *
 * @param string $name Name.
 * @return bool
 */
function delete_option( $name ) {
	unset( Qevora_Test_Env::$options[ (string) $name ] );

	return true;
}

/**
 * Reads a transient.
 *
 * @param string $name Name.
 * @return mixed
 */
function get_transient( $name ) {
	$entry = isset( Qevora_Test_Env::$transients[ (string) $name ] ) ? Qevora_Test_Env::$transients[ (string) $name ] : null;

	if ( null === $entry ) {
		return false;
	}

	if ( $entry['expires'] > 0 && $entry['expires'] < time() ) {
		unset( Qevora_Test_Env::$transients[ (string) $name ] );

		return false;
	}

	return $entry['value'];
}

/**
 * Writes a transient.
 *
 * @param string $name    Name.
 * @param mixed  $value   Value.
 * @param int    $expires Lifetime.
 * @return bool
 */
function set_transient( $name, $value, $expires = 0 ) {
	Qevora_Test_Env::$transients[ (string) $name ] = array(
		'value'   => $value,
		'expires' => $expires > 0 ? time() + (int) $expires : 0,
	);

	return true;
}

/**
 * Deletes a transient.
 *
 * @param string $name Name.
 * @return bool
 */
function delete_transient( $name ) {
	unset( Qevora_Test_Env::$transients[ (string) $name ] );

	return true;
}

/**
 * Reads from the object cache.
 *
 * @param string $key    Key.
 * @param string $group  Group.
 * @param bool   $force  Force.
 * @param mixed  $found  Found flag (by reference).
 * @return mixed
 */
function wp_cache_get( $key, $group = '', $force = false, &$found = null ) {
	unset( $force );

	$group = (string) $group;
	$key   = (string) $key;

	if ( isset( Qevora_Test_Env::$cache[ $group ][ $key ] ) ) {
		$found = true;

		return Qevora_Test_Env::$cache[ $group ][ $key ];
	}

	$found = false;

	return false;
}

/**
 * Writes to the object cache.
 *
 * @param string $key    Key.
 * @param mixed  $value  Value.
 * @param string $group  Group.
 * @param int    $expire Expiry.
 * @return bool
 */
function wp_cache_set( $key, $value, $group = '', $expire = 0 ) {
	$group = (string) $group;
	$key   = (string) $key;

	Qevora_Test_Env::$cache[ $group ][ $key ]     = $value;
	Qevora_Test_Env::$cache_ttl[ $group ][ $key ] = (int) $expire;

	return true;
}

/**
 * Deletes from the object cache.
 *
 * @param string $key   Key.
 * @param string $group Group.
 * @return bool
 */
function wp_cache_delete( $key, $group = '' ) {
	unset( Qevora_Test_Env::$cache[ (string) $group ][ (string) $key ] );
	unset( Qevora_Test_Env::$cache_ttl[ (string) $group ][ (string) $key ] );

	return true;
}

/**
 * Flushes the object cache.
 *
 * @return bool
 */
function wp_cache_flush() {
	Qevora_Test_Env::$cache     = array();
	Qevora_Test_Env::$cache_ttl = array();

	return true;
}

/**
 * Whether an external object cache is in use.
 *
 * @return bool
 */
function wp_using_ext_object_cache() {
	return false;
}

/* -------------------------------------------------------------------------
 * Posts and meta
 * ---------------------------------------------------------------------- */

/**
 * Returns a post.
 *
 * @param mixed $post Post or ID.
 * @return WP_Post|null
 */
function get_post( $post = null ) {
	if ( $post instanceof WP_Post ) {
		return $post;
	}

	if ( is_object( $post ) && isset( $post->ID ) ) {
		$post = $post->ID;
	}

	$id = (int) $post;

	return isset( Qevora_Test_Env::$posts[ $id ] ) ? Qevora_Test_Env::$posts[ $id ] : null;
}

/**
 * Queries posts.
 *
 * @param array<string,mixed> $args Arguments.
 * @return array<int,mixed>
 */
function get_posts( array $args = array() ) {
	$query = new WP_Query( $args );

	return $query->posts;
}

/**
 * Inserts a post.
 *
 * @param array<string,mixed> $args      Arguments.
 * @param bool                $wp_error  Return WP_Error.
 * @return int|WP_Error
 */
function wp_insert_post( array $args, $wp_error = false ) {
	if ( empty( $args['post_type'] ) ) {
		$args['post_type'] = 'post';
	}

	if ( empty( $args['post_status'] ) ) {
		$args['post_status'] = 'draft';
	}

	if ( '' === trim( (string) ( isset( $args['post_title'] ) ? $args['post_title'] : '' ) ) && 'auto-draft' !== $args['post_status'] ) {
		$args['post_title'] = '(no title)';
	}

	$id = Qevora_Test_Env::add_post( $args );

	if ( isset( $args['meta_input'] ) && is_array( $args['meta_input'] ) ) {
		foreach ( $args['meta_input'] as $key => $value ) {
			update_post_meta( $id, (string) $key, $value );
		}
	}

	return $id;
}

/**
 * Updates a post.
 *
 * @param array<string,mixed> $args     Arguments.
 * @param bool                $wp_error Return WP_Error.
 * @return int|WP_Error
 */
function wp_update_post( array $args, $wp_error = false ) {
	$id = isset( $args['ID'] ) ? (int) $args['ID'] : 0;
	$post = get_post( $id );

	if ( ! $post instanceof WP_Post ) {
		return $wp_error ? new WP_Error( 'invalid_post', 'Invalid post ID.' ) : 0;
	}

	foreach ( $args as $key => $value ) {
		if ( property_exists( $post, $key ) ) {
			$post->$key = $value;
		}
	}

	return $id;
}

/**
 * Moves a post to the trash.
 *
 * @param int $id Post ID.
 * @return WP_Post|false|null Null when the post does not exist.
 */
function wp_trash_post( $id = 0 ) {
	$id   = (int) $id;
	$post = get_post( $id );

	if ( ! $post instanceof WP_Post ) {
		return null;
	}

	if ( 'trash' === $post->post_status ) {
		return false;
	}

	update_post_meta( $id, '_wp_trash_meta_status', $post->post_status );
	update_post_meta( $id, '_wp_trash_meta_time', (string) time() );

	$post->post_status = 'trash';

	return $post;
}

/**
 * Restores a post from the trash.
 *
 * @param int $id Post ID.
 * @return WP_Post|false|null
 */
function wp_untrash_post( $id = 0 ) {
	$id   = (int) $id;
	$post = get_post( $id );

	if ( ! $post instanceof WP_Post || 'trash' !== $post->post_status ) {
		return $post instanceof WP_Post ? $post : null;
	}

	$previous = get_post_meta( $id, '_wp_trash_meta_status', true );

	$post->post_status = $previous ? (string) $previous : 'publish';

	delete_post_meta( $id, '_wp_trash_meta_status' );
	delete_post_meta( $id, '_wp_trash_meta_time' );

	return $post;
}

/**
 * Deletes or trashes a post.
 *
 * @param int  $id         Post ID.
 * @param bool $force_delete Delete permanently.
 * @return WP_Post|null
 */
function wp_delete_post( $id, $force_delete = false ) {
	$id   = (int) $id;
	$post = get_post( $id );

	if ( ! $post instanceof WP_Post ) {
		return null;
	}

	if ( ! $force_delete ) {
		$post->post_status = 'trash';

		return $post;
	}

	unset( Qevora_Test_Env::$posts[ $id ], Qevora_Test_Env::$meta[ $id ] );

	return $post;
}

/**
 * Returns a post title.
 *
 * @param mixed $post Post or ID.
 * @return string
 */
function get_the_title( $post = 0 ) {
	$post = get_post( $post );

	return $post instanceof WP_Post ? (string) $post->post_title : '';
}

/**
 * Returns a post status.
 *
 * @param mixed $post Post or ID.
 * @return string|false
 */
function get_post_status( $post = null ) {
	$post = get_post( $post );

	return $post instanceof WP_Post ? (string) $post->post_status : false;
}

/**
 * Returns a post type.
 *
 * @param mixed $post Post or ID.
 * @return string|false
 */
function get_post_type( $post = null ) {
	$post = get_post( $post );

	return $post instanceof WP_Post ? (string) $post->post_type : false;
}

/**
 * Returns an edit link.
 *
 * @param int    $id      Post ID.
 * @param string $context Context.
 * @return string
 */
function get_edit_post_link( $id = 0, $context = 'display' ) {
	unset( $context );

	return 'https://example.org/wp-admin/post.php?post=' . (int) $id . '&action=edit';
}

/**
 * Whether a post type exists.
 *
 * @param string $post_type Post type.
 * @return bool
 */
function post_type_exists( $post_type ) {
	return in_array( (string) $post_type, Qevora_Test_Env::$post_types, true );
}

/**
 * Whether a taxonomy exists.
 *
 * @param string $taxonomy Taxonomy.
 * @return bool
 */
function taxonomy_exists( $taxonomy ) {
	return in_array( (string) $taxonomy, Qevora_Test_Env::$taxonomies, true );
}

/**
 * Returns post types.
 *
 * @param array<string,mixed> $args   Arguments.
 * @param string              $output Output type.
 * @return array<string,mixed>
 */
function get_post_types( $args = array(), $output = 'names' ) {
	unset( $args );

	$out = array();

	foreach ( Qevora_Test_Env::$post_types as $slug ) {
		if ( 'names' === $output ) {
			$out[ $slug ] = $slug;
			continue;
		}

		$type              = new stdClass();
		$type->name        = $slug;
		$type->labels      = new stdClass();
		$type->labels->name = ucfirst( $slug );
		$type->labels->singular_name = ucfirst( $slug );
		$out[ $slug ]      = $type;
	}

	return $out;
}

/**
 * Returns taxonomies.
 *
 * @param array<string,mixed> $args   Arguments.
 * @param string              $output Output type.
 * @return array<string,mixed>
 */
function get_taxonomies( $args = array(), $output = 'names' ) {
	unset( $args );

	$out = array();

	foreach ( Qevora_Test_Env::$taxonomies as $slug ) {
		if ( 'names' === $output ) {
			$out[ $slug ] = $slug;
			continue;
		}

		$taxonomy         = new stdClass();
		$taxonomy->name   = $slug;
		$taxonomy->labels = new stdClass();
		$taxonomy->labels->name = ucfirst( $slug );
		$out[ $slug ]     = $taxonomy;
	}

	return $out;
}

/**
 * Returns terms.
 *
 * @param array<string,mixed> $args Arguments.
 * @return array<int,WP_Term>|WP_Error
 */
function get_terms( $args = array() ) {
	$args   = is_array( $args ) ? $args : array();
	$search = isset( $args['search'] ) ? strtolower( (string) $args['search'] ) : '';
	$out    = array();

	foreach ( Qevora_Test_Env::$terms as $term ) {
		if ( '' !== $search && false === strpos( strtolower( $term->name ), $search ) ) {
			continue;
		}

		$out[] = $term;
	}

	return $out;
}

/**
 * Reads post meta.
 *
 * @param int    $post_id Post ID.
 * @param string $key     Meta key.
 * @param bool   $single  Single value.
 * @return mixed
 */
function get_post_meta( $post_id, $key = '', $single = false ) {
	$post_id = (int) $post_id;
	$key     = (string) $key;

	if ( '' === $key ) {
		return isset( Qevora_Test_Env::$meta[ $post_id ] ) ? Qevora_Test_Env::$meta[ $post_id ] : array();
	}

	if ( ! isset( Qevora_Test_Env::$meta[ $post_id ][ $key ] ) ) {
		return $single ? '' : array();
	}

	$value = Qevora_Test_Env::$meta[ $post_id ][ $key ];

	return $single ? $value : array( $value );
}

/**
 * Writes post meta.
 *
 * @param int    $post_id Post ID.
 * @param string $key     Meta key.
 * @param mixed  $value   Value.
 * @return bool
 */
function update_post_meta( $post_id, $key, $value ) {
	Qevora_Test_Env::$meta[ (int) $post_id ][ (string) $key ] = $value;

	return true;
}

/**
 * Adds post meta.
 *
 * @param int    $post_id Post ID.
 * @param string $key     Meta key.
 * @param mixed  $value   Value.
 * @return bool
 */
function add_post_meta( $post_id, $key, $value ) {
	return update_post_meta( $post_id, $key, $value );
}

/**
 * Deletes post meta.
 *
 * @param int    $post_id Post ID.
 * @param string $key     Meta key.
 * @return bool
 */
function delete_post_meta( $post_id, $key ) {
	unset( Qevora_Test_Env::$meta[ (int) $post_id ][ (string) $key ] );

	return true;
}

/**
 * Deletes one meta key everywhere.
 *
 * @param string $key Meta key.
 * @return bool
 */
function delete_post_meta_by_key( $key ) {
	$key = (string) $key;

	foreach ( Qevora_Test_Env::$meta as $post_id => $values ) {
		unset( Qevora_Test_Env::$meta[ $post_id ][ $key ] );
	}

	return true;
}

/**
 * Registers meta (no-op in tests).
 *
 * @param string              $type Object type.
 * @param string              $subtype Subtype.
 * @param string              $key  Meta key.
 * @param array<string,mixed> $args Arguments.
 * @return bool
 */
function register_meta( $type, $subtype, $key, $args = array() ) {
	unset( $type, $subtype, $key, $args );

	return true;
}

/**
 * Registers a post meta key (no-op in tests).
 *
 * @param string              $post_type Post type.
 * @param string              $key       Meta key.
 * @param array<string,mixed> $args      Arguments.
 * @return bool
 */
function register_post_meta( $post_type, $key, $args = array() ) {
	unset( $post_type, $key, $args );

	return true;
}

/* -------------------------------------------------------------------------
 * Users and capabilities
 * ---------------------------------------------------------------------- */

/**
 * Whether the current user has a capability.
 *
 * @param string $capability Capability.
 * @param mixed  ...$args    Extra arguments.
 * @return bool
 */
function current_user_can( $capability, ...$args ) {
	unset( $args );

	return ! empty( Qevora_Test_Env::$caps[ (string) $capability ] );
}

/**
 * Whether a user has a capability.
 *
 * @param mixed  $user       User.
 * @param string $capability Capability.
 * @return bool
 */
function user_can( $user, $capability ) {
	unset( $user );

	return current_user_can( $capability );
}

/**
 * Returns the current user ID.
 *
 * @return int
 */
function get_current_user_id() {
	return (int) Qevora_Test_Env::$user_id;
}

/**
 * Returns the current user.
 *
 * @return WP_User
 */
function wp_get_current_user() {
	foreach ( Qevora_Test_Env::$users as $user ) {
		if ( (int) $user->ID === (int) Qevora_Test_Env::$user_id ) {
			return $user;
		}
	}

	return new WP_User(
		array(
			'ID'           => (int) Qevora_Test_Env::$user_id,
			'display_name' => 'Test user',
			'roles'        => array( 'administrator' ),
		)
	);
}

/**
 * Returns users.
 *
 * @param array<string,mixed> $args Arguments.
 * @return array<int,WP_User>
 */
function get_users( $args = array() ) {
	unset( $args );

	return Qevora_Test_Env::$users;
}

/**
 * Returns roles.
 *
 * @return object
 */
function wp_roles() {
	$roles         = new stdClass();
	$roles->roles  = array(
		'administrator' => array( 'name' => 'Administrator' ),
		'editor'        => array( 'name' => 'Editor' ),
		'author'        => array( 'name' => 'Author' ),
		'subscriber'    => array( 'name' => 'Subscriber' ),
	);

	return $roles;
}

/**
 * Whether a visitor is logged in.
 *
 * @return bool
 */
function is_user_logged_in() {
	return (bool) Qevora_Test_Env::is( 'logged_in', false );
}

/* -------------------------------------------------------------------------
 * Conditionals
 * ---------------------------------------------------------------------- */

/**
 * Whether the request is an admin request.
 *
 * @return bool
 */
function is_admin() {
	return (bool) Qevora_Test_Env::is( 'admin', false );
}

/**
 * Whether the front page is requested.
 *
 * @return bool
 */
function is_front_page() {
	return (bool) Qevora_Test_Env::is( 'front_page', false );
}

/**
 * Whether the posts page is requested.
 *
 * @return bool
 */
function is_home() {
	return (bool) Qevora_Test_Env::is( 'home', false );
}

/**
 * Whether a singular view is requested.
 *
 * @param mixed $post_types Post types.
 * @return bool
 */
function is_singular( $post_types = '' ) {
	if ( is_array( $post_types ) && ! empty( $post_types ) ) {
		$current = Qevora_Test_Env::is( 'singular_post_type', '' );

		return in_array( (string) $current, array_map( 'strval', $post_types ), true );
	}

	return (bool) Qevora_Test_Env::is( 'singular', false );
}

/**
 * Whether a single post is requested.
 *
 * @param mixed $post Post.
 * @return bool
 */
function is_single( $post = '' ) {
	unset( $post );

	return (bool) Qevora_Test_Env::is( 'single', false );
}

/**
 * Whether a page is requested.
 *
 * @param mixed $page Page.
 * @return bool
 */
function is_page( $page = '' ) {
	unset( $page );

	return (bool) Qevora_Test_Env::is( 'page', false );
}

/**
 * Whether an archive is requested.
 *
 * @return bool
 */
function is_archive() {
	return (bool) Qevora_Test_Env::is( 'archive', false );
}

/**
 * Whether a post type archive is requested.
 *
 * @param mixed $post_types Post types.
 * @return bool
 */
function is_post_type_archive( $post_types = '' ) {
	unset( $post_types );

	return (bool) Qevora_Test_Env::is( 'post_type_archive', false );
}

/**
 * Whether a taxonomy archive is requested.
 *
 * @param mixed $taxonomy Taxonomy.
 * @param mixed $term     Term.
 * @return bool
 */
function is_tax( $taxonomy = '', $term = '' ) {
	unset( $taxonomy, $term );

	return (bool) Qevora_Test_Env::is( 'tax', false );
}

/**
 * Whether a category archive is requested.
 *
 * @param mixed $category Category.
 * @return bool
 */
function is_category( $category = '' ) {
	unset( $category );

	return (bool) Qevora_Test_Env::is( 'category', false );
}

/**
 * Whether a tag archive is requested.
 *
 * @param mixed $tag Tag.
 * @return bool
 */
function is_tag( $tag = '' ) {
	unset( $tag );

	return (bool) Qevora_Test_Env::is( 'tag', false );
}

/**
 * Whether an author archive is requested.
 *
 * @param mixed $author Author.
 * @return bool
 */
function is_author( $author = '' ) {
	unset( $author );

	return (bool) Qevora_Test_Env::is( 'author', false );
}

/**
 * Whether a date archive is requested.
 *
 * @return bool
 */
function is_date() {
	return (bool) Qevora_Test_Env::is( 'date', false );
}

/**
 * Whether search results are requested.
 *
 * @return bool
 */
function is_search() {
	return (bool) Qevora_Test_Env::is( 'search', false );
}

/**
 * Whether a 404 is requested.
 *
 * @return bool
 */
function is_404() {
	return (bool) Qevora_Test_Env::is( 'is_404', false );
}

/**
 * Whether an attachment is requested.
 *
 * @param mixed $post_types Post types.
 * @return bool
 */
function is_attachment( $post_types = '' ) {
	unset( $post_types );

	return (bool) Qevora_Test_Env::is( 'attachment', false );
}

/**
 * Whether a preview is requested.
 *
 * @param mixed $post Post.
 * @return bool
 */
function is_preview( $post = null ) {
	unset( $post );

	return (bool) Qevora_Test_Env::is( 'preview', false );
}

/**
 * Whether the site runs multisite.
 *
 * @return bool
 */
function is_multisite() {
	return (bool) Qevora_Test_Env::is( 'multisite', false );
}

/**
 * Whether the active theme is a block theme.
 *
 * @return bool
 */
function wp_is_block_theme() {
	return (bool) Qevora_Test_Env::$block_theme;
}

/**
 * Whether a plugin is active.
 *
 * @param string $plugin Plugin basename.
 * @return bool
 */
function is_plugin_active( $plugin ) {
	return in_array( (string) $plugin, Qevora_Test_Env::$active_plugins, true );
}

/**
 * Returns the queried object.
 *
 * @return object|null
 */
function get_queried_object() {
	return Qevora_Test_Env::is( 'queried_object', null );
}

/**
 * Returns the queried object ID.
 *
 * @return int
 */
function get_queried_object_id() {
	return (int) Qevora_Test_Env::is( 'queried_object_id', 0 );
}

/* -------------------------------------------------------------------------
 * URLs, themes and misc
 * ---------------------------------------------------------------------- */

/**
 * Returns the home URL.
 *
 * @param string $path Path.
 * @return string
 */
function home_url( $path = '' ) {
	return 'https://example.org' . ( '' !== $path ? '/' . ltrim( (string) $path, '/' ) : '' );
}

/**
 * Returns the site URL.
 *
 * @param string $path Path.
 * @return string
 */
function site_url( $path = '' ) {
	return home_url( $path );
}

/**
 * Returns an admin URL.
 *
 * @param string $path Path.
 * @return string
 */
function admin_url( $path = '' ) {
	return 'https://example.org/wp-admin/' . ltrim( (string) $path, '/' );
}

/**
 * Returns a REST URL.
 *
 * @param string $path Path.
 * @return string
 */
function rest_url( $path = '' ) {
	return 'https://example.org/wp-json/' . ltrim( (string) $path, '/' );
}

/**
 * Returns the plugins URL.
 *
 * @param string $path Path.
 * @return string
 */
function plugins_url( $path = '' ) {
	return 'https://example.org/wp-content/plugins/' . ltrim( (string) $path, '/' );
}

/**
 * Returns a plugin directory path with a trailing slash.
 *
 * @param string $file File.
 * @return string
 */
function plugin_dir_path( $file ) {
	return trailingslashit( dirname( (string) $file ) );
}

/**
 * Returns a plugin directory URL with a trailing slash.
 *
 * @param string $file File.
 * @return string
 */
function plugin_dir_url( $file ) {
	return 'https://example.org/wp-content/plugins/' . basename( dirname( (string) $file ) ) . '/';
}

/**
 * Returns a plugin basename.
 *
 * @param string $file File.
 * @return string
 */
function plugin_basename( $file ) {
	return basename( dirname( (string) $file ) ) . '/' . basename( (string) $file );
}

/**
 * Adds a trailing slash.
 *
 * @param string $value Value.
 * @return string
 */
function trailingslashit( $value ) {
	return rtrim( (string) $value, '/\\' ) . '/';
}

/**
 * Removes a trailing slash.
 *
 * @param string $value Value.
 * @return string
 */
function untrailingslashit( $value ) {
	return rtrim( (string) $value, '/\\' );
}

/**
 * Returns the theme.
 *
 * @param string $stylesheet Stylesheet.
 * @return Qevora_Test_Theme
 */
function wp_get_theme( $stylesheet = '' ) {
	unset( $stylesheet );

	return new Qevora_Test_Theme();
}

/**
 * Theme stub.
 */
class Qevora_Test_Theme {

	/**
	 * Returns a header value.
	 *
	 * @param string $key Key.
	 * @return string
	 */
	public function get( $key ) {
		if ( 'Name' === $key ) {
			return 'Test Theme';
		}

		if ( 'Version' === $key ) {
			return '1.0.0';
		}

		return '';
	}

	/**
	 * Returns the parent theme.
	 *
	 * @return null
	 */
	public function parent() {
		return null;
	}

	/**
	 * Returns the stylesheet slug.
	 *
	 * @return string
	 */
	public function get_stylesheet() {
		return 'test-theme';
	}

	/**
	 * Returns the template directory.
	 *
	 * @return string
	 */
	public function get_template_directory() {
		return '/srv/www/wp-content/themes/test-theme';
	}

	/**
	 * Returns the stylesheet directory.
	 *
	 * @return string
	 */
	public function get_stylesheet_directory() {
		return '/srv/www/wp-content/themes/test-theme';
	}
}

/**
 * Returns a blog info value.
 *
 * @param string $show Key.
 * @return string
 */
function get_bloginfo( $show = '' ) {
	if ( 'version' === $show ) {
		return '7.1.4';
	}

	return 'Test Site';
}

/**
 * Returns block templates (none in tests).
 *
 * @param array<string,mixed> $args Arguments.
 * @return array<int,mixed>
 */
function get_block_templates( $args = array() ) {
	unset( $args );

	return array();
}

/**
 * Returns the sites of a network.
 *
 * @param array<string,mixed> $args Arguments.
 * @return array<int,int>
 */
function get_sites( $args = array() ) {
	unset( $args );

	return array( 1 );
}

/**
 * Switches to another site (no-op).
 *
 * @param int $site_id Site ID.
 * @return bool
 */
function switch_to_blog( $site_id ) {
	unset( $site_id );

	return true;
}

/**
 * Restores the current site (no-op).
 *
 * @return bool
 */
function restore_current_blog() {
	return true;
}

/**
 * Creates a nonce.
 *
 * @param string $action Action.
 * @return string
 */
function wp_create_nonce( $action = '' ) {
	return 'nonce_' . md5( (string) $action );
}

/**
 * Verifies a nonce.
 *
 * @param string $nonce  Nonce.
 * @param string $action Action.
 * @return bool
 */
function wp_verify_nonce( $nonce, $action = '' ) {
	return hash_equals( wp_create_nonce( $action ), (string) $nonce );
}

/**
 * Adds a nonce to a URL.
 *
 * @param string $url    URL.
 * @param string $action Action.
 * @return string
 */
function wp_nonce_url( $url, $action = '' ) {
	return add_query_arg( '_wpnonce', wp_create_nonce( $action ), (string) $url );
}

/**
 * Prints a nonce field (no-op).
 *
 * @param string $action Action.
 * @param string $name   Field name.
 * @return string
 */
function wp_nonce_field( $action = '', $name = '_wpnonce' ) {
	return '<input type="hidden" name="' . esc_attr( $name ) . '" value="' . esc_attr( wp_create_nonce( $action ) ) . '" />';
}

/**
 * Checks an AJAX nonce.
 *
 * @param string $action      Action.
 * @param string $query_arg   Argument.
 * @param bool   $stop        Stop on failure.
 * @return bool|int
 */
function check_ajax_referer( $action = '', $query_arg = false, $stop = true ) {
	unset( $action, $query_arg, $stop );

	return 1;
}

/**
 * Records a misuse warning.
 *
 * @param string $function Function.
 * @param string $message  Message.
 * @return void
 */
function _doing_it_wrong( $function, $message ) {
	Qevora_Test_Env::$doing_it_wrong[] = $function . ': ' . $message;
}

/**
 * Stops execution.
 *
 * @param mixed $message Message.
 * @param mixed $title   Title.
 * @return void
 */
function wp_die( $message = '', $title = '' ) {
	unset( $title );

	Qevora_Test_Env::$died[] = is_scalar( $message ) ? (string) $message : 'wp_die';

	throw new RuntimeException( 'wp_die: ' . ( is_scalar( $message ) ? (string) $message : 'non scalar' ) );
}

/**
 * Registers an activation hook (no-op).
 *
 * @param string   $file     File.
 * @param callable $callback Callback.
 * @return void
 */
function register_activation_hook( $file, $callback ) {
	unset( $file, $callback );
}

/**
 * Registers a deactivation hook (no-op).
 *
 * @param string   $file     File.
 * @param callable $callback Callback.
 * @return void
 */
function register_deactivation_hook( $file, $callback ) {
	unset( $file, $callback );
}

/**
 * Deactivates a plugin (no-op).
 *
 * @param string $plugin Plugin.
 * @return void
 */
function deactivate_plugins( $plugin ) {
	unset( $plugin );
}

/**
 * Registers a post type (no-op).
 *
 * @param string              $post_type Post type.
 * @param array<string,mixed> $args      Arguments.
 * @return object
 */
function register_post_type( $post_type, $args = array() ) {
	unset( $args );

	if ( ! in_array( (string) $post_type, Qevora_Test_Env::$post_types, true ) ) {
		Qevora_Test_Env::$post_types[] = (string) $post_type;
	}

	$type       = new stdClass();
	$type->name = (string) $post_type;

	return $type;
}

/**
 * Adds post type support (no-op).
 *
 * @param string $post_type Post type.
 * @param string $feature   Feature.
 * @return void
 */
function add_post_type_support( $post_type, $feature ) {
	unset( $post_type, $feature );
}

/**
 * Renders blocks (returns the content unchanged in tests).
 *
 * @param string $content Content.
 * @return string
 */
function do_blocks( $content ) {
	return (string) $content;
}

/**
 * Renders shortcodes (returns the content unchanged in tests).
 *
 * @param string $content Content.
 * @return string
 */
function do_shortcode( $content ) {
	return (string) $content;
}

/**
 * Registers a shortcode (no-op).
 *
 * @param string   $tag      Tag.
 * @param callable $callback Callback.
 * @return void
 */
function add_shortcode( $tag, $callback ) {
	unset( $tag, $callback );
}

/**
 * Fires wp_body_open (no-op).
 *
 * @return void
 */
function wp_body_open() {
	do_action( 'wp_body_open' );
}

/**
 * Returns a formatted number.
 *
 * @param mixed $number Number.
 * @return string
 */
function number_format_i18n( $number ) {
	return number_format( (float) $number );
}

/**
 * Returns the post permalink.
 *
 * @param mixed $post Post.
 * @return string
 */
function get_permalink( $post = 0 ) {
	$post = get_post( $post );

	return $post instanceof WP_Post ? home_url( '/?p=' . (int) $post->ID ) : home_url();
}

/**
 * Returns an author name.
 *
 * @param mixed $user User.
 * @return string
 */
function get_the_author_meta( $field = '', $user = 0 ) {
	unset( $user );

	return 'display_name' === $field ? 'Test Author' : '';
}

/**
 * Formats bytes.
 *
 * @param int $bytes Bytes.
 * @return string
 */
function size_format( $bytes ) {
	return number_format( (int) $bytes / 1024, 1 ) . ' KB';
}

/**
 * Whether the response is a WP_Error style failure (alias used in a few places).
 *
 * @param mixed $value Value.
 * @return bool
 */
function is_email( $value ) {
	return (bool) filter_var( (string) $value, FILTER_VALIDATE_EMAIL );
}

/* -------------------------------------------------------------------------
 * Query vars, terms and cron (added for the condition engine)
 * ---------------------------------------------------------------------- */

/**
 * Whether WordPress is running cron.
 *
 * @return bool
 */
function wp_doing_cron() {
	return (bool) Qevora_Test_Env::is( 'doing_cron', false );
}

/**
 * Whether WordPress is running AJAX.
 *
 * @return bool
 */
function wp_doing_ajax() {
	return (bool) Qevora_Test_Env::is( 'doing_ajax', false );
}

/**
 * Reads a query variable.
 *
 * @param string $name    Name.
 * @param mixed  $default Default.
 * @return mixed
 */
function get_query_var( $name, $default = '' ) {
	$vars = Qevora_Test_Env::is( 'query_vars', array() );

	return isset( $vars[ (string) $name ] ) ? $vars[ (string) $name ] : $default;
}

/**
 * Reads one post field.
 *
 * @param string $field Field.
 * @param mixed  $post  Post or ID.
 * @return mixed
 */
function get_post_field( $field, $post = null ) {
	$post = get_post( $post );

	if ( ! $post instanceof WP_Post || ! isset( $post->$field ) ) {
		return '';
	}

	return $post->$field;
}

/**
 * Returns the taxonomies of an object type.
 *
 * @param string $object_type Object type.
 * @param string $output      Output type.
 * @return array<int,string>|array<string,object>
 */
function get_object_taxonomies( $object_type, $output = 'names' ) {
	$map = Qevora_Test_Env::is( 'object_taxonomies', array( 'post' => array( 'category', 'post_tag' ) ) );

	$names = isset( $map[ (string) $object_type ] ) ? (array) $map[ (string) $object_type ] : array();

	if ( 'names' === $output ) {
		return array_values( $names );
	}

	$out = array();

	foreach ( $names as $name ) {
		$taxonomy         = new stdClass();
		$taxonomy->name   = $name;
		$out[ $name ]     = $taxonomy;
	}

	return $out;
}

/**
 * Returns the term IDs assigned to one or more posts.
 *
 * @param int|int[]        $post_ids   Post IDs.
 * @param string|string[]  $taxonomies Taxonomies.
 * @param array<string,mixed> $args    Arguments.
 * @return array<int,int>
 */
function wp_get_object_terms( $post_ids, $taxonomies = array(), $args = array() ) {
	unset( $taxonomies, $args );

	$map = Qevora_Test_Env::is( 'post_terms', array() );
	$out = array();

	foreach ( (array) $post_ids as $post_id ) {
		$term_ids = isset( $map[ (int) $post_id ] ) ? (array) $map[ (int) $post_id ] : array();

		foreach ( $term_ids as $term_id ) {
			$out[] = (int) $term_id;
		}
	}

	return array_values( array_unique( $out ) );
}

/**
 * Returns one term.
 *
 * @param int $term_id Term ID.
 * @return WP_Term|null
 */
function get_term( $term_id ) {
	foreach ( Qevora_Test_Env::$terms as $term ) {
		if ( (int) $term->term_id === (int) $term_id ) {
			return $term;
		}
	}

	return null;
}

/**
 * Returns the ancestors of a term.
 *
 * @param int    $object_id Term ID.
 * @param string $object_type Type.
 * @param string $resource  Resource.
 * @return int[]
 */
function get_ancestors( $object_id, $object_type = '', $resource = '' ) {
	unset( $object_type, $resource );

	$ancestors = array();
	$term      = get_term( (int) $object_id );
	$guard     = 0;

	while ( $term instanceof WP_Term && (int) $term->parent > 0 && $guard < 20 ) {
		$ancestors[] = (int) $term->parent;
		$term        = get_term( (int) $term->parent );
		++$guard;
	}

	return $ancestors;
}

/**
 * Returns the descendants of a term.
 *
 * @param int    $term_id  Term ID.
 * @param string $taxonomy Taxonomy.
 * @return int[]
 */
function get_term_children( $term_id, $taxonomy ) {
	unset( $taxonomy );

	$children = array();
	$queue    = array( (int) $term_id );
	$guard    = 0;

	while ( ! empty( $queue ) && $guard < 200 ) {
		$current = array_shift( $queue );

		foreach ( Qevora_Test_Env::$terms as $term ) {
			if ( (int) $term->parent === $current ) {
				$children[] = (int) $term->term_id;
				$queue[]    = (int) $term->term_id;
			}
		}

		++$guard;
	}

	return $children;
}

/**
 * Returns the permalink structure.
 *
 * @return string
 */
function get_permalink_structure() {
	return (string) get_option( 'permalink_structure', '' );
}

/* -------------------------------------------------------------------------
 * REST helpers (the plugin uses two of them outside the REST server)
 * ---------------------------------------------------------------------- */

/**
 * Converts a value to a boolean the WordPress way.
 *
 * @param mixed $value Value.
 * @return bool
 */
function rest_sanitize_boolean( $value ) {
	// String values are translated to `true`; make sure 'false' is false.
	if ( is_string( $value ) ) {
		$value = strtolower( $value );

		if ( in_array( $value, array( 'false', '0' ), true ) ) {
			$value = false;
		}
	}

	return (bool) $value;
}

/**
 * Returns the HTTP status code for a failed permission check.
 *
 * @return int
 */
function rest_authorization_required_code() {
	return is_user_logged_in() ? 403 : 401;
}

/* -------------------------------------------------------------------------
 * HTTP API
 * ---------------------------------------------------------------------- */

/**
 * Performs a GET request against the canned response table.
 *
 * @param string             $url  Request URL.
 * @param array<string,mixed> $args Request arguments.
 * @return array<string,mixed>|WP_Error
 */
function wp_remote_get( $url, $args = array() ) {
	Qevora_Test_Env::$http_requests[] = array( (string) $url, (array) $args );

	if ( isset( Qevora_Test_Env::$http[ (string) $url ] ) ) {
		$response = Qevora_Test_Env::$http[ (string) $url ];

		if ( $response instanceof WP_Error ) {
			return $response;
		}

		return array_merge(
			array(
				'headers'  => array(),
				'body'     => '',
				'response' => array(
					'code'    => 200,
					'message' => 'OK',
				),
			),
			(array) $response
		);
	}

	return new WP_Error( 'http_request_failed', 'The test environment has no canned response for this URL.' );
}

/**
 * Performs a POST request. The plugin does not use it; parity only.
 *
 * @param string             $url  Request URL.
 * @param array<string,mixed> $args Request arguments.
 * @return array<string,mixed>|WP_Error
 */
function wp_remote_post( $url, $args = array() ) {
	return wp_remote_get( $url, $args );
}

/**
 * Returns the response status code.
 *
 * @param array<string,mixed>|WP_Error $response Response.
 * @return int|string
 */
function wp_remote_retrieve_response_code( $response ) {
	if ( ! is_array( $response ) || ! isset( $response['response']['code'] ) ) {
		return '';
	}

	return $response['response']['code'];
}

/**
 * Returns the response body.
 *
 * @param array<string,mixed>|WP_Error $response Response.
 * @return string
 */
function wp_remote_retrieve_body( $response ) {
	if ( ! is_array( $response ) || ! isset( $response['body'] ) ) {
		return '';
	}

	return (string) $response['body'];
}

/**
 * Returns one response header, case-insensitively.
 *
 * @param array<string,mixed>|WP_Error $response Response.
 * @param string                       $header   Header name.
 * @return string|array<int,string>
 */
function wp_remote_retrieve_header( $response, $header ) {
	if ( ! is_array( $response ) || empty( $response['headers'] ) || ! is_array( $response['headers'] ) ) {
		return '';
	}

	$header = strtolower( (string) $header );

	foreach ( $response['headers'] as $name => $value ) {
		if ( strtolower( (string) $name ) === $header ) {
			return $value;
		}
	}

	return '';
}

/**
 * Returns every response header.
 *
 * @param array<string,mixed>|WP_Error $response Response.
 * @return array<string,mixed>|string
 */
function wp_remote_retrieve_headers( $response ) {
	if ( ! is_array( $response ) || ! isset( $response['headers'] ) ) {
		return '';
	}

	return $response['headers'];
}

/* -------------------------------------------------------------------------
 * Assets
 * ---------------------------------------------------------------------- */

/**
 * Registers a stylesheet.
 *
 * @param string           $handle Handle.
 * @param string           $src    Source.
 * @param array<int,string> $deps   Dependencies.
 * @param string|bool|null $ver    Version.
 * @param string           $media  Media.
 * @return bool
 */
function wp_register_style( $handle, $src = '', $deps = array(), $ver = false, $media = 'all' ) {
	Qevora_Test_Env::$assets[ (string) $handle ] = array(
		'type'    => 'style',
		'action'  => 'register',
		'src'     => (string) $src,
		'deps'    => (array) $deps,
		'ver'     => $ver,
		'media'   => (string) $media,
	);

	return true;
}

/**
 * Enqueues a stylesheet.
 *
 * @param string           $handle Handle.
 * @param string           $src    Source.
 * @param array<int,string> $deps   Dependencies.
 * @param string|bool|null $ver    Version.
 * @param string           $media  Media.
 * @return void
 */
function wp_enqueue_style( $handle, $src = '', $deps = array(), $ver = false, $media = 'all' ) {
	$handle = (string) $handle;

	if ( '' !== $src || ! isset( Qevora_Test_Env::$assets[ $handle ] ) ) {
		wp_register_style( $handle, $src, $deps, $ver, $media );
	}

	Qevora_Test_Env::$assets[ $handle ]['action'] = 'enqueue';
}

/**
 * Registers a script.
 *
 * @param string           $handle  Handle.
 * @param string           $src     Source.
 * @param array<int,string> $deps    Dependencies.
 * @param string|bool|null $ver     Version.
 * @param bool|array<string,mixed> $in_footer Whether to print in the footer.
 * @return bool
 */
function wp_register_script( $handle, $src = '', $deps = array(), $ver = false, $in_footer = false ) {
	Qevora_Test_Env::$assets[ (string) $handle ] = array(
		'type'      => 'script',
		'action'    => 'register',
		'src'       => (string) $src,
		'deps'      => (array) $deps,
		'ver'       => $ver,
		'in_footer' => $in_footer,
		'localized' => array(),
	);

	return true;
}

/**
 * Enqueues a script.
 *
 * @param string           $handle  Handle.
 * @param string           $src     Source.
 * @param array<int,string> $deps    Dependencies.
 * @param string|bool|null $ver     Version.
 * @param bool|array<string,mixed> $in_footer Whether to print in the footer.
 * @return void
 */
function wp_enqueue_script( $handle, $src = '', $deps = array(), $ver = false, $in_footer = false ) {
	$handle = (string) $handle;

	if ( '' !== $src || ! isset( Qevora_Test_Env::$assets[ $handle ] ) ) {
		wp_register_script( $handle, $src, $deps, $ver, $in_footer );
	}

	Qevora_Test_Env::$assets[ $handle ]['action'] = 'enqueue';
}

/**
 * Attaches data to a script.
 *
 * @param string               $handle     Handle.
 * @param string               $object_name JavaScript object name.
 * @param array<string,mixed>  $data       Data.
 * @return bool
 */
function wp_localize_script( $handle, $object_name, $data ) {
	$handle = (string) $handle;

	if ( ! isset( Qevora_Test_Env::$assets[ $handle ] ) ) {
		return false;
	}

	Qevora_Test_Env::$assets[ $handle ]['localized'][ (string) $object_name ] = $data;

	return true;
}

/**
 * Loads script translations.
 *
 * @param string $handle Handle.
 * @param string $domain Text domain.
 * @param string $path   Path.
 * @return bool
 */
function wp_set_script_translations( $handle, $domain = '', $path = '' ) {
	unset( $handle, $domain, $path );

	return true;
}

/**
 * Adds inline CSS to a stylesheet handle.
 *
 * @param string $handle Handle.
 * @param string $data   CSS.
 * @param string $position Position.
 * @return bool
 */
function wp_add_inline_style( $handle, $data, $position = 'after' ) {
	$handle = (string) $handle;

	if ( ! isset( Qevora_Test_Env::$assets[ $handle ] ) ) {
		return false;
	}

	Qevora_Test_Env::$assets[ $handle ]['inline'] = isset( Qevora_Test_Env::$assets[ $handle ]['inline'] )
		? Qevora_Test_Env::$assets[ $handle ]['inline'] . "\n" . $data
		: $data;

	unset( $position );

	return true;
}

/**
 * Whether a handle was enqueued.
 *
 * @param string $handle Handle.
 * @param string $list   Status list.
 * @return bool
 */
function wp_script_is( $handle, $list = 'enqueued' ) {
	unset( $list );

	return isset( Qevora_Test_Env::$assets[ (string) $handle ] ) && 'script' === Qevora_Test_Env::$assets[ (string) $handle ]['type'];
}

/**
 * Whether a stylesheet handle was enqueued.
 *
 * @param string $handle Handle.
 * @param string $list   Status list.
 * @return bool
 */
function wp_style_is( $handle, $list = 'enqueued' ) {
	unset( $list );

	return isset( Qevora_Test_Env::$assets[ (string) $handle ] ) && 'style' === Qevora_Test_Env::$assets[ (string) $handle ]['type'];
}

/**
 * Dequeues a script.
 *
 * @param string $handle Handle.
 * @return void
 */
function wp_dequeue_script( $handle ) {
	unset( Qevora_Test_Env::$assets[ (string) $handle ] );
}

/**
 * Dequeues a stylesheet.
 *
 * @param string $handle Handle.
 * @return void
 */
function wp_dequeue_style( $handle ) {
	unset( Qevora_Test_Env::$assets[ (string) $handle ] );
}

/* -------------------------------------------------------------------------
 * Admin UI
 * ---------------------------------------------------------------------- */

/**
 * Registers a top-level admin menu page.
 *
 * @param string   $page_title   Page title.
 * @param string   $menu_title   Menu title.
 * @param string   $capability   Capability.
 * @param string   $menu_slug    Slug.
 * @param callable $callback     Render callback.
 * @param string   $icon_url     Icon.
 * @param int|null $position     Position.
 * @return string
 */
function add_menu_page( $page_title, $menu_title, $capability, $menu_slug, $callback = '', $icon_url = '', $position = null ) {
	Qevora_Test_Env::$admin['menus'][] = array(
		'kind'       => 'menu',
		'page_title' => (string) $page_title,
		'menu_title' => (string) $menu_title,
		'capability' => (string) $capability,
		'slug'       => (string) $menu_slug,
		'callback'   => $callback,
		'icon'       => (string) $icon_url,
		'position'   => $position,
	);

	return (string) $menu_slug;
}

/**
 * Registers a submenu page.
 *
 * @param string   $parent_slug  Parent slug.
 * @param string   $page_title   Page title.
 * @param string   $menu_title   Menu title.
 * @param string   $capability   Capability.
 * @param string   $menu_slug    Slug.
 * @param callable $callback     Render callback.
 * @param int|null $position     Position.
 * @return string|false
 */
function add_submenu_page( $parent_slug, $page_title, $menu_title, $capability, $menu_slug, $callback = '', $position = null ) {
	Qevora_Test_Env::$admin['menus'][] = array(
		'kind'       => 'submenu',
		'parent'     => (string) $parent_slug,
		'page_title' => (string) $page_title,
		'menu_title' => (string) $menu_title,
		'capability' => (string) $capability,
		'slug'       => (string) $menu_slug,
		'callback'   => $callback,
		'position'   => $position,
	);

	return (string) $menu_slug;
}

/**
 * Registers a meta box.
 *
 * @param string               $id       Meta box ID.
 * @param string               $title    Title.
 * @param callable             $callback Render callback.
 * @param string|array<int,string> $screen   Screen(s).
 * @param string               $context  Context.
 * @param string               $priority Priority.
 * @param array<string,mixed>  $args     Callback arguments.
 * @return void
 */
function add_meta_box( $id, $title, $callback, $screen = null, $context = 'advanced', $priority = 'default', $args = array() ) {
	Qevora_Test_Env::$admin['meta_boxes'][] = array(
		'id'       => (string) $id,
		'title'    => (string) $title,
		'callback' => $callback,
		'screen'   => $screen,
		'context'  => (string) $context,
		'priority' => (string) $priority,
		'args'     => (array) $args,
	);
}

/**
 * Removes a meta box.
 *
 * @param string               $id      Meta box ID.
 * @param string|array<int,string> $screen  Screen(s).
 * @param string               $context Context.
 * @return void
 */
function remove_meta_box( $id, $screen = null, $context = 'advanced' ) {
	unset( $id, $screen, $context );
}

/**
 * Registers a settings section.
 *
 * @param string   $id       Section ID.
 * @param string   $title    Title.
 * @param callable $callback Render callback.
 * @param string   $page     Page slug.
 * @param string   $section  Section.
 * @param array<string,mixed> $args Arguments.
 * @return void
 */
function add_settings_section( $id, $title, $callback, $page, $section = 'default', $args = array() ) {
	Qevora_Test_Env::$admin['sections'][] = array(
		'id'       => (string) $id,
		'title'    => (string) $title,
		'callback' => $callback,
		'page'     => (string) $page,
		'section'  => (string) $section,
		'args'     => (array) $args,
	);
}

/**
 * Registers a settings field.
 *
 * @param string   $id       Field ID.
 * @param string   $title    Title.
 * @param callable $callback Render callback.
 * @param string   $page     Page slug.
 * @param string   $section  Section.
 * @param array<string,mixed> $args Arguments.
 * @return void
 */
function add_settings_field( $id, $title, $callback, $page, $section = 'default', $args = array() ) {
	Qevora_Test_Env::$admin['fields'][] = array(
		'id'       => (string) $id,
		'title'    => (string) $title,
		'callback' => $callback,
		'page'     => (string) $page,
		'section'  => (string) $section,
		'args'     => (array) $args,
	);
}

/**
 * Prints the settings sections of a page.
 *
 * @param string $page Page slug.
 * @return void
 */
function do_settings_sections( $page ) {
	foreach ( Qevora_Test_Env::$admin['sections'] as $section ) {
		if ( $section['page'] !== (string) $page ) {
			continue;
		}

		Qevora_Test_Env::$output .= '<section id="' . esc_attr( $section['id'] ) . '">' . esc_html( $section['title'] ) . '</section>';

		if ( is_callable( $section['callback'] ) ) {
			call_user_func( $section['callback'], $section['args'] );
		}
	}
}

/**
 * Prints the settings fields of a page.
 *
 * @param string $page Page slug.
 * @return void
 */
function settings_fields( $page ) {
	Qevora_Test_Env::$output .= '<input type="hidden" name="option_page" value="' . esc_attr( (string) $page ) . '" />';
	Qevora_Test_Env::$output .= wp_nonce_field( 'qevora_test_settings_' . $page, '_wpnonce', true, false );
}

/**
 * Registers a setting.
 *
 * @param string               $option_group Option group.
 * @param string               $option_name  Option name.
 * @param array<string,mixed>  $args         Arguments.
 * @return void
 */
function register_setting( $option_group, $option_name, $args = array() ) {
	Qevora_Test_Env::$admin['settings'][] = array(
		'group' => (string) $option_group,
		'name'  => (string) $option_name,
		'args'  => (array) $args,
	);
}

/**
 * Prints a submit button.
 *
 * @param string $text  Label.
 * @param string $type  Button type.
 * @param string $name  Input name.
 * @param bool   $wrap  Whether to wrap.
 * @param array<string,mixed>|string $other_attributes Attributes.
 * @return string
 */
function submit_button( $text = null, $type = 'primary', $name = 'submit', $wrap = true, $other_attributes = null ) {
	$html = '<input type="submit" name="' . esc_attr( (string) $name ) . '" id="' . esc_attr( (string) $name ) . '" class="button button-' . esc_attr( (string) $type ) . '" value="' . esc_attr( null === $text ? 'Save Changes' : (string) $text ) . '" />';

	unset( $wrap, $other_attributes );

	return $html;
}

/**
 * Returns the checked attribute.
 *
 * @param mixed $checked One of the values.
 * @param mixed $current The other value.
 * @param bool  $display Whether to echo.
 * @return string
 */
function checked( $checked, $current = true, $display = true ) {
	$result = (string) $checked === (string) $current ? " checked='checked'" : '';

	if ( $display ) {
		echo $result; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	return $result;
}

/**
 * Returns the selected attribute.
 *
 * @param mixed $selected One of the values.
 * @param mixed $current  The other value.
 * @param bool  $display  Whether to echo.
 * @return string
 */
function selected( $selected, $current = true, $display = true ) {
	$result = (string) $selected === (string) $current ? " selected='selected'" : '';

	if ( $display ) {
		echo $result; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	return $result;
}

/**
 * Returns the disabled attribute.
 *
 * @param mixed $disabled One of the values.
 * @param mixed $current  The other value.
 * @param bool  $display  Whether to echo.
 * @return string
 */
function disabled( $disabled, $current = true, $display = true ) {
	$result = (string) $disabled === (string) $current ? " disabled='disabled'" : '';

	if ( $display ) {
		echo $result; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	return $result;
}

/**
 * Returns the readonly attribute.
 *
 * @param mixed $readonly One of the values.
 * @param mixed $current  The other value.
 * @param bool  $display  Whether to echo.
 * @return string
 */
function readonly( $readonly, $current = true, $display = true ) {
	$result = (string) $readonly === (string) $current ? " readonly='readonly'" : '';

	if ( $display ) {
		echo $result; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	return $result;
}

/**
 * Returns the current screen.
 *
 * @return object|null
 */
function get_current_screen() {
	return Qevora_Test_Env::$screen;
}

/**
 * Sets the current screen.
 *
 * @param string|object $screen Screen.
 * @return void
 */
function set_current_screen( $screen = '' ) {
	if ( is_string( $screen ) ) {
		$screen = (object) array(
			'id'       => $screen,
			'base'     => $screen,
			'post_type' => '',
			'action'   => '',
		);
	}

	Qevora_Test_Env::$screen = $screen;
}

/**
 * Builds the hook name of a plugin page.
 *
 * @param string $page_title  Page title.
 * @param string $parent_page Parent page.
 * @return string
 */
function get_plugin_page_hookname( $page_title, $parent_page ) {
	unset( $parent_page );

	return 'settings_page_' . (string) $page_title;
}

/* -------------------------------------------------------------------------
 * REST API, blocks and shortcodes
 * ---------------------------------------------------------------------- */

/**
 * Registers a REST route.
 *
 * @param string               $namespace Namespace.
 * @param string               $route     Route.
 * @param array<string,mixed>  $args      Route arguments.
 * @param bool                 $override  Whether to override.
 * @return bool
 */
function register_rest_route( $namespace, $route, $args = array(), $override = false ) {
	unset( $override );

	Qevora_Test_Env::$rest_routes[ trim( (string) $namespace, '/' ) . trim( (string) $route, '/' ) ] = $args;

	return true;
}

/**
 * Wraps data in a REST response object.
 *
 * @param mixed $response Data.
 * @return WP_REST_Response
 */
function rest_ensure_response( $response ) {
	if ( $response instanceof WP_REST_Response ) {
		return $response;
	}

	return new WP_REST_Response( $response );
}

/**
 * Registers a block type.
 *
 * @param string              $name Block name.
 * @param array<string,mixed> $args Block arguments.
 * @return object|false
 */
function register_block_type( $name, $args = array() ) {
	Qevora_Test_Env::$blocks[ (string) $name ] = (array) $args;

	return (object) array_merge( array( 'name' => (string) $name ), (array) $args );
}

/**
 * Whether a shortcode is registered.
 *
 * @param string $tag Tag.
 * @return bool
 */
function shortcode_exists( $tag ) {
	return isset( Qevora_Test_Env::$shortcodes[ (string) $tag ] );
}

/**
 * Merges user arguments over the defaults.
 *
 * @param array<string,mixed> $pairs     Defaults.
 * @param array<string,mixed> $atts      User arguments.
 * @param string              $shortcode Shortcode name.
 * @return array<string,mixed>
 */
function shortcode_atts( $pairs, $atts, $shortcode = '' ) {
	unset( $shortcode );

	$atts = (array) $atts;
	$out  = array();

	foreach ( $pairs as $name => $default ) {
		if ( array_key_exists( $name, $atts ) ) {
			$out[ $name ] = $atts[ $name ];
		} else {
			$out[ $name ] = $default;
		}
	}

	foreach ( $atts as $name => $value ) {
		if ( array_key_exists( $name, $out ) ) {
			continue;
		}

		$out[ $name ] = $value;
	}

	return $out;
}

/**
 * Parses a shortcode attribute string.
 *
 * @param string $text Attributes.
 * @return array<string,mixed>|string
 */
function shortcode_parse_atts( $text ) {
	$atts = array();
	$text = preg_replace( '/[\x{00a0}\x{200b}]+/u', ' ', (string) $text );

	if ( preg_match_all( '/([\w-]+)\s*=\s*"([^"]*)"(?:\s|$)|([\w-]+)\s*=\s*\'([^\']*)\'(?:\s|$)|([\w-]+)\s*=\s*([^\s\'"]+)(?:\s|$)|"([^"]*)"(?:\s|$)|(\S+)(?:\s|$)/', $text, $matches, PREG_SET_ORDER ) ) {
		foreach ( $matches as $match ) {
			if ( ! empty( $match[1] ) ) {
				$atts[ strtolower( $match[1] ) ] = $match[2];
			} elseif ( ! empty( $match[3] ) ) {
				$atts[ strtolower( $match[3] ) ] = $match[4];
			} elseif ( ! empty( $match[5] ) ) {
				$atts[ strtolower( $match[5] ) ] = $match[6];
			} elseif ( isset( $match[7] ) && strlen( $match[7] ) ) {
				$atts[] = $match[7];
			} elseif ( isset( $match[8] ) && strlen( $match[8] ) ) {
				$atts[] = $match[8];
			}
		}
	}

	return empty( $atts ) ? '' : $atts;
}

/* -------------------------------------------------------------------------
 * Requests, redirects and JSON responses
 * ---------------------------------------------------------------------- */

/**
 * Records a redirect instead of sending headers.
 *
 * @param string $location Target URL.
 * @param int    $status   Status code.
 * @return void
 */
function wp_safe_redirect( $location, $status = 302 ) {
	Qevora_Test_Env::$redirects[] = array(
		'location' => (string) $location,
		'status'   => (int) $status,
	);
}

/**
 * Records a redirect instead of sending headers.
 *
 * @param string $location Target URL.
 * @param int    $status   Status code.
 * @return void
 */
function wp_redirect( $location, $status = 302 ) {
	wp_safe_redirect( $location, $status );
}

/**
 * Returns the referer.
 *
 * @return string|false
 */
function wp_get_referer() {
	return isset( Qevora_Test_Env::$server['HTTP_REFERER'] ) ? (string) Qevora_Test_Env::$server['HTTP_REFERER'] : false;
}

/**
 * Sends a JSON success response.
 *
 * @param mixed $data    Data.
 * @param int   $status  Status code.
 * @param int   $options Encoding options.
 * @return void
 */
function wp_send_json_success( $data = null, $status = null, $options = 0 ) {
	unset( $status, $options );

	Qevora_Test_Env::$json = array(
		'success' => true,
		'data'    => $data,
	);
}

/**
 * Sends a JSON error response.
 *
 * @param mixed $data    Data.
 * @param int   $status  Status code.
 * @param int   $options Encoding options.
 * @return void
 */
function wp_send_json_error( $data = null, $status = null, $options = 0 ) {
	unset( $status, $options );

	Qevora_Test_Env::$json = array(
		'success' => false,
		'data'    => $data,
	);
}

/**
 * Sends a JSON response.
 *
 * @param mixed $response Data.
 * @param int   $status   Status code.
 * @param int   $options  Encoding options.
 * @return void
 */
function wp_send_json( $response, $status = null, $options = 0 ) {
	unset( $status, $options );

	Qevora_Test_Env::$json = $response;
}

/* -------------------------------------------------------------------------
 * Theme, template and post helpers
 * ---------------------------------------------------------------------- */

/**
 * Returns the active theme directory.
 *
 * @return string
 */
function get_template_directory() {
	return ABSPATH . 'wp-content/themes/testtheme';
}

/**
 * Returns the active (child) theme directory.
 *
 * @return string
 */
function get_stylesheet_directory() {
	return get_template_directory();
}

/**
 * Returns the active theme directory URI.
 *
 * @return string
 */
function get_template_directory_uri() {
	return 'https://example.org/wp-content/themes/testtheme';
}

/**
 * Returns the child theme directory URI.
 *
 * @return string
 */
function get_stylesheet_directory_uri() {
	return get_template_directory_uri();
}

/**
 * Loads the theme header.
 *
 * @param string|null $name Header name.
 * @param array<string,mixed> $args Arguments.
 * @return void
 */
function get_header( $name = null, $args = array() ) {
	unset( $args );

	Qevora_Test_Env::$output .= '<!-- header' . ( $name ? '-' . $name : '' ) . ' -->';

	do_action( 'get_header', $name );
}

/**
 * Loads the theme footer.
 *
 * @param string|null $name Footer name.
 * @param array<string,mixed> $args Arguments.
 * @return void
 */
function get_footer( $name = null, $args = array() ) {
	unset( $args );

	Qevora_Test_Env::$output .= '<!-- footer' . ( $name ? '-' . $name : '' ) . ' -->';

	do_action( 'get_footer', $name );
}

/**
 * Loads the theme sidebar.
 *
 * @param string|null $name Sidebar name.
 * @param array<string,mixed> $args Arguments.
 * @return void
 */
function get_sidebar( $name = null, $args = array() ) {
	unset( $name, $args );

	Qevora_Test_Env::$output .= '<!-- sidebar -->';
}

/**
 * Fires the wp_footer hook.
 *
 * @return void
 */
function wp_footer() {
	do_action( 'wp_footer' );
}

/**
 * Fires the wp_head hook.
 *
 * @return void
 */
function wp_head() {
	do_action( 'wp_head' );
}

/**
 * Whether the current request is a feed.
 *
 * @return bool
 */
function is_feed( $feeds = '' ) {
	unset( $feeds );

	return (bool) Qevora_Test_Env::is( 'feed', false );
}

/**
 * Whether the Customizer preview is running.
 *
 * @return bool
 */
function is_customize_preview() {
	return (bool) Qevora_Test_Env::is( 'customize_preview', false );
}

/**
 * Whether WordPress is serving a REST request.
 *
 * @return bool
 */
function wp_is_serving_rest_request() {
	return defined( 'REST_REQUEST' ) && REST_REQUEST;
}

/**
 * Formats a GMT date for display.
 *
 * @param string $gmt_date GMT date.
 * @param string $format   Date format.
 * @return string
 */
function get_date_from_gmt( $gmt_date, $format = 'Y-m-d H:i:s' ) {
	unset( $format );

	return (string) $gmt_date;
}

/**
 * Formats a MySQL date.
 *
 * @param string $format     Format.
 * @param string $mysql_date Date.
 * @param bool   $gmt        Whether the date is GMT.
 * @param bool   $translate  Whether to translate.
 * @return string
 */
function mysql2date( $format, $mysql_date, $translate = true ) {
	unset( $format, $translate );

	return (string) $mysql_date;
}

/**
 * Formats a date in the human-readable time difference style.
 *
 * @param string|int $from Earlier date or timestamp.
 * @param string|int $to   Later date or timestamp, 0 for now.
 * @return string
 */
function human_time_diff( $from, $to = 0 ) {
	$from = is_numeric( $from ) ? (int) $from : (int) strtotime( (string) $from );
	$to   = $to ? ( is_numeric( $to ) ? (int) $to : (int) strtotime( (string) $to ) ) : time();
	$diff = abs( $to - $from );

	if ( $diff < HOUR_IN_SECONDS ) {
		$mins = max( 1, (int) round( $diff / MINUTE_IN_SECONDS ) );

		return $mins . ' mins';
	}

	return max( 1, (int) round( $diff / HOUR_IN_SECONDS ) ) . ' hours';
}

/**
 * Strips accents from a string.
 *
 * @param string $string Input.
 * @return string
 */
function remove_accents( $string ) {
	if ( ! preg_match( '/[\x80-\xff]/', (string) $string ) ) {
		return (string) $string;
	}

	$chars = array(
		'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A', 'Å' => 'A',
		'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a',
		'Ç' => 'C', 'ç' => 'c', 'È' => 'E', 'É' => 'E', 'Ê' => 'E', 'Ë' => 'E',
		'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e', 'Ì' => 'I', 'Í' => 'I',
		'ì' => 'i', 'í' => 'i', 'Ñ' => 'N', 'ñ' => 'n', 'Ò' => 'O', 'Ó' => 'O',
		'ò' => 'o', 'ó' => 'o', 'Ù' => 'U', 'Ú' => 'U', 'ù' => 'u', 'ú' => 'u',
	);

	return strtr( (string) $string, $chars );
}

/**
 * Returns a file name without its path, Windows-safe.
 *
 * @param string $path   Path.
 * @param string $suffix Suffix to remove.
 * @return string
 */
function wp_basename( $path, $suffix = '' ) {
	return urldecode( basename( str_replace( array( '%2F', '%5C' ), '/', urlencode( (string) $path ) ), $suffix ) );
}

/**
 * Whether a post is a revision.
 *
 * @param int|WP_Post $post Post.
 * @return int|false
 */
function wp_is_post_revision( $post = false ) {
	$post = get_post( $post );

	return ( $post instanceof WP_Post && 'revision' === $post->post_type ) ? (int) $post->ID : false;
}

/**
 * Whether a post is an autosave revision.
 *
 * @param int|WP_Post $post Post.
 * @return int|false
 */
function wp_is_post_autosave( $post = false ) {
	$post = get_post( $post );

	return ( $post instanceof WP_Post && 'revision' === $post->post_type && false !== strpos( (string) $post->post_name, 'autosave' ) )
		? (int) $post->ID
		: false;
}

/**
 * Sets up the global post data.
 *
 * @param WP_Post|int|null $post Post.
 * @return bool
 */
function setup_postdata( $post = null ) {
	unset( $post );

	return true;
}

/**
 * Resets the global post data.
 *
 * @return bool
 */
function wp_reset_postdata() {
	return true;
}

