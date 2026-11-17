<?php
/**
 * SEO column, Quick Edit, and Bulk Edit in the post lists.
 *
 * @since      1.1.0
 * @package    Lightweight_SEO
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Shows a few SEO facts for each row of a post list and lets the list's own
 * Quick Edit and Bulk Edit change them.
 *
 * The lists are WordPress screens, so the markup here is the native list and
 * inline-edit markup, and no plugin stylesheet is loaded.
 */
class Lightweight_SEO_Post_List {

	/** Key of the column in the list table. */
	const COLUMN = 'lightweight_seo';

	/** Nonce action shared by the Quick Edit and Bulk Edit fields. */
	const NONCE_ACTION = 'lightweight_seo_post_list';

	/** Name of the nonce field in both forms. */
	const NONCE_NAME = 'lightweight_seo_list_nonce';

	/**
	 * Shared settings service.
	 *
	 * @since    1.1.0
	 * @access   private
	 * @var      Lightweight_SEO_Settings    $settings
	 */
	private $settings;

	/**
	 * Shared post meta service.
	 *
	 * @since    1.1.0
	 * @access   private
	 * @var      Lightweight_SEO_Post_Meta    $post_meta
	 */
	private $post_meta;

	/**
	 * Initialize the list integration.
	 *
	 * @since    1.1.0
	 * @param    Lightweight_SEO_Settings     $settings     Shared settings service.
	 * @param    Lightweight_SEO_Post_Meta    $post_meta    Shared post meta service.
	 */
	public function __construct( $settings, $post_meta ) {
		$this->settings  = $settings;
		$this->post_meta = $post_meta;

		// Post types are known once WordPress has finished registering them.
		add_action( 'admin_init', array( $this, 'register_hooks' ) );
	}

	/**
	 * Get the post types whose list shows the column.
	 *
	 * The Media Library is a different kind of list with no Quick Edit, so it is left out.
	 *
	 * @since    1.1.0
	 * @return   array
	 */
	public function get_post_types() {
		return array_values( array_diff( $this->post_meta->get_supported_post_types(), array( 'attachment' ) ) );
	}

	/**
	 * Register the column, the inline-edit fields, and the save handler.
	 *
	 * @since    1.1.0
	 * @return   void
	 */
	public function register_hooks() {
		foreach ( $this->get_post_types() as $post_type ) {
			add_filter( 'manage_' . $post_type . '_posts_columns', array( $this, 'add_column' ) );
			add_action( 'manage_' . $post_type . '_posts_custom_column', array( $this, 'render_column' ), 10, 2 );
		}

		add_action( 'quick_edit_custom_box', array( $this, 'render_quick_edit' ), 10, 2 );
		add_action( 'bulk_edit_custom_box', array( $this, 'render_bulk_edit' ), 10, 2 );
		add_action( 'save_post', array( $this, 'save' ) );
	}

	/**
	 * Add the SEO column before the date, or last when the list has no date.
	 *
	 * @since    1.1.0
	 * @param    array    $columns    List table columns.
	 * @return   array
	 */
	public function add_column( $columns ) {
		$columns = (array) $columns;
		$label   = __( 'SEO', 'lightweight-seo' );

		if ( ! isset( $columns['date'] ) ) {
			$columns[ self::COLUMN ] = $label;

			return $columns;
		}

		$with_column = array();

		foreach ( $columns as $key => $title ) {
			if ( 'date' === $key ) {
				$with_column[ self::COLUMN ] = $label;
			}

			$with_column[ $key ] = $title;
		}

		return $with_column;
	}

