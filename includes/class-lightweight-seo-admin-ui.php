<?php
/**
 * Shared admin interface components.
 *
 * @since      1.1.0
 * @package    Lightweight_SEO
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Renders the design system components that more than one screen uses.
 *
 * Every method prints escaped markup, so callers pass plain text. The matching
 * styles live in admin/css/lightweight-seo-admin.css and the usage rules in
 * docs/design-system.md.
 */
class Lightweight_SEO_Admin_UI {

	/**
	 * Dashicon and screen reader prefix for each status.
	 *
	 * @since    1.1.0
	 * @return   array
	 */
	private static function get_status_definitions() {
		return array(
			'success' => array( 'dashicons-yes-alt', __( 'Ready:', 'lightweight-seo' ) ),
			'warning' => array( 'dashicons-warning', __( 'Needs attention:', 'lightweight-seo' ) ),
			'pending' => array( 'dashicons-marker', __( 'To do:', 'lightweight-seo' ) ),
			'info'    => array( 'dashicons-info-outline', '' ),
		);
	}

	/**
	 * Print a badge: a one or two word status.
	 *
	 * @since    1.1.0
	 * @param    string    $label    Badge text.
	 * @param    string    $state    Optional. One of success, warning, or info. Default neutral.
	 * @param    string    $url      Optional. Makes the badge a link.
	 * @return   void
	 */
	public static function badge( $label, $state = '', $url = '' ) {
		$class = 'lwseo-badge' . ( in_array( $state, array( 'success', 'warning', 'info' ), true ) ? ' is-' . $state : '' );

		if ( $url ) {
			echo '<a class="' . esc_attr( $class ) . '" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';

			return;
		}

		echo '<span class="' . esc_attr( $class ) . '">' . esc_html( $label ) . '</span>';
	}

	/**
	 * Print a callout: one message that must stand apart from help text.
	 *
	 * @since    1.1.0
	 * @param    string|array    $paragraphs    One paragraph, or several.
	 * @param    string          $state         Optional. info or warning. Default info.
	 * @return   void
	 */
	public static function callout( $paragraphs, $state = 'info' ) {
		$is_warning = 'warning' === $state;

		echo '<div class="lwseo-callout' . ( $is_warning ? ' is-warning' : '' ) . '">';
		echo '<span class="dashicons ' . ( $is_warning ? 'dashicons-warning' : 'dashicons-info-outline' ) . '" aria-hidden="true"></span><div>';

		foreach ( (array) $paragraphs as $paragraph ) {
			echo '<p>' . esc_html( $paragraph ) . '</p>';
		}

		echo '</div></div>';
	}

	/**
	 * Get the screen reader prefix of a status, for scripts that change a row in place.
	 *
	 * @since    1.1.0
	 * @param    string    $state    One of success, warning, pending, or info.
	 * @return   string
	 */
	public static function status_prefix( $state ) {
		$definitions = self::get_status_definitions();

		return isset( $definitions[ $state ] ) ? $definitions[ $state ][1] : '';
	}

