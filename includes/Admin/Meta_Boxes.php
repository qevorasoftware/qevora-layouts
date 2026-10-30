<?php
/**
 * Layout editor meta boxes.
 *
 * @package QevoraLayouts
 * @since   1.0.0
 */

namespace QevoraLayouts\Admin;

use QevoraLayouts\Conditions\Condition_Registry;
use QevoraLayouts\Conditions\Condition_Sanitizer;
use QevoraLayouts\Core\Capabilities;
use QevoraLayouts\Core\Layout;
use QevoraLayouts\Core\Layout_Post_Type;
use QevoraLayouts\Core\Logger;
use QevoraLayouts\Core\Plugin;
use QevoraLayouts\Import\Demo_Registry;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Layout type, placement, conditions and content source meta boxes.
 *
 * All boxes render their state server side, so what an editor sees is what is
 * stored. JavaScript only adds, removes and re-orders rows.
 *
 * @since 1.0.0
 */
class Meta_Boxes {

	/**
	 * Nonce action.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	const NONCE_ACTION = 'qevora_layouts_save_meta';

	/**
	 * Nonce field name.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	const NONCE_FIELD = 'qevora_layouts_meta_nonce';

	/**
	 * Registers hooks.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function register() {
		add_action( 'add_meta_boxes_' . Layout_Post_Type::POST_TYPE, array( __CLASS__, 'add_boxes' ), 10, 2 );
		add_action( 'save_post_' . Layout_Post_Type::POST_TYPE, array( __CLASS__, 'save' ), 10, 2 );
		add_action( 'save_post_' . Layout_Post_Type::POST_TYPE, array( __CLASS__, 'save_demo_settings' ), 11, 2 );
		add_filter( 'use_block_editor_for_post', array( __CLASS__, 'use_block_editor' ), 10, 2 );
		add_action( 'edit_form_top', array( __CLASS__, 'demo_editor_notice' ) );
	}

	/**
	 * Adds the meta boxes.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Post $post Post being edited.
	 * @return void
	 */
	public static function add_boxes( $post ) {
		if ( ! $post instanceof \WP_Post || ! Capabilities::can_edit_layout( $post->ID ) ) {
			return;
		}

		$demo = self::demo_for_post( $post->ID );

		if ( null !== $demo ) {
			add_meta_box(
				'qevora-layout-demo-settings',
				sprintf(
					/* translators: %s: name of the bundled demo. */
					__( '%s - demo settings', 'qevora-layouts' ),
					(string) $demo['name']
				),
				array( __CLASS__, 'render_demo_settings_box' ),
				Layout_Post_Type::POST_TYPE,
				'normal',
				'high'
			);
		}

		add_meta_box(
			'qevora-layout-type',
			__( 'Layout type and placement', 'qevora-layouts' ),
			array( __CLASS__, 'render_type_box' ),
			Layout_Post_Type::POST_TYPE,
			'side',
			'high'
		);

		add_meta_box(
			'qevora-layout-conditions',
			__( 'Display conditions', 'qevora-layouts' ),
			array( __CLASS__, 'render_conditions_box' ),
			Layout_Post_Type::POST_TYPE,
			'normal',
			'high'
		);

		add_meta_box(
			'qevora-layout-content',
			__( 'Content source', 'qevora-layouts' ),
			array( __CLASS__, 'render_content_box' ),
			Layout_Post_Type::POST_TYPE,
			'normal',
			'default'
		);

		add_meta_box(
			'qevora-layout-shortcode',
			__( 'Usage', 'qevora-layouts' ),
			array( __CLASS__, 'render_usage_box' ),
			Layout_Post_Type::POST_TYPE,
			'side',
			'default'
		);

		if ( self::has_import_data( $post->ID ) ) {
			add_meta_box(
				'qevora-layout-import',
				__( 'Imported from', 'qevora-layouts' ),
				array( __CLASS__, 'render_import_box' ),
				Layout_Post_Type::POST_TYPE,
				'side',
				'low'
			);
		}
	}

	/**
	 * Renders the type/placement box.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Post $post Post.
	 * @return void
	 */
	public static function render_type_box( $post ) {
		$layout = Layout::from_post( $post ) ?: new Layout();

		wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD );

		echo '<p>';
		echo '<label for="qevora-layout-type-select"><strong>' . esc_html__( 'Layout type', 'qevora-layouts' ) . '</strong></label><br />';
		echo '<select id="qevora-layout-type-select" name="qevora_layout[type]" class="widefat">';

		foreach ( Layout_Post_Type::get_types() as $slug => $type ) {
			printf(
				'<option value="%1$s"%2$s>%3$s</option>',
				esc_attr( (string) $slug ),
				selected( $layout->type, (string) $slug, false ),
				esc_html( isset( $type['label'] ) ? (string) $type['label'] : (string) $slug )
			);
		}

		echo '</select></p>';

		echo '<p class="description">' . esc_html__( 'The type decides which placement options are available and where the layout can render.', 'qevora-layouts' ) . '</p>';

		echo '<hr />';

		echo '<p>';
		echo '<label for="qevora-layout-priority"><strong>' . esc_html__( 'Priority', 'qevora-layouts' ) . '</strong></label><br />';
		printf(
			'<input type="number" id="qevora-layout-priority" name="qevora_layout[priority]" min="0" max="999" step="1" value="%1$s" class="small-text" />',
			esc_attr( (string) $layout->priority )
		);
		echo '</p>';
		echo '<p class="description">' . esc_html__( 'When several layouts match, the highest priority wins. Equal priorities are resolved by condition specificity, then by the oldest layout.', 'qevora-layouts' ) . '</p>';

