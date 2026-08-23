<?php
/**
 * SEO metadata importer service for Lightweight SEO.
 *
 * @since      1.1.0
 * @package    Lightweight_SEO
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * SEO metadata importer service.
 */
class Lightweight_SEO_Importer_Service {
	/** Maximum number of posts read or changed in one administrator request. */
	const BATCH_SIZE = 50;

	/** Option containing the bounded rollback snapshot for the latest batch. */
	const ROLLBACK_OPTION = 'lightweight_seo_import_rollback';

	/**
	 * Shared post meta service.
	 *
	 * @since    1.1.0
	 * @access   private
	 * @var      Lightweight_SEO_Post_Meta
	 */
	private $post_meta;

	/**
	 * Initialize the service.
	 *
	 * @since    1.1.0
	 * @param    Lightweight_SEO_Post_Meta    $post_meta    Shared post meta service.
	 */
	public function __construct( $post_meta ) {
		$this->post_meta = $post_meta;
	}

	/**
	 * Import metadata from a supported SEO plugin.
	 *
	 * @since    1.1.0
	 * @param    string    $source    Supported importer source.
	 * @return   array
	 */
	public function import( $source ) {
		return $this->import_batch( $source, 0 );
	}

	/** Preview one bounded batch without writing metadata. */
	public function preview( $source, $offset = 0 ) {
		return $this->process_batch( $source, $offset, false );
	}

	/** Import one bounded batch and retain only that batch's rollback snapshot. */
	public function import_batch( $source, $offset = 0 ) {
		return $this->process_batch( $source, $offset, true );
	}

	/** Roll back the most recently imported batch. */
	public function rollback_last_batch() {
		$snapshot = (array) get_option( self::ROLLBACK_OPTION, array() );
		$restored = 0;

		foreach ( (array) ( $snapshot['changes'] ?? array() ) as $change ) {
			$post_id = absint( $change['post_id'] ?? 0 );
			$field   = sanitize_key( $change['field'] ?? '' );

			if ( ! $post_id || '' === $field ) {
				continue;
			}

			if ( ! empty( $change['existed'] ) || '' !== (string) ( $change['old_value'] ?? '' ) ) {
				$this->post_meta->update( $post_id, $field, $change['old_value'] ?? '' );
			} elseif ( method_exists( $this->post_meta, 'delete' ) ) {
				$this->post_meta->delete( $post_id, $field );
			} else {
				$this->post_meta->update( $post_id, $field, '' );
			}

			++$restored;
		}

		delete_option( self::ROLLBACK_OPTION );

		return array(
			'source'          => sanitize_key( $snapshot['source'] ?? '' ),
			'restored_fields' => $restored,
			'restored_posts'  => count( array_unique( array_map( 'absint', wp_list_pluck( (array) ( $snapshot['changes'] ?? array() ), 'post_id' ) ) ) ),
			'offset'          => absint( $snapshot['offset'] ?? 0 ),
		);
	}

	/** Whether a bounded latest-batch rollback is available. */
	public function has_rollback() {
		$snapshot = (array) get_option( self::ROLLBACK_OPTION, array() );

		return ! empty( $snapshot['changes'] );
	}