	/**
	 * Print one row of a status list.
	 *
	 * @since    1.1.0
	 * @param    string    $state         One of success, warning, pending, or info.
	 * @param    string    $title         What is being reported.
	 * @param    string|array    $text    Optional. Supporting detail: one line, or several.
	 * @param    array     $action        Optional. Link with label, url, and new_tab keys.
	 * @param    array     $attributes    Optional. Extra attributes for the row. A true value prints a bare attribute.
	 * @return   void
	 */
	public static function status( $state, $title, $text = '', $action = array(), $attributes = array() ) {
		$definitions = self::get_status_definitions();
		$state       = isset( $definitions[ $state ] ) ? $state : 'info';

		list( $icon, $prefix ) = $definitions[ $state ];

		echo '<li class="lwseo-status is-' . esc_attr( $state ) . '"';

		foreach ( $attributes as $name => $value ) {
			$name = preg_replace( '/[^a-z0-9-]/', '', strtolower( (string) $name ) );

			if ( '' === $name || false === $value || null === $value ) {
				continue;
			}

			echo ' ' . esc_attr( $name ) . ( true === $value ? '' : '="' . esc_attr( (string) $value ) . '"' );
		}

		echo '>';
		echo '<span class="lwseo-status__icon dashicons ' . esc_attr( $icon ) . '" aria-hidden="true"></span>';
		echo '<div class="lwseo-status__body"><span class="lwseo-status__title">';

		if ( $prefix ) {
			echo '<span class="screen-reader-text">' . esc_html( $prefix ) . ' </span>';
		}

		echo esc_html( $title ) . '</span>';

		foreach ( (array) $text as $line ) {
			if ( '' !== (string) $line ) {
				echo '<p class="lwseo-status__text">' . esc_html( $line ) . '</p>';
			}
		}

		echo '</div>';

		if ( ! empty( $action['url'] ) && ! empty( $action['label'] ) ) {
			$new_tab = ! empty( $action['new_tab'] );

			echo '<a class="lwseo-status__action" href="' . esc_url( $action['url'] ) . '"' . ( $new_tab ? ' target="_blank" rel="noopener noreferrer"' : '' ) . '>' . esc_html( $action['label'] );

			if ( $new_tab ) {
				echo '<span class="screen-reader-text"> ' . esc_html__( '(opens in a new tab)', 'lightweight-seo' ) . '</span>';
			}

			echo '</a>';
		}

		echo '</li>';
	}

	/**
	 * Print a checkbox with its label and optional help text.
	 *
	 * Settings are applied on save, so a checkbox is used instead of a switch,
	 * which would suggest an immediate effect.
	 *
	 * @since    1.1.0
	 * @param    string    $name       Input name.
	 * @param    bool      $checked    Whether the box is ticked.
	 * @param    string    $label      Label text.
	 * @param    string    $help       Optional. Help text shown under the label.
	 * @param    string    $id         Optional. Input ID.
	 * @param    string    $value      Optional. Submitted value, for a box that is one of a list. Default 1.
	 * @return   void
	 */
	public static function choice( $name, $checked, $label, $help = '', $id = '', $value = '1' ) {
		echo '<label class="lwseo-choice"><input type="checkbox"' . ( $id ? ' id="' . esc_attr( $id ) . '"' : '' ) . ' name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '"' . ( $checked ? ' checked="checked"' : '' ) . '>';
		echo '<span>' . esc_html( $label ) . '</span></label>';

		if ( '' !== (string) $help ) {
			echo '<p class="lwseo-help">' . esc_html( $help ) . '</p>';
		}
	}

	/**
	 * Print a matrix: one row per item and one column per setting, with one control in each cell.
	 *
	 * A tick box follows a hidden input of the same name, so the cell always submits
	 * a value and the form posts the shape that is stored.
	 *
	 * @since    1.1.0
	 * @param    array    $args    Matrix settings:
	 *                             first    Heading of the column that names each row.
	 *                             columns  Column headings keyed by column.
	 *                             groups   Groups of rows, each with label and rows. A row has label
	 *                                      and cells keyed by column. A cell has a type:
	 *                                      check  name, checked, on, and off (submitted values), plus
	 *                                             optional note and note_hidden for a remark beside the
	 *                                             box, and toggles_notes when the box hides the row's
	 *                                             notes while it is ticked.
	 *                                      text   name, value, placeholder, and tokens (accepts title variables).
	 *                                      none   optional text; without it the cell reads as having no setting.
	 * @return   void
	 */
	public static function matrix( $args ) {
		$columns = (array) $args['columns'];
		?>
		<div class="lwseo-table-wrap">
			<table class="lwseo-table lwseo-matrix">
				<thead>
					<tr>
						<th scope="col"><?php echo esc_html( $args['first'] ); ?></th>
						<?php foreach ( $columns as $key => $label ) : ?>
							<th scope="col" class="<?php echo esc_attr( 'lwseo-matrix__' . sanitize_key( $key ) ); ?>"><?php echo esc_html( $label ); ?></th>
						<?php endforeach; ?>
					</tr>
				</thead>
				<?php foreach ( (array) $args['groups'] as $group ) : ?>
					<tbody>
						<tr class="lwseo-matrix__group">
							<th scope="rowgroup" colspan="<?php echo esc_attr( (string) ( count( $columns ) + 1 ) ); ?>"><?php echo esc_html( $group['label'] ); ?></th>
						</tr>
						<?php foreach ( (array) $group['rows'] as $row ) : ?>
							<tr>
								<th scope="row"><?php echo esc_html( $row['label'] ); ?></th>
								<?php
								foreach ( $columns as $key => $label ) {
									/* translators: 1: name of a content type, such as Posts, 2: name of a setting, such as Title format. */
									self::matrix_cell( $row['cells'][ $key ] ?? array(), $label, sprintf( _x( '%1$s: %2$s', 'matrix control label', 'lightweight-seo' ), $row['label'], $label ) );
								}
								?>
							</tr>
						<?php endforeach; ?>
					</tbody>
				<?php endforeach; ?>
			</table>
		</div>
		<?php
	}

