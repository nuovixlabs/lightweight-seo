<?php
/**
 * Shared admin interface components.
 *
 * @since      1.2.0
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
	 * @since    1.2.0
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
	 * @since    1.2.0
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
	 * @since    1.2.0
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
	 * Print one row of a status list.
	 *
	 * @since    1.2.0
	 * @param    string    $state     One of success, warning, pending, or info.
	 * @param    string    $title     What is being reported.
	 * @param    string    $text      Optional. Supporting detail.
	 * @param    array     $action    Optional. Link with label, url, and new_tab keys.
	 * @return   void
	 */
	public static function status( $state, $title, $text = '', $action = array() ) {
		$definitions = self::get_status_definitions();
		$state       = isset( $definitions[ $state ] ) ? $state : 'info';

		list( $icon, $prefix ) = $definitions[ $state ];

		echo '<li class="lwseo-status is-' . esc_attr( $state ) . '">';
		echo '<span class="lwseo-status__icon dashicons ' . esc_attr( $icon ) . '" aria-hidden="true"></span>';
		echo '<div class="lwseo-status__body"><span class="lwseo-status__title">';

		if ( $prefix ) {
			echo '<span class="screen-reader-text">' . esc_html( $prefix ) . ' </span>';
		}

		echo esc_html( $title ) . '</span>';

		if ( '' !== (string) $text ) {
			echo '<p class="lwseo-status__text">' . esc_html( $text ) . '</p>';
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
	 * @since    1.2.0
	 * @param    string    $name       Input name.
	 * @param    bool      $checked    Whether the box is ticked.
	 * @param    string    $label      Label text.
	 * @param    string    $help       Optional. Help text shown under the label.
	 * @param    string    $id         Optional. Input ID.
	 * @return   void
	 */
	public static function choice( $name, $checked, $label, $help = '', $id = '' ) {
		echo '<label class="lwseo-choice"><input type="checkbox"' . ( $id ? ' id="' . esc_attr( $id ) . '"' : '' ) . ' name="' . esc_attr( $name ) . '" value="1"' . ( $checked ? ' checked="checked"' : '' ) . '>';
		echo '<span>' . esc_html( $label ) . '</span></label>';

		if ( '' !== (string) $help ) {
			echo '<p class="lwseo-help">' . esc_html( $help ) . '</p>';
		}
	}

	/**
	 * Print title variables as buttons that insert themselves into the focused field.
	 *
	 * @since    1.2.0
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
	 * Print an image field: URL input, media picker, and thumbnail.
	 *
	 * @since    1.2.0
	 * @param    array    $args    Field settings: url, id, url_name, id_name, url_id, id_id, alt.
	 * @return   void
	 */
	public static function image_field( $args ) {
		$url = (string) ( $args['url'] ?? '' );
		?>
		<div class="lwseo-image">
			<input type="hidden" id="<?php echo esc_attr( $args['id_id'] ); ?>" name="<?php echo esc_attr( $args['id_name'] ); ?>" value="<?php echo esc_attr( absint( $args['id'] ?? 0 ) ); ?>" class="lwseo-image__id">
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
