<?php
/** Minimal Insights-style dependency handshake fixture. */
class Lightweight_SEO_Insights_Fixture {

	private $ready = false;

	public function boot( $api ) {
		if ( ! $api || ! is_callable( array( $api, 'is_compatible' ) ) || ! $api->is_compatible( '1.1.0-rc.1', '1.0' ) ) {
			return false;
		}

		$this->ready = ! empty( $api->get_supported_object_types()['post'] );

		return $this->ready;
	}

	public function is_ready() {
		return $this->ready;
	}
}