	/**
	 * Print one cell of a matrix.
	 *
	 * @since    1.1.0
	 * @param    array     $cell      Cell settings, as described in matrix().
	 * @param    string    $column    Column heading, shown beside the control when rows stack on a narrow screen.
	 * @param    string    $label     Accessible name of the control: the row and the column.
	 * @return   void
	 */
	private static function matrix_cell( $cell, $column, $label ) {
		$type = (string) ( $cell['type'] ?? 'none' );

		echo '<td class="' . esc_attr( 'lwseo-matrix__cell lwseo-matrix__cell--' . sanitize_key( $type ) ) . '" data-label="' . esc_attr( $column ) . '">';

		if ( 'check' === $type ) {
			echo '<input type="hidden" name="' . esc_attr( $cell['name'] ) . '" value="' . esc_attr( $cell['off'] ) . '">';
			echo '<label class="lwseo-matrix__check"><input type="checkbox" name="' . esc_attr( $cell['name'] ) . '" value="' . esc_attr( $cell['on'] ) . '"' . ( empty( $cell['checked'] ) ? '' : ' checked="checked"' ) . ( empty( $cell['toggles_notes'] ) ? '' : ' data-lwseo-matrix-notes' ) . '>';
			echo '<span class="screen-reader-text">' . esc_html( $label ) . '</span></label>';

			if ( '' !== (string) ( $cell['note'] ?? '' ) ) {
				echo '<span class="lwseo-matrix__note"' . ( empty( $cell['note_hidden'] ) ? '' : ' hidden' ) . '>' . esc_html( $cell['note'] ) . '</span>';
			}
		} elseif ( 'select' === $type ) {
			echo '<select name="' . esc_attr( $cell['name'] ) . '" aria-label="' . esc_attr( $label ) . '">';

			foreach ( (array) $cell['options'] as $value => $text ) {
				echo '<option value="' . esc_attr( (string) $value ) . '"' . selected( (string) $value, (string) ( $cell['value'] ?? '' ), false ) . '>' . esc_html( $text ) . '</option>';
			}

			echo '</select>';
		} elseif ( 'text' === $type ) {
			echo '<input type="text" name="' . esc_attr( $cell['name'] ) . '" value="' . esc_attr( $cell['value'] ?? '' ) . '" placeholder="' . esc_attr( $cell['placeholder'] ?? '' ) . '" aria-label="' . esc_attr( $label ) . '" autocomplete="off"' . ( empty( $cell['tokens'] ) ? '' : ' data-lwseo-tokens' ) . '>';
		} elseif ( '' !== (string) ( $cell['text'] ?? '' ) ) {
			echo '<span class="lwseo-matrix__note">' . esc_html( $cell['text'] ) . '</span>';
		} else {
			echo '<span aria-hidden="true">–</span><span class="screen-reader-text">' . esc_html__( 'No setting', 'lightweight-seo' ) . '</span>';
		}

		echo '</td>';
	}