	/**
	 * Print the SEO facts of one row, and the saved values Quick Edit starts from.
	 *
	 * Nothing is queried here: WordPress has already loaded the post and its meta for the row.
	 *
	 * @since    1.1.0
	 * @param    string    $column     Column being printed.
	 * @param    int       $post_id    Post of the row.
	 * @return   void
	 */
	public function render_column( $column, $post_id ) {
		if ( self::COLUMN !== $column ) {
			return;
		}

		$post        = get_post( $post_id );
		$meta        = $this->post_meta->get_all( $post_id );
		$title       = trim( (string) ( $meta['seo_title'] ?? '' ) );
		$description = trim( (string) ( $meta['seo_description'] ?? '' ) );
		$own_choice  = $this->get_own_choice( $meta );
		$source      = trim( wp_strip_all_tags( (string) ( $post->post_excerpt ?? '' ) . ' ' . (string) ( $post->post_content ?? '' ) ) );
		$lines       = array( '' !== $title ? __( 'Title: own', 'lightweight-seo' ) : __( 'Title: default', 'lightweight-seo' ) );

		if ( '' !== $description ) {
			$lines[] = __( 'Description: own', 'lightweight-seo' );
		} elseif ( '' !== $source ) {
			$lines[] = __( 'Description: from content', 'lightweight-seo' );
		} else {
			$lines[] = __( 'Description: none', 'lightweight-seo' );
		}

		if ( $this->settings->resolve_noindex( $meta['seo_noindex'] ?? '', $this->settings->is_post_type_noindex( (string) ( $post->post_type ?? '' ) ) ) ) {
			$lines[] = __( 'Hidden from search', 'lightweight-seo' );
		}

		echo implode( '<br>', array_map( 'esc_html', $lines ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Every line is escaped by esc_html before the lines are joined.
		echo '<span class="hidden lightweight-seo-list-data" data-title="' . esc_attr( $title ) . '" data-description="' . esc_attr( $description ) . '" data-noindex="' . esc_attr( $own_choice ) . '"></span>';
	}

	/**
	 * Print the SEO fields of Quick Edit.
	 *
	 * The fields start empty and are filled from the row by the list script, which
	 * also sets the "loaded" flag. Without that flag nothing is saved.
	 *
	 * @since    1.1.0
	 * @param    string    $column       Column the box belongs to.
	 * @param    string    $post_type    Post type of the list.
	 * @return   void
	 */
	public function render_quick_edit( $column, $post_type ) {
		if ( self::COLUMN !== $column || ! in_array( $post_type, $this->get_post_types(), true ) ) {
			return;
		}
		?>
		<fieldset class="inline-edit-col-left lightweight-seo-quick-edit">
			<legend class="inline-edit-legend"><?php esc_html_e( 'SEO', 'lightweight-seo' ); ?></legend>
			<div class="inline-edit-col">
				<input type="hidden" name="<?php echo esc_attr( self::NONCE_NAME ); ?>" value="<?php echo esc_attr( wp_create_nonce( self::NONCE_ACTION ) ); ?>">
				<input type="hidden" name="lightweight_seo_list_loaded" value="">
				<label>
					<span class="title"><?php esc_html_e( 'SEO title', 'lightweight-seo' ); ?></span>
					<span class="input-text-wrap"><input type="text" name="lightweight_seo_list_title" value="" autocomplete="off"></span>
				</label>
				<label>
					<span class="title"><?php esc_html_e( 'Description', 'lightweight-seo' ); ?></span>
					<span class="input-text-wrap"><textarea name="lightweight_seo_list_description" rows="2"></textarea></span>
				</label>
				<label>
					<span class="title"><?php esc_html_e( 'Search', 'lightweight-seo' ); ?></span>
					<?php Lightweight_SEO_Admin_UI::visibility_select( 'lightweight_seo_list_noindex', '', $this->get_type_label( $post_type ), $this->settings->is_post_type_noindex( $post_type ), '' ); ?>
				</label>
			</div>
		</fieldset>
		<?php
	}

	/**
	 * Print the one SEO field of Bulk Edit: search visibility.
	 *
	 * Titles and descriptions are left out, because giving many pages the same
	 * one creates duplicates.
	 *
	 * @since    1.1.0
	 * @param    string    $column       Column the box belongs to.
	 * @param    string    $post_type    Post type of the list.
	 * @return   void
	 */
	public function render_bulk_edit( $column, $post_type ) {
		if ( self::COLUMN !== $column || ! in_array( $post_type, $this->get_post_types(), true ) ) {
			return;
		}

		$type_label = $this->get_type_label( $post_type );

		if ( $this->settings->is_post_type_noindex( $post_type ) ) {
			/* translators: %s: plural name of a content type, such as Posts or Categories. */
			$default = sprintf( __( 'Default for %s (currently hidden)', 'lightweight-seo' ), $type_label );
		} else {
			/* translators: %s: plural name of a content type, such as Posts or Categories. */
			$default = sprintf( __( 'Default for %s (currently shown)', 'lightweight-seo' ), $type_label );
		}
		?>
		<fieldset class="inline-edit-col-right lightweight-seo-bulk-edit">
			<div class="inline-edit-col">
				<input type="hidden" name="<?php echo esc_attr( self::NONCE_NAME ); ?>" value="<?php echo esc_attr( wp_create_nonce( self::NONCE_ACTION ) ); ?>">
				<div class="inline-edit-group wp-clearfix">
					<label class="alignleft">
						<span class="title"><?php esc_html_e( 'Search', 'lightweight-seo' ); ?></span>
						<select name="lightweight_seo_list_bulk_noindex">
							<option value=""><?php esc_html_e( '— No change —', 'lightweight-seo' ); ?></option>
							<option value="default"><?php echo esc_html( $default ); ?></option>
							<option value="show"><?php esc_html_e( 'Show in search results', 'lightweight-seo' ); ?></option>
							<option value="hide"><?php esc_html_e( 'Hide from search results', 'lightweight-seo' ); ?></option>
						</select>
					</label>
				</div>
			</div>
		</fieldset>
		<?php
	}

	/**
	 * Save the SEO fields submitted by Quick Edit or Bulk Edit.
	 *
	 * WordPress has already checked its own nonce for the request. This handler
	 * also requires the nonce printed with the SEO fields and the right to edit
	 * this post, and it touches only the fields the list shows.
	 *
	 * @since    1.1.0
	 * @param    int    $post_id    Post being saved.
	 * @return   void
	 */
	public function save( $post_id ) {
		if ( ! isset( $_REQUEST[ self::NONCE_NAME ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST[ self::NONCE_NAME ] ) ), self::NONCE_ACTION ) ) {
			return;
		}

		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		if ( ! in_array( get_post_type( $post_id ), $this->get_post_types(), true ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( isset( $_REQUEST['bulk_edit'] ) ) {
			$this->save_bulk_edit( $post_id );

			return;
		}

		// Quick Edit: the script sets this once it has filled the fields from the row, so an unfilled form never saves blanks.
		if ( '1' !== sanitize_text_field( wp_unslash( $_POST['lightweight_seo_list_loaded'] ?? '' ) ) ) {
			return;
		}

		if ( isset( $_POST['lightweight_seo_list_title'] ) ) {
			$this->post_meta->update( $post_id, 'seo_title', Lightweight_SEO_Text::sanitize_template( wp_unslash( $_POST['lightweight_seo_list_title'] ) ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized by Lightweight_SEO_Text, which wraps the WordPress text sanitizer so title variables survive it.
		}

		if ( isset( $_POST['lightweight_seo_list_description'] ) ) {
			$this->post_meta->update( $post_id, 'seo_description', Lightweight_SEO_Text::sanitize_textarea_template( wp_unslash( $_POST['lightweight_seo_list_description'] ) ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized by Lightweight_SEO_Text, which wraps the WordPress text sanitizer so title variables survive it.
		}

		if ( isset( $_POST['lightweight_seo_list_noindex'] ) ) {
			$this->save_choice( $post_id, $this->settings->normalize_noindex_choice( sanitize_text_field( wp_unslash( $_POST['lightweight_seo_list_noindex'] ) ) ) );
		}
	}

	/**
	 * Store the search visibility choice of one post.
	 *
	 * A page hidden before 1.1 also told search engines not to follow its links,
	 * and keeps doing so until it is saved again; it is recognised by having no
	 * stored nofollow value. A page hidden from the list for the first time is not
	 * one of those, so its nofollow is recorded as off, as the editor does.
	 *
	 * @since    1.1.0
	 * @param    int       $post_id    Post being saved.
	 * @param    string    $choice     Normalized choice: 1 to hide, the show value, or 0 to follow the content type.
	 * @return   void
	 */
	private function save_choice( $post_id, $choice ) {
		$meta = $this->post_meta->get_all( $post_id );

		if ( '1' === $choice && '1' !== (string) ( $meta['seo_noindex'] ?? '' ) && '' === (string) ( $meta['seo_nofollow'] ?? '' ) ) {
			$this->post_meta->update( $post_id, 'seo_nofollow', '0' );
		}

		$this->post_meta->update( $post_id, 'seo_noindex', $choice );
	}

	/**
	 * Apply the Bulk Edit search visibility choice to one of the selected posts.
	 *
	 * @since    1.1.0
	 * @param    int    $post_id    Post being saved.
	 * @return   void
	 */
	private function save_bulk_edit( $post_id ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- save() verified the nonce printed with this field before calling here.
		$choice = sanitize_key( wp_unslash( $_REQUEST['lightweight_seo_list_bulk_noindex'] ?? '' ) );
		$values = array(
			'default' => '0',
			'show'    => Lightweight_SEO_Settings::INDEX_OVERRIDE,
			'hide'    => '1',
		);

		// "No change", or anything unexpected, leaves every selected post as it is.
		if ( isset( $values[ $choice ] ) ) {
			$this->save_choice( $post_id, $values[ $choice ] );
		}
	}

	/**
	 * Get the stored search visibility choice of a post in the form the select uses.
	 *
	 * @since    1.1.0
	 * @param    array    $meta    Stored SEO meta of the post.
	 * @return   string Empty to follow the content type, 1 to hide, or the show value.
	 */
	private function get_own_choice( $meta ) {
		$choice = (string) ( $meta['seo_noindex'] ?? '' );

		return in_array( $choice, array( '1', Lightweight_SEO_Settings::INDEX_OVERRIDE ), true ) ? $choice : '';
	}

	/**
	 * Get the plural name of a post type.
	 *
	 * @since    1.1.0
	 * @param    string    $post_type    Post type name.
	 * @return   string
	 */
	private function get_type_label( $post_type ) {
		$object = get_post_type_object( $post_type );

		return (string) ( $object->labels->name ?? $post_type );
	}
}