		if ( null === self::demo_for_post( $post->ID ) ) {
			echo '<hr />';

			echo '<div class="qevora-field" data-qevora-placement-fields>';

		echo '<p>';
		echo '<label for="qevora-layout-placement"><strong>' . esc_html__( 'Placement', 'qevora-layouts' ) . '</strong></label><br />';
		echo '<select id="qevora-layout-placement" name="qevora_layout[placement]" class="widefat" data-qevora-placement>';
		echo '<option value="">' . esc_html__( 'Use the plugin default', 'qevora-layouts' ) . '</option>';

		foreach ( array_merge( Layout_Post_Type::get_placements( 'header' ), Layout_Post_Type::get_placements( 'footer' ) ) as $slug => $label ) {
			printf(
				'<option value="%1$s"%2$s>%3$s</option>',
				esc_attr( (string) $slug ),
				selected( $layout->placement, (string) $slug, false ),
				esc_html( (string) $label )
			);
		}

		echo '</select></p>';

		echo '<p class="description" data-qevora-placement-note>' . esc_html__( 'The placement chooses where on the page this layout appears. Modern block themes do not have a classic header or footer, so a choice made for classic themes is moved automatically to the closest position that works there.', 'qevora-layouts' ) . '</p>';

			echo '</div>'; // data-qevora-placement-fields
		}

		echo '<div class="qevora-field" data-qevora-hide-theme-field>';

		echo '<p>';
		echo '<input type="hidden" name="qevora_layout[hide_theme_part]" value="0" />';
		printf(
			'<label for="qevora-layout-hide-theme-part"><input type="checkbox" id="qevora-layout-hide-theme-part" name="qevora_layout[hide_theme_part]" value="1"%1$s /> <strong>%2$s</strong></label>',
			checked( ! empty( $layout->hide_theme_part ), true, false ),
			esc_html__( 'Hide the theme header/footer while this layout is displayed', 'qevora-layouts' )
		);
		echo '</p>';

		echo '<p class="description">' . esc_html__( 'Stops the theme header or footer and this layout from showing at the same time. Theme files are never changed - untick this box and the theme part comes straight back. Applies to header and footer layouts only.', 'qevora-layouts' ) . '</p>';

		echo '</div>'; // data-qevora-hide-theme-field

		echo '<div class="qevora-field" data-qevora-hook-fields>';

		echo '<p>';
		echo '<label for="qevora-layout-hook"><strong>' . esc_html__( 'Custom hook name', 'qevora-layouts' ) . '</strong></label><br />';
		printf(
			'<input type="text" id="qevora-layout-hook" name="qevora_layout[hook]" value="%1$s" class="widefat code" placeholder="%2$s" />',
			esc_attr( $layout->hook ),
			esc_attr__( 'my_theme_before_header', 'qevora-layouts' )
		);
		echo '</p>';

		echo '<p>';
		echo '<label for="qevora-layout-hook-priority">' . esc_html__( 'Custom hook priority', 'qevora-layouts' ) . '</label><br />';
		printf(
			'<input type="number" id="qevora-layout-hook-priority" name="qevora_layout[hook_priority]" min="0" max="999" step="1" value="%1$s" class="small-text" />',
			esc_attr( (string) $layout->hook_priority )
		);
		echo '</p>';

		echo '<p class="description">' . esc_html__( 'Only used when the placement is "Custom theme hook". Enter a hook your theme fires, for example a GeneratePress, Astra or Kadence hook.', 'qevora-layouts' ) . '</p>';