	/**
	 * Print the search visibility choice of one page, term, or author: follow its content type, show, or hide.
	 *
	 * The markup is a plain select with no plugin classes, so it also fits the native term and user screens.
	 *
	 * @since    1.1.0
	 * @param    string         $name           Input name.
	 * @param    mixed          $value          Stored choice.
	 * @param    string         $type_label     Plural name of the content type, such as Posts.
	 * @param    bool           $type_hidden    Whether the content type is hidden from search results.
	 * @param    string|null    $id             Optional. Input ID. Defaults to the name; pass an empty string for a
	 *                                          select that WordPress copies, such as one in Quick Edit.
	 * @return   void
	 */
	public static function visibility_select( $name, $value, $type_label, $type_hidden, $id = null ) {
		$id    = null === $id ? $name : $id;
		$value = in_array( (string) $value, array( '1', Lightweight_SEO_Settings::INDEX_OVERRIDE ), true ) ? (string) $value : '';

		if ( $type_hidden ) {
			/* translators: %s: plural name of a content type, such as Posts or Categories. */
			$default = sprintf( __( 'Default for %s (currently hidden)', 'lightweight-seo' ), $type_label );
		} else {
			/* translators: %s: plural name of a content type, such as Posts or Categories. */
			$default = sprintf( __( 'Default for %s (currently shown)', 'lightweight-seo' ), $type_label );
		}
		?>
		<select<?php echo '' === $id ? '' : ' id="' . esc_attr( $id ) . '"'; ?> name="<?php echo esc_attr( $name ); ?>">
			<option value="" <?php selected( $value, '' ); ?>><?php echo esc_html( $default ); ?></option>
			<option value="<?php echo esc_attr( Lightweight_SEO_Settings::INDEX_OVERRIDE ); ?>" <?php selected( $value, Lightweight_SEO_Settings::INDEX_OVERRIDE ); ?>><?php esc_html_e( 'Show in search results', 'lightweight-seo' ); ?></option>
			<option value="1" <?php selected( $value, '1' ); ?>><?php esc_html_e( 'Hide from search results', 'lightweight-seo' ); ?></option>
		</select>
		<?php
	}

	/**
	 * Get the address of the "Inspect a page" card with one address ready to be inspected.
	 *
	 * @since    1.1.0
	 * @param    string    $address    Address on this site.
	 * @return   string
	 */
	public static function inspect_url( $address ) {
		return admin_url( 'admin.php?page=lightweight-seo&tab=tools&inspect=' . rawurlencode( (string) $address ) ) . '#lwseo-inspect';
	}

	/**
	 * Print title variables as buttons that insert themselves into the focused field.
	 *
	 * @since    1.1.0
	 * @param    array    $tokens    Variables such as %title%.
	 * @return   void
	 */
	public static function tokens( $tokens ) {
		echo '<div class="lwseo-tokens"><span>' . esc_html__( 'Insert a variable:', 'lightweight-seo' ) . '</span>';

		foreach ( $tokens as $token ) {
			echo '<button type="button" class="lwseo-token" data-token="' . esc_attr( $token ) . '">' . esc_html( $token ) . '</button>';
		}

		echo '</div>';
	}

	/**
	 * Print a search result preview: site icon and name, address, title, and description.
	 *
	 * @since    1.1.0
	 * @param    array    $args    Preview values: url, title, description, placeholder (shown for an empty
	 *                             description), and live (announce changes made by the editor script).
	 * @return   void
	 */
	public static function search_preview( $args ) {
		$icon        = (string) get_site_icon_url( 64 );
		$description = (string) ( $args['description'] ?? '' );
		?>
		<div class="lwseo-preview lwseo-preview--search"<?php echo empty( $args['live'] ) ? '' : ' aria-live="polite"'; ?>>
			<div class="lwseo-preview__body">
				<div class="lwseo-preview__source">
					<?php if ( '' !== $icon ) : ?>
						<img class="lwseo-preview__icon" src="<?php echo esc_url( $icon ); ?>" alt="">
					<?php endif; ?>
					<div>
						<p class="lwseo-preview__site"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></p>
						<p class="lwseo-preview__url"><?php echo esc_html( (string) ( $args['url'] ?? '' ) ); ?></p>
					</div>
				</div>
				<p class="lwseo-preview__title" data-preview="title"><?php echo esc_html( (string) ( $args['title'] ?? '' ) ); ?></p>
				<p class="lwseo-preview__description" data-preview="description"><?php echo esc_html( '' !== $description ? $description : (string) ( $args['placeholder'] ?? '' ) ); ?></p>
			</div>
		</div>
		<?php
	}

