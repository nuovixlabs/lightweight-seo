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
		$settings = apply_filters( 'lightweight_seo_tracking_settings', $this->settings->get_all() );

		do_action( 'lightweight_seo_before_tracking_codes', $settings );

		if ( ! empty( $settings['ga4_measurement_id'] ) ) {
			?>
			<!-- Google Analytics 4 -->
			<script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo esc_attr( $settings['ga4_measurement_id'] ); ?>"></script>
			<script>
				window.dataLayer = window.dataLayer || [];
				function gtag(){dataLayer.push(arguments);}
				gtag('js', new Date());
				gtag('config', '<?php echo esc_attr( $settings['ga4_measurement_id'] ); ?>');
			</script>
			<?php
		}

		if ( ! empty( $settings['gtm_container_id'] ) ) {
			?>
			<!-- Google Tag Manager -->
			<script>
				(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
				new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
				j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
				'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
				})(window,document,'script','dataLayer','<?php echo esc_attr( $settings['gtm_container_id'] ); ?>');
			</script>
			<?php
		}

		if ( ! empty( $settings['facebook_pixel_id'] ) ) {
			?>
			<!-- Facebook Pixel -->
			<script>
				!function(f,b,e,v,n,t,s)
				{if(f.fbq)return;n=f.fbq=function(){n.callMethod?
				n.callMethod.apply(n,arguments):n.queue.push(arguments)};
				if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
				n.queue=[];t=b.createElement(e);t.async=!0;
				t.src=v;s=b.getElementsByTagName(e)[0];
				s.parentNode.insertBefore(t,s)}(window, document,'script',
				'https://connect.facebook.net/en_US/fbevents.js');
				fbq('init', '<?php echo esc_attr( $settings['facebook_pixel_id'] ); ?>');
				fbq('track', 'PageView');
			</script>
			<noscript>
				<img height="1" width="1" style="display:none" 
					src="https://www.facebook.com/tr?id=<?php echo esc_attr( $settings['facebook_pixel_id'] ); ?>&ev=PageView&noscript=1"/>
			</noscript>
			<?php
		}

		do_action( 'lightweight_seo_after_tracking_codes', $settings );
	}

	/**
	 * Add Google Tag Manager noscript code after body tag.
	 *
	 * @since    1.0.2
	 * @return   void
	 */
	public function add_gtm_noscript() {
		$settings = apply_filters( 'lightweight_seo_tracking_settings', $this->settings->get_all() );

		if ( ! empty( $settings['gtm_container_id'] ) ) {
			do_action( 'lightweight_seo_before_gtm_noscript', $settings );
			?>
			<!-- Google Tag Manager (noscript) -->
			<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=<?php echo esc_attr( $settings['gtm_container_id'] ); ?>"
			height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
			<?php
			do_action( 'lightweight_seo_after_gtm_noscript', $settings );
		}
	}
}