		echo '</div>'; // data-qevora-hook-fields
	}

	/**
	 * Renders the conditions box.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Post $post Post.
	 * @return void
	 */
	public static function render_conditions_box( $post ) {
		$layout     = Layout::from_post( $post ) ?: new Layout();
		$conditions = Condition_Sanitizer::normalize( $layout->conditions );

		echo '<p class="description">';
		echo esc_html__( 'Include: the layout shows on these views. Exclude: the layout never shows on these views. Exclusions always win. Leave the include list empty to match the entire site.', 'qevora-layouts' );
		echo '</p>';

		self::render_rule_list( 'include', isset( $conditions['include'] ) ? $conditions['include'] : array() );
		self::render_rule_list( 'exclude', isset( $conditions['exclude'] ) ? $conditions['exclude'] : array() );

		echo '<noscript><p class="description">' . esc_html__( 'Adding and removing conditions needs JavaScript. Existing conditions are still saved.', 'qevora-layouts' ) . '</p></noscript>';
	}

	/**
	 * Renders one include/exclude rule list.
	 *
	 * @since 1.0.0
	 *
	 * @param string                            $list  include|exclude.
	 * @param array<int,array<string,string>>   $rules Stored rules.
	 * @return void
	 */
	private static function render_rule_list( $list, array $rules ) {
		$heading = 'include' === $list
			? __( 'Include', 'qevora-layouts' )
			: __( 'Exclude', 'qevora-layouts' );

		printf(
			'<fieldset class="qevora-conditions" data-list="%1$s"><legend class="screen-reader-text"><span>%2$s</span></legend>',
			esc_attr( $list ),
			esc_html( $heading )
		);

		printf(
			'<h3 class="qevora-conditions__title">%s</h3>',
			esc_html( $heading )
		);

		echo '<div class="qevora-conditions__rows">';

		$index = 0;

		foreach ( $rules as $rule ) {
			self::render_rule( $list, $index, is_array( $rule ) ? $rule : array() );
			++$index;
		}

		echo '</div>';

		printf(
			'<p><button type="button" class="button button-secondary qevora-conditions__add" data-list="%1$s">%2$s</button></p>',
			esc_attr( $list ),
			esc_html( 'include' === $list ? __( 'Add include condition', 'qevora-layouts' ) : __( 'Add exclude condition', 'qevora-layouts' ) )
		);

		echo '</fieldset>';

		// Hidden markup used by JavaScript to create new rows.
		if ( 'include' === $list ) {
			echo '<template class="qevora-conditions__template">';
			self::render_rule( 'include', 0, array(), true );
			echo '</template>';
		}
	}

	/**
	 * Renders one condition row.
	 *
	 * @since 1.0.0
	 *
	 * @param string                  $list     include|exclude.
	 * @param int                     $index    Row index.
	 * @param array<string,string>    $rule     Stored rule.
	 * @param bool                    $is_empty Whether this is the empty template row.
	 * @return void
	 */
	private static function render_rule( $list, $index, array $rule, $is_empty = false ) {
		$type  = isset( $rule['type'] ) ? (string) $rule['type'] : '';
		$value = isset( $rule['value'] ) ? (string) $rule['value'] : '';
		$terms = isset( $rule['terms'] ) ? (string) $rule['terms'] : '';

		$base = sprintf( 'qevora_conditions[%1$s][%2$d]', $list, (int) $index );

		// Only the value fields of the selected condition are shown, also without JavaScript.
		$active_value = Condition_Registry::value_type( $type );
		$is_template  = (bool) $is_empty;

		printf(
			'<div class="qevora-condition%1$s" data-type="%2$s"%3$s>',
			$is_empty ? ' qevora-condition--empty' : '',
			esc_attr( $type ),
			$is_empty ? ' aria-hidden="true"' : ''
		);

		printf(
			'<label class="screen-reader-text" for="%1$s-type">%2$s</label>',
			esc_attr( $base ),
			esc_html( 'include' === $list ? __( 'Include condition', 'qevora-layouts' ) : __( 'Exclude condition', 'qevora-layouts' ) )
		);

		printf( '<select id="%1$s-type" name="%1$s[type]" class="qevora-condition__type">', esc_attr( $base ) );
		echo '<option value="">' . esc_html__( 'Select a condition', 'qevora-layouts' ) . '</option>';

		foreach ( Condition_Registry::grouped_choices() as $group => $choices ) {
			printf( '<optgroup label="%s">', esc_attr( (string) $group ) );

			foreach ( $choices as $slug => $label ) {
				printf(
					'<option value="%1$s"%2$s>%3$s</option>',
					esc_attr( (string) $slug ),
					selected( $type, (string) $slug, false ),
					esc_html( (string) $label )
				);
			}

			echo '</optgroup>';
		}

		echo '</select>';

		echo '<span class="qevora-condition__values">';

		// Post type / taxonomy selects.
		printf( '<span class="qevora-condition__value" data-value="post_type"%1$s>', $is_template || 'post_type' !== $active_value ? ' hidden' : '' );
		printf(
			'<label class="screen-reader-text" for="%1$s-post-type">%2$s</label>',
			esc_attr( $base ),
			esc_html__( 'Post type', 'qevora-layouts' )
		);
		printf( '<select id="%1$s-post-type" name="%1$s[post_type]" class="qevora-condition__select">', esc_attr( $base ) );
		echo '<option value="">' . esc_html__( 'Select a post type', 'qevora-layouts' ) . '</option>';

		foreach ( self::post_type_choices() as $slug => $label ) {
			$selected = ( 'post_type' === Condition_Registry::value_type( $type ) && $slug === $value );

			printf(
				'<option value="%1$s"%2$s>%3$s</option>',
				esc_attr( (string) $slug ),
				selected( $selected, true, false ),
				esc_html( (string) $label )
			);
		}

		echo '</select></span>';

		printf( '<span class="qevora-condition__value" data-value="taxonomy"%1$s>', $is_template || 'taxonomy' !== $active_value ? ' hidden' : '' );
		printf(
			'<label class="screen-reader-text" for="%1$s-taxonomy">%2$s</label>',
			esc_attr( $base ),
			esc_html__( 'Taxonomy', 'qevora-layouts' )
		);
		printf( '<select id="%1$s-taxonomy" name="%1$s[taxonomy]" class="qevora-condition__select">', esc_attr( $base ) );
		echo '<option value="">' . esc_html__( 'Any taxonomy', 'qevora-layouts' ) . '</option>';

		foreach ( self::taxonomy_choices() as $slug => $label ) {
			$selected = ( 'taxonomy' === $type && $slug === $value );

			printf(
				'<option value="%1$s"%2$s>%3$s</option>',
				esc_attr( (string) $slug ),
				selected( $selected, true, false ),
				esc_html( (string) $label )
			);
		}

		echo '</select></span>';

		// Free form ID lists with a search helper.
		foreach ( array(
			'ids'      => array(
				'label'  => __( 'Post or page IDs', 'qevora-layouts' ),
				'search' => 'posts',
			),
			'term_ids' => array(
				'label'  => __( 'Term IDs', 'qevora-layouts' ),
				'search' => 'terms',
			),
		) as $value_type => $meta ) {
			printf(
				'<span class="qevora-condition__value" data-value="%1$s"%2$s>',
				esc_attr( (string) $value_type ),
				$is_template || (string) $value_type !== $active_value ? ' hidden' : ''
			);
			printf(
				'<label class="screen-reader-text" for="%1$s-%2$s">%3$s</label>',
				esc_attr( $base ),
				esc_attr( (string) $value_type ),
				esc_html( (string) $meta['label'] )
			);
			printf(
				'<input type="text" id="%1$s-%2$s" name="%1$s[%2$s]" value="%3$s" class="qevora-condition__ids regular-text code" placeholder="%4$s" data-search="%5$s" />',
				esc_attr( $base ),
				esc_attr( (string) $value_type ),
				esc_attr( Condition_Registry::value_type( $type ) === $value_type ? $value : '' ),
				esc_attr__( 'Comma separated IDs, e.g. 12, 48', 'qevora-layouts' ),
				esc_attr( (string) $meta['search'] )
			);
			printf(
				'<button type="button" class="button qevora-condition__search" data-search="%1$s" data-target="%2$s-%3$s">%4$s</button>',
				esc_attr( (string) $meta['search'] ),
				esc_attr( $base ),
				esc_attr( (string) $value_type ),
				esc_html__( 'Find', 'qevora-layouts' )
			);
			echo '<span class="qevora-condition__results" role="listbox" aria-live="polite" hidden></span>';
			echo '</span>';
		}

		// Roles as checkboxes.
		printf(
			'<span class="qevora-condition__value" data-value="roles"%1$s><span class="qevora-condition__roles">',
			$is_template || 'roles' !== $active_value ? ' hidden' : ''
		);

		$selected_roles = 'user_role' === $type ? Condition_Sanitizer::sanitize_value( $value, 'roles' ) : '';
		$selected_roles = '' === $selected_roles ? array() : explode( ',', $selected_roles );

		foreach ( self::role_choices() as $slug => $label ) {
			$input_id = $base . '-role-' . $slug;

			printf(
				'<label for="%1$s"><input type="checkbox" id="%1$s" name="%2$s[roles][]" value="%3$s"%4$s /> %5$s</label>',
				esc_attr( $input_id ),
				esc_attr( $base ),
				esc_attr( (string) $slug ),
				checked( in_array( (string) $slug, $selected_roles, true ), true, false ),
				esc_html( (string) $label )
			);
		}

		echo '</span></span>';

		// Terms for a taxonomy rule.
		printf( '<span class="qevora-condition__value" data-value="terms"%1$s>', $is_template || 'taxonomy' !== $type ? ' hidden' : '' );
		printf(
			'<label class="screen-reader-text" for="%1$s-terms">%2$s</label>',
			esc_attr( $base ),
			esc_html__( 'Limit to term IDs (optional)', 'qevora-layouts' )
		);
		printf(
			'<input type="text" id="%1$s-terms" name="%1$s[terms]" value="%2$s" class="qevora-condition__ids regular-text code" placeholder="%3$s" data-search="terms" />',
			esc_attr( $base ),
			esc_attr( $terms ),
			esc_attr__( 'Comma separated term IDs (optional)', 'qevora-layouts' )
		);
		printf(
			'<button type="button" class="button qevora-condition__search" data-search="terms" data-target="%1$s-terms">%2$s</button>',
			esc_attr( $base ),
			esc_html__( 'Find', 'qevora-layouts' )
		);
		echo '<span class="qevora-condition__results" role="listbox" aria-live="polite" hidden></span>';
		echo '</span>';

		echo '</span>'; // .qevora-condition__values

		printf(
			'<span class="qevora-condition__description" aria-live="polite">%s</span>',
			esc_html( self::describe_type( $type ) )
		);

		printf(
			'<button type="button" class="button-link-delete qevora-condition__remove" aria-label="%1$s">%2$s</button>',
			esc_attr__( 'Remove this condition', 'qevora-layouts' ),
			esc_html__( 'Remove', 'qevora-layouts' )
		);

		echo '</div>';
	}

	/**
	 * Returns the help text of a condition type.
	 *
	 * @since 1.0.0
	 *
	 * @param string $type Condition type.
	 * @return string
	 */
	private static function describe_type( $type ) {
		$condition = Condition_Registry::get( (string) $type );

		return isset( $condition['description'] ) ? (string) $condition['description'] : '';
	}

	/**
	 * Renders the content source box.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Post $post Post.
	 * @return void
	 */
	public static function render_content_box( $post ) {
		$layout = Layout::from_post( $post ) ?: new Layout();

		echo '<p><label for="qevora-layout-builder">' . esc_html__( 'Render content with', 'qevora-layouts' ) . '</label> ';
		echo '<select id="qevora-layout-builder" name="qevora_layout[builder]">';

		$builders = array(
			'auto'      => __( 'Auto detect (recommended)', 'qevora-layouts' ),
			'block'     => __( 'Block editor content', 'qevora-layouts' ),
			'elementor' => __( 'Elementor', 'qevora-layouts' ),
			'demo'      => __( 'Bundled demo (PHP templates)', 'qevora-layouts' ),
		);

		foreach ( $builders as $slug => $label ) {
			printf(
				'<option value="%1$s"%2$s>%3$s</option>',
				esc_attr( (string) $slug ),
				selected( $layout->builder, (string) $slug, false ),
				esc_html( (string) $label )
			);
		}

		echo '</select></p>';

		if ( 'demo' === $layout->builder && '' !== $layout->demo_slug ) {
			$qevora_demo = Demo_Registry::get( $layout->demo_slug );

			echo '<p class="description">' . sprintf(
				/* translators: %s: demo name. */
				esc_html__( 'This layout renders the bundled demo "%s" - its own HTML, CSS and JavaScript, kept in the plugin folder. No editor content is needed.', 'qevora-layouts' ),
				esc_html( null !== $qevora_demo ? (string) $qevora_demo['name'] : $layout->demo_slug )
			) . '</p>';

			if ( $layout->menu_id > 0 ) {
				echo '<p class="description"><a href="' . esc_url( admin_url( 'nav-menus.php?menu=' . (int) $layout->menu_id ) ) . '">' . esc_html__( 'Edit the menu created for this demo', 'qevora-layouts' ) . '</a></p>';
			}
		}

		$elementor_ready = self::elementor_is_ready();

		if ( ! $elementor_ready ) {
			echo '<p class="description">' . esc_html__( 'Elementor is not active, so layouts built with it cannot be edited or rendered right now. They fall back to the stored block content.', 'qevora-layouts' ) . '</p>';
		}

		echo '<hr />';

		echo '<fieldset><legend class="screen-reader-text"><span>' . esc_html__( 'Content source', 'qevora-layouts' ) . '</span></legend>';

		printf(
			'<p><label for="qevora-source-self"><input type="radio" id="qevora-source-self" name="qevora_layout[content_source]" value="self"%1$s /> %2$s</label></p>',
			checked( $layout->content_source, 'self', false ),
			esc_html__( 'Use the content of this layout', 'qevora-layouts' )
		);

		printf(
			'<p><label for="qevora-source-reference"><input type="radio" id="qevora-source-reference" name="qevora_layout[content_source]" value="reference"%1$s /> %2$s</label></p>',
			checked( $layout->content_source, 'reference', false ),
			esc_html__( 'Render another post, page or builder template instead', 'qevora-layouts' )
		);

		echo '<div class="qevora-field" data-qevora-reference-fields>';

		printf(
			'<p><label class="screen-reader-text" for="qevora-reference-id">%1$s</label><input type="number" id="qevora-reference-id" name="qevora_layout[reference_id]" min="0" step="1" value="%2$d" class="small-text" /> %1$s</p>',
			esc_html__( 'Referenced post ID', 'qevora-layouts' ),
			(int) $layout->reference_id
		);

		if ( $layout->reference_id > 0 ) {
			$title = get_the_title( $layout->reference_id );

			echo '<p class="description">' . sprintf(
				/* translators: %s: referenced post title or a missing notice. */
				esc_html__( 'Currently referencing: %s', 'qevora-layouts' ),
				esc_html( '' !== $title ? $title : __( '(post not found)', 'qevora-layouts' ) )
			) . '</p>';
		}

		echo '</div>'; // data-qevora-reference-fields

		echo '</fieldset>';

		echo '<p class="description">' . esc_html__( 'Referencing is useful when a page or an Elementor library template already contains the design you want to reuse. Nothing is copied: the layout renders the referenced content.', 'qevora-layouts' ) . '</p>';
	}

	/**
	 * Renders the usage/shortcode box.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Post $post Post.
	 * @return void
	 */
	public static function render_usage_box( $post ) {
		$shortcode = sprintf( '[qevora_layout id="%d"]', (int) $post->ID );

		echo '<p>' . esc_html__( 'Insert this layout anywhere with the shortcode:', 'qevora-layouts' ) . '</p>';
		printf(
			'<p><code><input type="text" readonly="readonly" value="%1$s" class="widefat code" aria-label="%2$s" onfocus="this.select();" /></code></p>',
			esc_attr( $shortcode ),
			esc_attr__( 'Layout shortcode', 'qevora-layouts' )
		);

		echo '<p>' . esc_html__( 'Or add the "Qevora Layout" block in the editor.', 'qevora-layouts' ) . '</p>';

		echo '<p>' . esc_html__( 'In a theme template:', 'qevora-layouts' ) . '</p>';
		printf(
			'<p><code>%s</code></p>',
			esc_html( sprintf( 'qevora_layouts_the_layout( %d );', (int) $post->ID ) )
		);
	}

	/**
	 * Renders the import provenance box.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Post $post Post.
	 * @return void
	 */
	public static function render_import_box( $post ) {
		$template_id   = (string) get_post_meta( $post->ID, Layout_Post_Type::META_TEMPLATE_ID, true );
		$import_source = (string) get_post_meta( $post->ID, Layout_Post_Type::META_IMPORT_SOURCE, true );
		$imported_at   = (string) get_post_meta( $post->ID, Layout_Post_Type::META_IMPORTED_AT, true );

		echo '<dl class="qevora-import-info">';

		echo '<dt>' . esc_html__( 'Template', 'qevora-layouts' ) . '</dt>';
		echo '<dd><code>' . esc_html( $template_id ) . '</code></dd>';

		echo '<dt>' . esc_html__( 'Origin', 'qevora-layouts' ) . '</dt>';
		echo '<dd>' . esc_html( self::origin_label( $import_source ) ) . '</dd>';

		if ( '' !== $imported_at ) {
			echo '<dt>' . esc_html__( 'Imported', 'qevora-layouts' ) . '</dt>';
			echo '<dd>' . esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), get_date_from_gmt( $imported_at ) ) ) . '</dd>';
		}

		echo '</dl>';

		echo '<p class="description">' . esc_html__( 'Importing the same template again is skipped by default so layouts are never overwritten.', 'qevora-layouts' ) . '</p>';
	}

	/**
	 * Returns a label for an import origin.
	 *
	 * @since 1.0.0
	 *
	 * @param string $origin Origin slug.
	 * @return string
	 */
	private static function origin_label( $origin ) {
		$labels = array(
			'bundled' => __( 'Bundled starter template', 'qevora-layouts' ),
			'remote'  => __( 'Remote template library', 'qevora-layouts' ),
			'upload'  => __( 'Uploaded template file', 'qevora-layouts' ),
		);

		return isset( $labels[ $origin ] ) ? $labels[ $origin ] : __( 'Unknown', 'qevora-layouts' );
	}

	/**
	 * Whether a layout carries import provenance.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	private static function has_import_data( $post_id ) {
		return '' !== (string) get_post_meta( (int) $post_id, Layout_Post_Type::META_TEMPLATE_ID, true );
	}

	/**
	 * Whether Elementor can be used right now.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	private static function elementor_is_ready() {
		$adapter = Plugin::is_booted() ? Plugin::instance()->builders()->get( 'elementor' ) : null;

		return null !== $adapter && $adapter->is_available();
	}

	/**
	 * Saves the meta boxes.
	 *
	 * @since 1.0.0
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post.
	 * @return void
	 */
	/**
	 * Returns the bundled demo a layout renders, when it renders one.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id Layout post ID.
	 * @return array<string,mixed>|null Demo manifest or null.
	 */
	private static function demo_for_post( $post_id ) {
		if ( 'demo' !== (string) get_post_meta( (int) $post_id, Layout_Post_Type::META_BUILDER, true ) ) {
			return null;
		}

		return Demo_Registry::get( (string) get_post_meta( (int) $post_id, Layout_Post_Type::META_DEMO, true ) );
	}

	/**
	 * Demo layouts are customised through their settings metabox, not
	 * through blocks: the markup lives in the plugin's PHP templates, so
	 * the block editor has nothing useful to show for them.
	 *
	 * @since 1.0.0
	 *
	 * @param bool     $use Whether the post would use the block editor.
	 * @param \WP_Post $post Post being edited.
	 * @return bool
	 */
	public static function use_block_editor( $use, $post ) {
		if ( $post instanceof \WP_Post && Layout_Post_Type::POST_TYPE === $post->post_type && null !== self::demo_for_post( $post->ID ) ) {
			return false;
		}

		return (bool) $use;
	}

	/**
	 * Explains, on the edit screen of a demo layout, that the content
	 * editor is hidden and where the demo is customised instead.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Post $post Post being edited.
	 * @return void
	 */
	public static function demo_editor_notice( $post ) {
		$demo = $post instanceof \WP_Post ? self::demo_for_post( $post->ID ) : null;

		if ( null === $demo ) {
			return;
		}

		echo "<style>#postdivrich { display: none; }</style>\n";
		echo '<div class="notice notice-info"><p>' . esc_html(
			sprintf(
				/* translators: %s: name of the bundled demo. */
				__( 'This layout is rendered by the bundled "%s" demo - its HTML, CSS and JavaScript live in plugin files, so there is no block content to edit here. Customise the demo in the settings box below; changes appear on the site immediately.', 'qevora-layouts' ),
				(string) $demo['name']
			)
		) . '</p></div>' . "\n";
	}

	/**
	 * Renders the per-demo settings metabox.
	 *
	 * Every field comes from the demo's own `settings.php` schema, so
	 * each demo shows exactly its own options and nothing else.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Post $post Post being edited.
	 * @return void
	 */
	public static function render_demo_settings_box( $post ) {
		$demo = self::demo_for_post( $post->ID );

		if ( null === $demo ) {
			return;
		}

		$slug    = (string) $demo['id'];
		$schema  = Demo_Registry::settings_schema( $slug );
		$saved   = (array) get_post_meta( $post->ID, Layout_Post_Type::META_DEMO_SETTINGS, true );
		$current = Demo_Registry::get_settings( $slug, $saved );

		wp_nonce_field( 'qevora_layouts_demo_settings', 'qevora_layouts_demo_settings_nonce' );

		echo '<div class="qevora-demo-live qevora-demo-live--meta" id="qevora-demo-meta-preview" aria-hidden="true"><div class="qevora-demo-live__inner">';
		echo Demo_Registry::preview_html( $slug, 2, $saved ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Bundled demo markup; escaped inside the demo file.
		echo '</div></div>';
		echo '<p class="description">' . esc_html__( 'Live preview of this demo with the settings saved on this layout. The logo and the button label update the preview as you type; the menu choice and the button switch apply when you update.', 'qevora-layouts' ) . '</p>';
		echo '<script>
( function () {
	"use strict";
	var preview = document.getElementById( "qevora-demo-meta-preview" );
	if ( ! preview ) { return; }
	function bind( name, apply ) {
		var field = document.querySelector( "[name=\"qevora_layout_demo_settings[" + name + "]\"]" );
		if ( ! field ) { return; }
		field.addEventListener( "input", function () { apply( field.value ); } );
	}
	bind( "logo_image", function ( value ) {
		var logo = preview.querySelector( ".qhdb__logo" );
		if ( ! logo ) { return; }
		var img = logo.querySelector( "img" );
		if ( value ) {
			if ( ! img ) {
				img = document.createElement( "img" );
				img.className = "qhdb__logo-img";
				logo.textContent = "";
				logo.appendChild( img );
			}
			img.src = value;
			img.alt = logo.getAttribute( "aria-label" ) || "";
		} else if ( img ) {
			var mark = document.createElement( "span" );
			mark.className = "qhdb__mark";
			mark.setAttribute( "aria-hidden", "true" );
			mark.textContent = logo.getAttribute( "data-qhdb-fallback" ) || "";
			logo.textContent = "";
			logo.appendChild( mark );
		}
	} );
	bind( "button_label", function ( value ) {
		var button = preview.querySelector( ".qhdb__cta" );
		if ( button ) { button.textContent = value; }
	} );
}() );
</script>';

		echo '<p class="description">' . esc_html(
			sprintf(
				/* translators: %s: relative path of the demo template file. */
				__( 'Rendered on every request from %s - plain PHP, no block editor and no page builder. These options are the only content this demo uses.', 'qevora-layouts' ),
				'templates/demos/' . $slug . '.php'
			)
		) . '</p>';

		if ( empty( $schema ) ) {
			echo '<p>' . esc_html__( 'This demo has no customisable options.', 'qevora-layouts' ) . '</p>';
			return;
		}

		echo '<table class="form-table qevora-demo-settings" role="presentation">';

		foreach ( $schema as $key => $field ) {
			$value = isset( $current[ $key ] ) ? $current[ $key ] : $field['default'];
			$id    = 'qevora-demo-setting-' . $key;

			echo '<tr><th scope="row"><label for="' . esc_attr( $id ) . '">' . esc_html( (string) $field['label'] ) . '</label></th><td>';

			switch ( $field['type'] ) {
				case 'checkbox':
					echo '<label><input type="hidden" name="qevora_layout_demo_settings[' . esc_attr( $key ) . ']" value="0" />';
					echo '<input type="checkbox" id="' . esc_attr( $id ) . '" name="qevora_layout_demo_settings[' . esc_attr( $key ) . ']" value="1" ' . checked( $value, true, false ) . ' /> ';
					echo esc_html( (string) $field['label'] ) . '</label>';
					break;
				case 'textarea':
					echo '<textarea id="' . esc_attr( $id ) . '" name="qevora_layout_demo_settings[' . esc_attr( $key ) . ']" rows="4" class="large-text">' . esc_textarea( (string) $value ) . '</textarea>';
					break;
				case 'select':
					echo '<select id="' . esc_attr( $id ) . '" name="qevora_layout_demo_settings[' . esc_attr( $key ) . ']">';
					foreach ( $field['options'] as $option_value => $option_label ) {
						echo '<option value="' . esc_attr( $option_value ) . '" ' . selected( $value, $option_value, false ) . '>' . esc_html( $option_label ) . '</option>';
					}
					echo '</select>';
					break;
				case 'image':
					echo '<input type="url" id="' . esc_attr( $id ) . '" name="qevora_layout_demo_settings[' . esc_attr( $key ) . ']" value="' . esc_attr( (string) $value ) . '" class="regular-text qevora-media-url" />';
					echo ' <button type="button" class="button qevora-media-button" data-qevora-media-target="' . esc_attr( $id ) . '">' . esc_html__( 'Choose image', 'qevora-layouts' ) . '</button>';
					echo ' <button type="button" class="button qevora-media-clear" data-qevora-media-target="' . esc_attr( $id ) . '">' . esc_html__( 'Remove', 'qevora-layouts' ) . '</button>';
					echo '<br /><img class="qevora-media-thumb" src="' . esc_url( (string) $value ) . '" alt="" style="max-width:48px;max-height:48px;border-radius:6px;margin-top:8px;' . ( '' === (string) $value ? 'display:none;' : '' ) . '" />';
					break;
				case 'color':
					echo '<input type="color" id="' . esc_attr( $id ) . '" name="qevora_layout_demo_settings[' . esc_attr( $key ) . ']" value="' . esc_attr( (string) $value ) . '" />';
					break;
				case 'number':
					echo '<input type="number" id="' . esc_attr( $id ) . '" name="qevora_layout_demo_settings[' . esc_attr( $key ) . ']" value="' . esc_attr( (string) $value ) . '" class="small-text" />';
					break;
				case 'url':
					echo '<input type="url" id="' . esc_attr( $id ) . '" name="qevora_layout_demo_settings[' . esc_attr( $key ) . ']" value="' . esc_attr( (string) $value ) . '" class="regular-text" />';
					break;
				default:
					echo '<input type="text" id="' . esc_attr( $id ) . '" name="qevora_layout_demo_settings[' . esc_attr( $key ) . ']" value="' . esc_attr( (string) $value ) . '" class="regular-text" />';
			}

			if ( '' !== (string) $field['help'] ) {
				echo '<p class="description">' . esc_html( (string) $field['help'] ) . '</p>';
			}

			echo '</td></tr>';
		}

		echo '</table>';

		echo '<script>
( function () {
	"use strict";
	function thumb( id, url ) {
		var input = document.getElementById( id );
		var cell = input ? input.closest( "td" ) : null;
		var img = cell ? cell.querySelector( ".qevora-media-thumb" ) : null;
		if ( ! img ) { return; }
		if ( url ) { img.src = url; img.style.display = ""; } else { img.src = ""; img.style.display = "none"; }
	}
	document.addEventListener( "click", function ( event ) {
		var clear = event.target.closest ? event.target.closest( ".qevora-media-clear" ) : null;
		var pick = event.target.closest ? event.target.closest( ".qevora-media-button" ) : null;
		if ( clear ) {
			event.preventDefault();
			var cid = String( clear.getAttribute( "data-qevora-media-target" ) || "" );
			var cinput = document.getElementById( cid );
			if ( cinput ) { cinput.value = ""; cinput.dispatchEvent( new Event( "input", { bubbles: true } ) ); }
			thumb( cid, "" );
			return;
		}
		if ( ! pick ) { return; }
		event.preventDefault();
		var id = String( pick.getAttribute( "data-qevora-media-target" ) || "" );
		if ( ! window.wp || ! window.wp.media ) { return; }
		var frame = window.wp.media( { multiple: false, library: { type: "image" } } );
		frame.on( "select", function () {
			var att = frame.state().get( "selection" ).first().toJSON();
			var url = att && att.url ? String( att.url ) : "";
			var input = document.getElementById( id );
			if ( input ) { input.value = url; input.dispatchEvent( new Event( "input", { bubbles: true } ) ); }
			thumb( id, url );
		} );
		frame.open();
	} );
	document.addEventListener( "input", function ( event ) {
		if ( event.target && event.target.classList && event.target.classList.contains( "qevora-media-url" ) ) {
			thumb( String( event.target.id || "" ), String( event.target.value || "" ) );
		}
	} );
}() );
</script>';
	}

	/**
	 * Saves the per-demo settings metabox.
	 *
	 * @since 1.0.0
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post.
	 * @return void
	 */
	public static function save_demo_settings( $post_id, $post ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		if ( ! isset( $_POST['qevora_layouts_demo_settings_nonce'] ) ) {
			return;
		}

		$nonce = sanitize_text_field( wp_unslash( $_POST['qevora_layouts_demo_settings_nonce'] ) );

		if ( ! wp_verify_nonce( $nonce, 'qevora_layouts_demo_settings' ) ) {
			Logger::warning( 'Demo settings save rejected: invalid nonce.', array( 'post_id' => (int) $post_id ) );

			return;
		}

		if ( ! Capabilities::can_edit_layout( $post_id ) ) {
			Logger::warning( 'Demo settings save rejected: missing capability.', array( 'post_id' => (int) $post_id ) );

			return;
		}

		$demo = self::demo_for_post( $post_id );

		if ( null === $demo ) {
			return;
		}

		$raw = isset( $_POST['qevora_layout_demo_settings'] ) ? wp_unslash( $_POST['qevora_layout_demo_settings'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified above.

		update_post_meta( $post_id, Layout_Post_Type::META_DEMO_SETTINGS, Demo_Registry::sanitize_settings( (string) $demo['id'], $raw ) );
	}

	public static function save( $post_id, $post ) {
		$post_id = (int) $post_id;

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		if ( ! isset( $_POST[ self::NONCE_FIELD ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified right below.
			return;
		}

		$nonce = isset( $_POST[ self::NONCE_FIELD ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::NONCE_FIELD ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified right below.

		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			Logger::warning( 'Layout meta save rejected: invalid nonce.', array( 'post_id' => $post_id ) );

			return;
		}

		if ( ! Capabilities::can_edit_layout( $post_id ) ) {
			Logger::warning( 'Layout meta save rejected: missing capability.', array( 'post_id' => $post_id ) );

			return;
		}

		if ( ! isset( $_POST['qevora_layout'] ) || ! is_array( $_POST['qevora_layout'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified above.
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified above.
		$raw = wp_unslash( $_POST['qevora_layout'] );

		if ( ! is_array( $raw ) ) {
			return;
		}

		$type = Layout_Post_Type::sanitize_type( isset( $raw['type'] ) ? $raw['type'] : 'header' );

		update_post_meta( $post_id, Layout_Post_Type::META_TYPE, $type );
		update_post_meta( $post_id, Layout_Post_Type::META_PRIORITY, Layout_Post_Type::sanitize_priority( isset( $raw['priority'] ) ? $raw['priority'] : 10 ) );
		update_post_meta( $post_id, Layout_Post_Type::META_PLACEMENT, Layout_Post_Type::sanitize_placement( isset( $raw['placement'] ) ? $raw['placement'] : '' ) );
		update_post_meta( $post_id, Layout_Post_Type::META_HOOK, Layout_Post_Type::sanitize_hook_name( isset( $raw['hook'] ) ? $raw['hook'] : '' ) );
		update_post_meta( $post_id, Layout_Post_Type::META_HOOK_PRIORITY, Layout_Post_Type::sanitize_hook_priority( isset( $raw['hook_priority'] ) ? $raw['hook_priority'] : 10 ) );
		update_post_meta( $post_id, Layout_Post_Type::META_BUILDER, Layout_Post_Type::sanitize_builder( isset( $raw['builder'] ) ? $raw['builder'] : 'auto' ) );
		update_post_meta( $post_id, Layout_Post_Type::META_HIDE_THEME_PART, Layout_Post_Type::sanitize_hide_theme_part( isset( $raw['hide_theme_part'] ) ? $raw['hide_theme_part'] : false ) );

		$source = Layout_Post_Type::sanitize_content_source( isset( $raw['content_source'] ) ? $raw['content_source'] : 'self' );

		update_post_meta( $post_id, Layout_Post_Type::META_CONTENT_SOURCE, $source );

		$reference = Layout_Post_Type::sanitize_reference_id( isset( $raw['reference_id'] ) ? $raw['reference_id'] : 0 );

		if ( 'reference' === $source && $reference === $post_id ) {
			$reference = 0;

			Notices::add( __( 'A layout cannot reference itself. The reference was cleared.', 'qevora-layouts' ), 'warning' );
		}

		update_post_meta( $post_id, Layout_Post_Type::META_REFERENCE_ID, $reference );

		self::save_conditions( $post_id );

		/**
		 * Fires after layout meta was saved from the editor.
		 *
		 * @since 1.0.0
		 *
		 * @param int                  $post_id Layout ID.
		 * @param array<string,mixed>  $raw     Sanitized raw input.
		 */
		do_action( 'qevora_layouts_meta_saved', $post_id, $raw );
	}

	/**
	 * Saves the display conditions.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id Layout ID.
	 * @return void
	 */
	private static function save_conditions( $post_id ) {
		$raw = isset( $_POST['qevora_conditions'] ) ? wp_unslash( $_POST['qevora_conditions'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified in save().

		if ( ! is_array( $raw ) ) {
			$raw = array();
		}

		$normalized = array(
			'include' => array(),
			'exclude' => array(),
		);

		foreach ( array( 'include', 'exclude' ) as $list ) {
			if ( empty( $raw[ $list ] ) || ! is_array( $raw[ $list ] ) ) {
				continue;
			}

			foreach ( $raw[ $list ] as $rule ) {
				if ( ! is_array( $rule ) ) {
					continue;
				}

				$normalized_rule = self::normalize_form_rule( $rule );

				if ( null !== $normalized_rule ) {
					$normalized[ $list ][] = $normalized_rule;
				}
			}
		}

		$conditions = Condition_Sanitizer::sanitize( $normalized );

		update_post_meta( $post_id, Layout_Post_Type::META_CONDITIONS, $conditions );
	}

	/**
	 * Maps one submitted condition row onto a normalized rule.
	 *
	 * Each row submits every possible value field; only the one belonging to
	 * the selected condition type is used.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string,mixed> $rule Raw row.
	 * @return array<string,string>|null
	 */
	private static function normalize_form_rule( array $rule ) {
		$type = isset( $rule['type'] ) && is_scalar( $rule['type'] ) ? (string) $rule['type'] : '';

		if ( ! Condition_Registry::exists( $type ) ) {
			return null;
		}

		$value_type = Condition_Registry::value_type( $type );
		$value      = '';

		switch ( $value_type ) {
			case 'post_type':
				$value = isset( $rule['post_type'] ) ? $rule['post_type'] : '';
				break;
			case 'taxonomy':
				$value = isset( $rule['taxonomy'] ) ? $rule['taxonomy'] : '';
				break;
			case 'ids':
				$value = isset( $rule['ids'] ) ? $rule['ids'] : '';
				break;
			case 'term_ids':
				$value = isset( $rule['term_ids'] ) ? $rule['term_ids'] : '';
				break;
			case 'roles':
				$value = isset( $rule['roles'] ) && is_array( $rule['roles'] ) ? $rule['roles'] : '';
				break;
			default:
				$value = '';
				break;
		}

		$normalized = array(
			'type'  => $type,
			'value' => $value,
		);

		if ( 'taxonomy' === $type && ! empty( $rule['terms'] ) ) {
			$normalized['terms'] = $rule['terms'];
		}

		return Condition_Sanitizer::normalize_rule( $normalized );
	}

	/**
	 * Post types offered in condition selects.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string,string>
	 */
	private static function post_type_choices() {
		$types = get_post_types( array( 'public' => true ), 'objects' );
		$out   = array();

		foreach ( (array) $types as $type ) {
			if ( ! isset( $type->name ) || Layout_Post_Type::POST_TYPE === $type->name ) {
				continue;
			}

			$out[ (string) $type->name ] = isset( $type->labels->singular_name ) ? (string) $type->labels->singular_name : (string) $type->name;
		}

		return $out;
	}

	/**
	 * Taxonomies offered in condition selects.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string,string>
	 */
	private static function taxonomy_choices() {
		$taxonomies = get_taxonomies( array( 'public' => true ), 'objects' );
		$out        = array();

		foreach ( (array) $taxonomies as $taxonomy ) {
			if ( ! isset( $taxonomy->name ) ) {
				continue;
			}

			$out[ (string) $taxonomy->name ] = isset( $taxonomy->labels->name ) ? (string) $taxonomy->labels->name : (string) $taxonomy->name;
		}

		return $out;
	}

	/**
	 * Roles offered in condition checkboxes.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string,string>
	 */
	private static function role_choices() {
		$out = array();

		foreach ( (array) wp_roles()->roles as $slug => $role ) {
			$out[ (string) $slug ] = isset( $role['name'] ) ? translate_user_role( (string) $role['name'] ) : (string) $slug;
		}

		return $out;
	}
}