	/**
	 * Print a share preview: image, site name, title, and description.
	 *
	 * @since    1.1.0
	 * @param    array    $args    Preview values: image, title, description, and live.
	 * @return   void
	 */
	public static function share_preview( $args ) {
		$image = (string) ( $args['image'] ?? '' );
		?>
		<div class="lwseo-preview lwseo-preview--social"<?php echo empty( $args['live'] ) ? '' : ' aria-live="polite"'; ?>>
			<div class="lwseo-preview__media">
				<?php if ( '' !== $image ) : ?>
					<img src="<?php echo esc_url( $image ); ?>" alt="">
				<?php endif; ?>
			</div>
			<div class="lwseo-preview__body">
				<p class="lwseo-preview__site"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></p>
				<p class="lwseo-preview__title" data-preview="social-title"><?php echo esc_html( (string) ( $args['title'] ?? '' ) ); ?></p>
				<p class="lwseo-preview__description" data-preview="social-description"><?php echo esc_html( (string) ( $args['description'] ?? '' ) ); ?></p>
			</div>
		</div>
		<?php
	}

	/**
	 * Print a row editor for a field that stores one item per line.
	 *
	 * The caller prints the field itself, which stays the only submitted control and
	 * works on its own. The script fills these rows from it, hides the field, and
	 * writes the same line format back on every change. Row controls carry no name,
	 * so they are never submitted.
	 *
	 * @since    1.1.0
	 * @param    array    $args    Editor settings:
	 *                             format   Key of the line format in lightweight-seo-formats.js.
	 *                             field    ID of the field the rows are read from and written to.
	 *                             columns  Columns in order. Each has key, label, and type (text, url,
	 *                                      select, or page), plus optional placeholder, options, and list.
	 *                                      A page column stores a type and an ID, chosen by searching.
	 *                             labels   Optional. Names of the pages already stored, keyed "type:id".
	 *                             add      Label of the button that adds a row.
	 *                             item     Name of one row with a %d for its number.
	 *                             empty    Text shown while there are no rows.
	 *                             removed  Announcement made when a row is removed.
	 *                             filter   Optional. Label of the filter shown for a long list.
	 *                             limit    Optional. Most rows the setting keeps.
	 *                             hints    Optional. Messages keyed by the hint the format reports.
	 * @return   void
	 */
	public static function rows( $args ) {
		$format  = sanitize_key( $args['format'] );
		$columns = (array) $args['columns'];
		$limit   = absint( $args['limit'] ?? 0 );
		?>
		<div class="lwseo-rows lwseo-rows--<?php echo esc_attr( $format ); ?>" data-format="<?php echo esc_attr( $format ); ?>" data-field="<?php echo esc_attr( $args['field'] ); ?>" data-limit="<?php echo esc_attr( (string) $limit ); ?>" data-item="<?php echo esc_attr( $args['item'] ); ?>" data-removed="<?php echo esc_attr( $args['removed'] ); ?>" data-hints="<?php echo esc_attr( wp_json_encode( (object) ( $args['hints'] ?? array() ) ) ); ?>" data-labels="<?php echo esc_attr( wp_json_encode( (object) ( $args['labels'] ?? array() ) ) ); ?>" <?php self::search_attributes(); ?> hidden>
			<div class="lwseo-rows__editor">
				<?php if ( ! empty( $args['filter'] ) ) : ?>
					<p class="lwseo-rows__filter" hidden>
						<label class="screen-reader-text" for="<?php echo esc_attr( $args['field'] . '_filter' ); ?>"><?php echo esc_html( $args['filter'] ); ?></label>
						<input type="search" id="<?php echo esc_attr( $args['field'] . '_filter' ); ?>" class="regular-text" placeholder="<?php echo esc_attr( $args['filter'] ); ?>">
					</p>
				<?php endif; ?>
				<div class="lwseo-rows__head" aria-hidden="true">
					<?php foreach ( $columns as $column ) : ?>
						<span><?php echo esc_html( $column['label'] ); ?></span>
					<?php endforeach; ?>
					<span></span>
				</div>
				<div class="lwseo-rows__list"></div>
				<p class="lwseo-help lwseo-rows__empty"><?php echo esc_html( $args['empty'] ); ?></p>
				<div class="lwseo-buttons lwseo-rows__actions">
					<button type="button" class="button lwseo-rows__add"><?php echo esc_html( $args['add'] ); ?></button>
					<span class="lwseo-rows__count" data-template="<?php echo esc_attr( self::count_template() ); ?>"></span>
				</div>
			</div>
			<?php self::editor_footer(); ?>
			<template>
				<div class="lwseo-rows__row" role="group">
					<div class="lwseo-rows__cells">
						<?php foreach ( $columns as $column ) : ?>
							<span class="lwseo-rows__cell" data-label="<?php echo esc_attr( $column['label'] ); ?>">
								<?php if ( 'select' === $column['type'] ) : ?>
									<select data-field="<?php echo esc_attr( $column['key'] ); ?>" aria-label="<?php echo esc_attr( $column['label'] ); ?>">
										<?php foreach ( (array) $column['options'] as $value => $label ) : ?>
											<option value="<?php echo esc_attr( (string) $value ); ?>"><?php echo esc_html( $label ); ?></option>
										<?php endforeach; ?>
									</select>
								<?php elseif ( 'page' === $column['type'] ) : ?>
									<span class="lwseo-pagecell" data-mirror="<?php echo esc_attr( $column['mirror'] ?? '' ); ?>">
										<input type="hidden" data-field="type" value="post">
										<input type="hidden" data-field="id" value="">
										<button type="button" class="button lwseo-pagecell__current" aria-expanded="false"><?php echo esc_html( $column['choose'] ); ?></button>
										<span class="lwseo-pagecell__search" hidden>
											<input type="search" aria-label="<?php echo esc_attr( $column['search'] ); ?>" placeholder="<?php echo esc_attr( $column['search'] ); ?>" autocomplete="off">
											<ul class="lwseo-picker__results"></ul>
											<span class="lwseo-picker__status" role="status"></span>
										</span>
									</span>
								<?php else : ?>
									<input type="<?php echo 'url' === $column['type'] ? 'url' : 'text'; ?>" data-field="<?php echo esc_attr( $column['key'] ); ?>" aria-label="<?php echo esc_attr( $column['label'] ); ?>" placeholder="<?php echo esc_attr( $column['placeholder'] ?? '' ); ?>"<?php echo empty( $column['list'] ) ? '' : ' list="' . esc_attr( $column['list'] ) . '"'; ?> autocomplete="off">
								<?php endif; ?>
							</span>
						<?php endforeach; ?>
						<button type="button" class="button-link lwseo-rows__remove">
							<span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
							<span class="screen-reader-text"><?php esc_html_e( 'Remove', 'lightweight-seo' ); ?></span>
						</button>
					</div>
					<p class="lwseo-rows__hint" hidden></p>
				</div>
			</template>
		</div>
		<?php
	}