	/** Analyze or write one bounded batch. */
	private function process_batch( $source, $offset, $write ) {
		$source = sanitize_key( $source );
		$report = array(
			'source'         => $source,
			'offset'         => max( 0, absint( $offset ) ),
			'batch_size'     => self::BATCH_SIZE,
			'scanned_posts'  => 0,
			'eligible_posts' => 0,
			'imported_posts' => 0,
			'updated_fields' => 0,
			'skipped_fields' => 0,
			'next_offset'    => max( 0, absint( $offset ) ),
			'has_more'       => false,
		);

		if ( ! in_array( $source, array( 'yoast', 'rank_math', 'aioseo' ), true ) ) {
			return $report;
		}

		$posts              = get_posts(
			array(
				'post_type'              => $this->post_meta->get_supported_post_types(),
				'post_status'            => array( 'publish', 'draft', 'pending', 'future', 'private' ),
				'posts_per_page'         => self::BATCH_SIZE + 1,
				'offset'                 => $report['offset'],
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);
		$report['has_more'] = count( $posts ) > self::BATCH_SIZE;
		$posts              = array_slice( $posts, 0, self::BATCH_SIZE );
		$rollback_changes   = array();

		foreach ( $posts as $post ) {
			++$report['scanned_posts'];

			$mapped_values = $this->map_source_meta( (int) $post->ID, $source );

			if ( empty( $mapped_values ) ) {
				continue;
			}

			$updated_field_count = 0;

			foreach ( $mapped_values as $field => $value ) {
				if ( '' === (string) $value && 0 !== $value ) {
					continue;
				}

				$current_value = $this->post_meta->get( (int) $post->ID, $field );

				if ( (string) $current_value === (string) $value ) {
					continue;
				}

				if ( '' !== (string) $current_value ) {
					++$report['skipped_fields'];
					continue;
				}

				++$report['updated_fields'];
				++$updated_field_count;

				if ( ! $write ) {
					continue;
				}

				$meta_key           = method_exists( $this->post_meta, 'get_meta_key' ) ? $this->post_meta->get_meta_key( $field ) : '';
				$rollback_changes[] = array(
					'post_id'   => (int) $post->ID,
					'field'     => $field,
					'existed'   => $meta_key && function_exists( 'metadata_exists' ) ? metadata_exists( 'post', (int) $post->ID, $meta_key ) : '' !== (string) $current_value,
					'old_value' => $current_value,
				);
				$this->post_meta->update( (int) $post->ID, $field, $value );
			}

			if ( $updated_field_count > 0 ) {
				++$report['eligible_posts'];

				if ( $write ) {
					++$report['imported_posts'];
				}
			}
		}

		$report['next_offset'] = $report['offset'] + $report['scanned_posts'];

		if ( $write ) {
			if ( ! empty( $rollback_changes ) ) {
				update_option(
					self::ROLLBACK_OPTION,
					array(
						'source'  => $source,
						'offset'  => $report['offset'],
						'changes' => $rollback_changes,
					),
					false
				);
			} else {
				delete_option( self::ROLLBACK_OPTION );
			}
		}

		return $report;
	}

	/**
	 * Map source SEO metadata into Lightweight SEO fields.
	 *
	 * @since    1.1.0
	 * @param    int       $post_id    Post ID.
	 * @param    string    $source     Import source.
	 * @return   array
	 */
	private function map_source_meta( $post_id, $source ) {
		switch ( $source ) {
			case 'yoast':
				return array_filter(
					array(
						'seo_title'          => $this->get_source_meta( $post_id, '_yoast_wpseo_title' ),
						'seo_description'    => $this->get_source_meta( $post_id, '_yoast_wpseo_metadesc' ),
						'seo_keywords'       => $this->get_source_meta( $post_id, '_yoast_wpseo_focuskw' ),
						'seo_canonical_url'  => $this->get_source_meta( $post_id, '_yoast_wpseo_canonical' ),
						'seo_noindex'        => $this->normalize_boolean_flag( $this->get_source_meta( $post_id, '_yoast_wpseo_meta-robots-noindex' ) ),
						'seo_nofollow'       => $this->normalize_boolean_flag( $this->get_source_meta( $post_id, '_yoast_wpseo_meta-robots-nofollow' ) ),
						'social_title'       => $this->get_source_meta( $post_id, '_yoast_wpseo_opengraph-title' ),
						'social_description' => $this->get_source_meta( $post_id, '_yoast_wpseo_opengraph-description' ),
						'social_image'       => $this->get_source_meta( $post_id, '_yoast_wpseo_opengraph-image' ),
					),
					array( $this, 'keep_mapped_value' )
				);
			case 'rank_math':
				$robots = $this->get_source_meta( $post_id, 'rank_math_robots' );

				return array_filter(
					array(
						'seo_title'          => $this->get_source_meta( $post_id, 'rank_math_title' ),
						'seo_description'    => $this->get_source_meta( $post_id, 'rank_math_description' ),
						'seo_keywords'       => $this->get_source_meta( $post_id, 'rank_math_focus_keyword' ),
						'seo_canonical_url'  => $this->get_source_meta( $post_id, 'rank_math_canonical_url' ),
						'seo_noindex'        => $this->normalize_rank_math_robot_flag( $robots, 'noindex' ),
						'seo_nofollow'       => $this->normalize_rank_math_robot_flag( $robots, 'nofollow' ),
						'social_title'       => $this->get_source_meta( $post_id, 'rank_math_facebook_title' ),
						'social_description' => $this->get_source_meta( $post_id, 'rank_math_facebook_description' ),
						'social_image'       => $this->get_source_meta( $post_id, 'rank_math_facebook_image' ),
					),
					array( $this, 'keep_mapped_value' )
				);
			case 'aioseo':
				return array_filter(
					array(
						'seo_title'          => $this->get_source_meta( $post_id, '_aioseo_title' ),
						'seo_description'    => $this->get_source_meta( $post_id, '_aioseo_description' ),
						'seo_keywords'       => $this->normalize_aioseo_keywords( $this->get_source_meta( $post_id, '_aioseo_keywords' ) ),
						'seo_canonical_url'  => $this->get_source_meta( $post_id, '_aioseo_canonical_url' ),
						'seo_noindex'        => $this->normalize_boolean_flag( $this->get_source_meta( $post_id, '_aioseo_robots_noindex' ) ),
						'seo_nofollow'       => $this->normalize_boolean_flag( $this->get_source_meta( $post_id, '_aioseo_robots_nofollow' ) ),
						'social_title'       => $this->get_source_meta( $post_id, '_aioseo_og_title' ),
						'social_description' => $this->get_source_meta( $post_id, '_aioseo_og_description' ),
						'social_image'       => $this->get_source_meta( $post_id, '_aioseo_og_image' ),
					),
					array( $this, 'keep_mapped_value' )
				);
		}

		return array();
	}

	/**
	 * Determine whether a mapped value should be retained.
	 *
	 * @since    1.1.0
	 * @param    mixed    $value    Candidate value.
	 * @return   bool
	 */
	private function keep_mapped_value( $value ) {
		return '' !== (string) $value || 0 === $value;
	}

	/**
	 * Retrieve raw post meta from a source plugin.
	 *
	 * @since    1.1.0
	 * @param    int       $post_id     Post ID.
	 * @param    string    $meta_key    Source meta key.
	 * @return   mixed
	 */
	private function get_source_meta( $post_id, $meta_key ) {
		return get_post_meta( $post_id, $meta_key, true );
	}

	/**
	 * Normalize common boolean noindex/nofollow flags.
	 *
	 * @since    1.1.0
	 * @param    mixed    $value    Raw source value.
	 * @return   string
	 */
	private function normalize_boolean_flag( $value ) {
		$normalized = strtolower( sanitize_text_field( is_array( $value ) ? implode( ',', $value ) : (string) $value ) );

		return in_array( $normalized, array( '1', 'true', 'yes', 'noindex', 'nofollow', 'on' ), true ) ? '1' : '';
	}

	/**
	 * Normalize a Rank Math robots payload.
	 *
	 * @since    1.1.0
	 * @param    mixed     $robots    Raw robots payload.
	 * @param    string    $needle    Robot directive to test.
	 * @return   string
	 */
	private function normalize_rank_math_robot_flag( $robots, $needle ) {
		if ( is_array( $robots ) ) {
			return in_array( $needle, array_map( 'strtolower', array_map( 'sanitize_text_field', $robots ) ), true ) ? '1' : '';
		}

		$normalized = strtolower( sanitize_text_field( (string) $robots ) );

		return false !== strpos( $normalized, $needle ) ? '1' : '';
	}

	/**
	 * Normalize AIOSEO keywords into a comma-separated string.
	 *
	 * @since    1.1.0
	 * @param    mixed    $keywords    Raw keywords value.
	 * @return   string
	 */
	private function normalize_aioseo_keywords( $keywords ) {
		if ( is_array( $keywords ) ) {
			return implode(
				', ',
				array_values(
					array_filter(
						array_map( 'sanitize_text_field', $keywords )
					)
				)
			);
		}

		return sanitize_text_field( (string) $keywords );
	}
}
