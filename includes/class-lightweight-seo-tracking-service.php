<?php
/**
 * Frontend tracking service for Lightweight SEO.
 *
 * @since      1.0.2
 * @package    Lightweight_SEO
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Frontend tracking service.
 */
class Lightweight_SEO_Tracking_Service {

	/**
	 * Shared settings service.
	 *
	 * @since    1.0.2
	 * @access   private
	 * @var      Lightweight_SEO_Settings    $settings
	 */
	private $settings;

	/** Whether the GTM head container was output during this request. */
	private $gtm_head_output = false;

	/** Whether the GTM body fallback was output during this request. */
	private $gtm_body_output = false;

	/**
	 * Initialize the service.
	 *
	 * @since    1.0.2
	 * @param    Lightweight_SEO_Settings    $settings    Shared settings service.
	 */
	public function __construct( $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Add tracking codes to head.
	 *
	 * @since    1.0.2
	 * @return   void
	 */
	public function add_tracking_codes() {
		$tracking_settings = $this->get_tracking_settings();
		$configuration     = $this->normalize_configuration( $tracking_settings );
		$providers         = array_keys( array_filter( $configuration ) );
		do_action( 'lightweight_seo_before_tracking_codes', $tracking_settings );

		if ( empty( $providers ) ) {
			do_action( 'lightweight_seo_after_tracking_codes', $tracking_settings );
			return;
		}

		if ( ! empty( $configuration['gtm'] ) && $this->tracking_is_allowed( 'gtm' ) ) {
			$nonce                 = $this->get_script_nonce( 'gtm' );
			$this->gtm_head_output = true;
			?>
			<!-- Google Tag Manager -->
			<script<?php echo $nonce; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fully escaped attribute. ?>>
				(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
				new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
				j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
				'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
				})(window,document,'script','dataLayer','<?php echo esc_attr( $configuration['gtm'] ); ?>');
			</script>
			<?php
		}

		if ( ! empty( $configuration['ga4'] ) && $this->tracking_is_allowed( 'ga4' ) ) {
			$nonce = $this->get_script_nonce( 'ga4' );
			?>
			<!-- Google Analytics 4 -->
			<script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo esc_attr( $configuration['ga4'] ); ?>"<?php echo $nonce; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fully escaped attribute. ?>></script>
			<script<?php echo $nonce; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fully escaped attribute. ?>>
				window.dataLayer = window.dataLayer || [];
				function gtag(){dataLayer.push(arguments);}
				gtag('js', new Date());
				gtag('config', '<?php echo esc_attr( $configuration['ga4'] ); ?>');
			</script>
			<?php
		}

		if ( ! empty( $configuration['meta'] ) && $this->tracking_is_allowed( 'meta' ) ) {
			$nonce = $this->get_script_nonce( 'meta' );
			?>
			<!-- Meta Pixel -->
			<script<?php echo $nonce; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fully escaped attribute. ?>>
				!function(f,b,e,v,n,t,s)
				{if(f.fbq)return;n=f.fbq=function(){n.callMethod?
				n.callMethod.apply(n,arguments):n.queue.push(arguments)};
				if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
				n.queue=[];t=b.createElement(e);t.async=!0;
				t.src=v;s=b.getElementsByTagName(e)[0];
				s.parentNode.insertBefore(t,s)}(window, document,'script',
				'https://connect.facebook.net/en_US/fbevents.js');
				fbq('init', '<?php echo esc_attr( $configuration['meta'] ); ?>');
				fbq('track', 'PageView');
			</script>
			<noscript>
				<img height="1" width="1" style="display:none" 
					src="https://www.facebook.com/tr?id=<?php echo esc_attr( $configuration['meta'] ); ?>&ev=PageView&noscript=1"/>
			</noscript>
			<?php
		}

		do_action( 'lightweight_seo_after_tracking_codes', $tracking_settings );
	}