	/**
	 * Get the template for "3 of 50": how many items a setting holds out of the most it keeps.
	 *
	 * @since    1.1.0
	 * @return   string
	 */
	private static function count_template() {
		/* translators: 1: number of items, 2: most items the setting keeps. */
		return __( '%1$d of %2$d', 'lightweight-seo' );
	}

	/**
	 * Print the messages a page search shows, as attributes of the element that holds the search.
	 *
	 * @since    1.1.0
	 * @return   void
	 */
	private static function search_attributes() {
		echo 'data-search-searching="' . esc_attr( __( 'Searching…', 'lightweight-seo' ) ) . '"';
		echo ' data-search-none="' . esc_attr( __( 'Nothing matches that search.', 'lightweight-seo' ) ) . '"';
		echo ' data-search-error="' . esc_attr( __( 'Search is not available right now. Use “Edit as text” instead.', 'lightweight-seo' ) ) . '"';
	}

	/**
	 * Print a page picker for a field that stores a list of post IDs.
	 *
	 * Like rows(), it reads and writes a field the caller prints, which stays the
	 * only submitted control. Pages are found by searching this site's REST API
	 * from the browser; nothing is requested by PHP.
	 *
	 * @since    1.1.0
	 * @param    array    $args    Picker settings:
	 *                             field    ID of the field that stores the IDs.
	 *                             items    Stored pages keyed by ID, each with title and an optional note.
	 *                             limit    Most pages the setting keeps.
	 *                             search   Label of the search box.
	 *                             chosen   Name of the list of chosen pages.
	 *                             empty    Text shown while nothing is chosen.
	 *                             removed  Announcement made when a page is removed.
	 *                             fallback Name for a page whose title is unknown, with a %d for its ID.
	 * @return   void
	 */
	public static function picker( $args ) {
		$field = (string) $args['field'];
		?>
		<div class="lwseo-picker" data-field="<?php echo esc_attr( $field ); ?>" data-limit="<?php echo esc_attr( (string) absint( $args['limit'] ?? 0 ) ); ?>" data-items="<?php echo esc_attr( wp_json_encode( (object) ( $args['items'] ?? array() ) ) ); ?>" data-fallback="<?php echo esc_attr( $args['fallback'] ); ?>" data-removed="<?php echo esc_attr( $args['removed'] ); ?>" data-remove="<?php echo esc_attr( __( 'Remove', 'lightweight-seo' ) ); ?>" <?php self::search_attributes(); ?> hidden>
			<div class="lwseo-picker__editor">
				<ul class="lwseo-picker__chosen" aria-label="<?php echo esc_attr( $args['chosen'] ); ?>"></ul>
				<p class="lwseo-help lwseo-picker__empty"><?php echo esc_html( $args['empty'] ); ?></p>
				<p class="lwseo-picker__search">
					<label class="screen-reader-text" for="<?php echo esc_attr( $field . '_search' ); ?>"><?php echo esc_html( $args['search'] ); ?></label>
					<input type="search" id="<?php echo esc_attr( $field . '_search' ); ?>" class="regular-text" placeholder="<?php echo esc_attr( $args['search'] ); ?>" autocomplete="off">
					<span class="lwseo-rows__count" data-template="<?php echo esc_attr( self::count_template() ); ?>"></span>
				</p>
				<ul class="lwseo-picker__results"></ul>
				<p class="lwseo-help lwseo-picker__status" role="status"></p>
			</div>
			<?php self::editor_footer(); ?>
		</div>
		<?php
	}

	/**
	 * Print what every editor over a raw field ends with: the switch back to the
	 * field, the note for lines kept as text, and the region that announces changes.
	 *
	 * @since    1.1.0
	 * @return   void
	 */
	private static function editor_footer() {
		/* translators: %d: number of lines that are kept as text. */
		$kept_one = _n( '%d line could not be shown as a row and is kept as text.', '%d lines could not be shown as rows and are kept as text.', 1, 'lightweight-seo' );
		/* translators: %d: number of lines that are kept as text. */
		$kept_many = _n( '%d line could not be shown as a row and is kept as text.', '%d lines could not be shown as rows and are kept as text.', 2, 'lightweight-seo' );
		?>
		<p class="lwseo-rows__footer">
			<button type="button" class="button-link lwseo-rows__toggle" data-as-text="<?php echo esc_attr( __( 'Edit as text', 'lightweight-seo' ) ); ?>" data-as-rows="<?php echo esc_attr( __( 'Edit as rows', 'lightweight-seo' ) ); ?>"><?php esc_html_e( 'Edit as text', 'lightweight-seo' ); ?></button>
			<span class="lwseo-rows__leftover" data-one="<?php echo esc_attr( $kept_one ); ?>" data-many="<?php echo esc_attr( $kept_many ); ?>" hidden></span>
		</p>
		<span class="screen-reader-text lwseo-rows__live" aria-live="polite"></span>
		<?php
	}