	/**
	 * Add Google Tag Manager noscript code after body tag.
	 *
	 * @since    1.0.2
	 * @return   void
	 */
	public function add_gtm_noscript() {
		$tracking_settings = $this->get_tracking_settings();
		$configuration     = $this->normalize_configuration( $tracking_settings );

		if ( ! empty( $configuration['gtm'] ) && $this->tracking_is_allowed( 'gtm' ) ) {
			$this->gtm_body_output = true;
			do_action( 'lightweight_seo_before_gtm_noscript', $tracking_settings );
			?>
			<!-- Google Tag Manager (noscript) -->
			<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=<?php echo esc_attr( $configuration['gtm'] ); ?>"
			height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
			<?php
			do_action( 'lightweight_seo_after_gtm_noscript', $tracking_settings );
		}
	}

	/** Output a source-level diagnostic when the active theme omits wp_body_open(). */
	public function add_gtm_theme_diagnostic() {
		if ( $this->gtm_head_output && ! $this->gtm_body_output ) {
			echo '<!-- Lightweight SEO: GTM body fallback was not output because this theme did not call wp_body_open(). -->' . "\n";
		}
	}

	/** Return a strict provider map, with GTM taking precedence over direct tags. */
	public function get_configuration() {
		return $this->normalize_configuration( $this->get_tracking_settings() );
	}

	/** Return only the legacy tracking keys, never the complete plugin settings array. */
	private function get_tracking_settings() {
		$settings = $this->settings->get_all();

		return (array) apply_filters(
			'lightweight_seo_tracking_settings',
			array(
				'gtm_container_id'   => (string) ( $settings['gtm_container_id'] ?? '' ),
				'ga4_measurement_id' => (string) ( $settings['ga4_measurement_id'] ?? '' ),
				'facebook_pixel_id'  => (string) ( $settings['facebook_pixel_id'] ?? '' ),
			)
		);
	}

	/** Validate identifiers and apply the GTM-first provider precedence rule. */
	private function normalize_configuration( $settings ) {
		$gtm  = strtoupper( trim( (string) ( $settings['gtm_container_id'] ?? '' ) ) );
		$ga4  = strtoupper( trim( (string) ( $settings['ga4_measurement_id'] ?? '' ) ) );
		$meta = trim( (string) ( $settings['facebook_pixel_id'] ?? '' ) );

		$gtm  = 1 === preg_match( '/^GTM-[A-Z0-9]+$/', $gtm ) ? $gtm : '';
		$ga4  = 1 === preg_match( '/^G-[A-Z0-9]+$/', $ga4 ) ? $ga4 : '';
		$meta = 1 === preg_match( '/^[0-9]+$/', $meta ) ? $meta : '';

		if ( '' !== $gtm ) {
			$ga4  = '';
			$meta = '';
		}

		return array(
			'gtm'  => $gtm,
			'ga4'  => $ga4,
			'meta' => $meta,
		);
	}

	/** Determine whether one provider may emit for the current visitor and environment. */
	public function tracking_is_allowed( $provider ) {
		$environment  = function_exists( 'wp_get_environment_type' ) ? wp_get_environment_type() : 'production';
		$environments = method_exists( $this->settings, 'get_tracking_excluded_environments' ) ? $this->settings->get_tracking_excluded_environments() : array();

		if ( in_array( $environment, $environments, true ) ) {
			return false;
		}

		if ( function_exists( 'is_user_logged_in' ) && is_user_logged_in() && function_exists( 'wp_get_current_user' ) ) {
			$user  = wp_get_current_user();
			$roles = method_exists( $this->settings, 'get_tracking_excluded_roles' ) ? $this->settings->get_tracking_excluded_roles() : array();

			if ( array_intersect( (array) ( $user->roles ?? array() ), $roles ) ) {
				return false;
			}
		}

		$consent = (bool) apply_filters( 'lightweight_seo_tracking_consent_granted', true, $provider );

		return (bool) apply_filters( 'lightweight_seo_tracking_should_output', $consent, $provider );
	}

	/** Return a safely escaped CSP nonce attribute for a provider. */
	private function get_script_nonce( $provider ) {
		$nonce = trim( (string) apply_filters( 'lightweight_seo_tracking_script_nonce', '', $provider ) );

		return '' === $nonce ? '' : ' nonce="' . esc_attr( $nonce ) . '"';
	}
}