	/**
	 * Print an opening hours editor: one row per day, each with any number of time ranges.
	 *
	 * Like rows(), it reads and writes a field the caller prints, which stays the
	 * only submitted control.
	 *
	 * @since    1.1.0
	 * @param    string    $field    ID of the field that stores the opening hours.
	 * @return   void
	 */
	public static function hours( $field ) {
		global $wp_locale;

		// Schema.org day codes in week order, with the weekday index WordPress uses for its names.
		$days = array(
			'Mo' => 1,
			'Tu' => 2,
			'We' => 3,
			'Th' => 4,
			'Fr' => 5,
			'Sa' => 6,
			'Su' => 0,
		);
		?>
		<div class="lwseo-hours" data-field="<?php echo esc_attr( $field ); ?>" data-removed="<?php echo esc_attr( __( 'Hours removed.', 'lightweight-seo' ) ); ?>" data-copied="<?php echo esc_attr( __( 'Monday’s hours copied to Tuesday to Friday.', 'lightweight-seo' ) ); ?>" hidden>
			<div class="lwseo-hours__editor">
				<?php foreach ( $days as $code => $weekday ) : ?>
					<div class="lwseo-hours__day" data-day="<?php echo esc_attr( $code ); ?>" role="group" aria-label="<?php echo esc_attr( $wp_locale->get_weekday( $weekday ) ); ?>">
						<span class="lwseo-hours__name"><?php echo esc_html( $wp_locale->get_weekday( $weekday ) ); ?></span>
						<div class="lwseo-hours__times">
							<div class="lwseo-hours__ranges"></div>
							<span class="lwseo-hours__closed"><?php esc_html_e( 'Closed', 'lightweight-seo' ); ?></span>
						</div>
						<button type="button" class="button-link lwseo-hours__add"><?php esc_html_e( 'Add hours', 'lightweight-seo' ); ?></button>
					</div>
				<?php endforeach; ?>
				<p class="lwseo-hours__tools">
					<button type="button" class="button-link lwseo-hours__copy"><?php esc_html_e( 'Copy Monday to Tuesday–Friday', 'lightweight-seo' ); ?></button>
				</p>
			</div>
			<?php self::editor_footer(); ?>
			<template>
				<span class="lwseo-hours__range">
					<input type="time" data-time="open" aria-label="<?php echo esc_attr( __( 'Opens', 'lightweight-seo' ) ); ?>">
					<span aria-hidden="true">–</span>
					<input type="time" data-time="close" aria-label="<?php echo esc_attr( __( 'Closes', 'lightweight-seo' ) ); ?>">
					<button type="button" class="button-link lwseo-rows__remove">
						<span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
						<span class="screen-reader-text"><?php esc_html_e( 'Remove', 'lightweight-seo' ); ?></span>
					</button>
				</span>
			</template>
		</div>
		<?php
	}

	/**
	 * Print an image field: URL input, media picker, and thumbnail.
	 *
	 * @since    1.1.0
	 * @param    array    $args    Field settings: url, id, url_name, id_name, url_id, id_id, alt, and width.
	 *                             Leave id_name out for an image stored as a URL only.
	 * @return   void
	 */
	public static function image_field( $args ) {
		$url   = (string) ( $args['url'] ?? '' );
		$width = absint( $args['width'] ?? 0 );
		?>
		<div class="lwseo-image"<?php echo $width ? ' data-width="' . esc_attr( (string) $width ) . '"' : ''; ?>>
			<?php if ( ! empty( $args['id_name'] ) ) : ?>
				<input type="hidden" id="<?php echo esc_attr( $args['id_id'] ?? '' ); ?>" name="<?php echo esc_attr( $args['id_name'] ); ?>" value="<?php echo esc_attr( absint( $args['id'] ?? 0 ) ); ?>" class="lwseo-image__id">
			<?php endif; ?>
			<div class="lwseo-image__row">
				<input type="text" id="<?php echo esc_attr( $args['url_id'] ); ?>" name="<?php echo esc_attr( $args['url_name'] ); ?>" value="<?php echo esc_url( $url ); ?>" class="lwseo-image__url">
				<button type="button" class="button lwseo-image__choose"><?php esc_html_e( 'Choose image', 'lightweight-seo' ); ?></button>
			</div>
			<div class="lwseo-image__preview" data-alt="<?php echo esc_attr( $args['alt'] ?? '' ); ?>" <?php echo '' === $url ? 'hidden' : ''; ?>>
				<?php if ( '' !== $url ) : ?>
					<img src="<?php echo esc_url( $url ); ?>" alt="<?php echo esc_attr( $args['alt'] ?? '' ); ?>">
				<?php endif; ?>
			</div>
		</div>
		<?php
	}
}
