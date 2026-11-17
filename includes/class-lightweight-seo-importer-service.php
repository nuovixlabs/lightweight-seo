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
	/**
	 * Plugins whose saved post metadata can be read.
	 *
	 * @since    1.1.0
	 * @var      string[]
	 */
	const SOURCES = array( 'yoast', 'rank_math', 'aioseo', 'seopress', 'the_seo_framework', 'slim_seo' );

	/** Maximum number of posts read or changed in one administrator request. */
	const BATCH_SIZE = 50;

	/** Option containing the bounded rollback snapshot for the latest batch. */
	const ROLLBACK_OPTION = 'lightweight_seo_import_rollback';

	/** Text fields whose source values may contain the other plugin's template variables. */
	const TEMPLATED_FIELDS = array( 'seo_title', 'seo_description', 'social_title', 'social_description' );

	/**
	 * Shared post meta service.
	 *
	 * @since    1.1.0
	 * @access   private
	 * @var      Lightweight_SEO_Post_Meta
	 */
	private $post_meta;

	/**
	 * Fields left out of the current batch because their variables cannot be converted.
	 *
	 * @since    1.1.0
	 * @access   private
	 * @var      int
	 */
	private $unsupported_fields = 0;

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
			'source'             => $source,
			'offset'             => max( 0, absint( $offset ) ),
			'batch_size'         => self::BATCH_SIZE,
			'scanned_posts'      => 0,
			'eligible_posts'     => 0,
			'imported_posts'     => 0,
			'updated_fields'     => 0,
			'skipped_fields'     => 0,
			'unsupported_fields' => 0,
			'next_offset'        => max( 0, absint( $offset ) ),
			'has_more'           => false,
		);

		if ( ! in_array( $source, self::SOURCES, true ) ) {
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

		$this->unsupported_fields = 0;

		foreach ( $posts as $post ) {
			++$report['scanned_posts'];

			$mapped_values = $this->map_source_meta( (int) $post->ID, $source );

			if ( empty( $mapped_values ) ) {
				continue;
			}

			$updated_field_count = 0;

			// A page this import hides is "noindex" only. Without a stored follow choice it would also print "nofollow",
			// the rule kept for pages hidden before 1.1.
			if ( '1' === (string) ( $mapped_values['seo_noindex'] ?? '' ) && ! isset( $mapped_values['seo_nofollow'] ) && '' === (string) $this->post_meta->get( (int) $post->ID, 'seo_noindex' ) ) {
				$mapped_values['seo_nofollow'] = '0';
			}

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

		$report['next_offset']        = $report['offset'] + $report['scanned_posts'];
		$report['unsupported_fields'] = $this->unsupported_fields;

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
				$mapped = array(
					'seo_title'          => $this->get_source_meta( $post_id, '_yoast_wpseo_title' ),
					'seo_description'    => $this->get_source_meta( $post_id, '_yoast_wpseo_metadesc' ),
					'seo_keywords'       => $this->get_source_meta( $post_id, '_yoast_wpseo_focuskw' ),
					'seo_canonical_url'  => $this->get_source_meta( $post_id, '_yoast_wpseo_canonical' ),
					'seo_noindex'        => $this->normalize_boolean_flag( $this->get_source_meta( $post_id, '_yoast_wpseo_meta-robots-noindex' ) ),
					'seo_nofollow'       => $this->normalize_boolean_flag( $this->get_source_meta( $post_id, '_yoast_wpseo_meta-robots-nofollow' ) ),
					'social_title'       => $this->get_source_meta( $post_id, '_yoast_wpseo_opengraph-title' ),
					'social_description' => $this->get_source_meta( $post_id, '_yoast_wpseo_opengraph-description' ),
					'social_image'       => $this->get_source_meta( $post_id, '_yoast_wpseo_opengraph-image' ),
				);
				break;
			case 'rank_math':
				$robots = $this->get_source_meta( $post_id, 'rank_math_robots' );
				$mapped = array(
					'seo_title'          => $this->get_source_meta( $post_id, 'rank_math_title' ),
					'seo_description'    => $this->get_source_meta( $post_id, 'rank_math_description' ),
					'seo_keywords'       => $this->get_source_meta( $post_id, 'rank_math_focus_keyword' ),
					'seo_canonical_url'  => $this->get_source_meta( $post_id, 'rank_math_canonical_url' ),
					'seo_noindex'        => $this->normalize_rank_math_robot_flag( $robots, 'noindex' ),
					'seo_nofollow'       => $this->normalize_rank_math_robot_flag( $robots, 'nofollow' ),
					'social_title'       => $this->get_source_meta( $post_id, 'rank_math_facebook_title' ),
					'social_description' => $this->get_source_meta( $post_id, 'rank_math_facebook_description' ),
					'social_image'       => $this->get_source_meta( $post_id, 'rank_math_facebook_image' ),
				);
				break;
			case 'aioseo':
				$mapped = array(
					'seo_title'          => $this->get_source_meta( $post_id, '_aioseo_title' ),
					'seo_description'    => $this->get_source_meta( $post_id, '_aioseo_description' ),
					'seo_keywords'       => $this->normalize_aioseo_keywords( $this->get_source_meta( $post_id, '_aioseo_keywords' ) ),
					'seo_canonical_url'  => $this->get_source_meta( $post_id, '_aioseo_canonical_url' ),
					'seo_noindex'        => $this->normalize_boolean_flag( $this->get_source_meta( $post_id, '_aioseo_robots_noindex' ) ),
					'seo_nofollow'       => $this->normalize_boolean_flag( $this->get_source_meta( $post_id, '_aioseo_robots_nofollow' ) ),
					'social_title'       => $this->get_source_meta( $post_id, '_aioseo_og_title' ),
					'social_description' => $this->get_source_meta( $post_id, '_aioseo_og_description' ),
					'social_image'       => $this->get_source_meta( $post_id, '_aioseo_og_image' ),
				);
				break;
			case 'seopress':
				$mapped = array(
					'seo_title'          => $this->get_source_meta( $post_id, '_seopress_titles_title' ),
					'seo_description'    => $this->get_source_meta( $post_id, '_seopress_titles_desc' ),
					'seo_keywords'       => $this->get_source_meta( $post_id, '_seopress_analysis_target_kw' ),
					'seo_canonical_url'  => $this->get_source_meta( $post_id, '_seopress_robots_canonical' ),
					// SEOPress stores "yes" for a ticked "noindex", "nofollow", or "nosnippet" box.
					'seo_noindex'        => $this->normalize_boolean_flag( $this->get_source_meta( $post_id, '_seopress_robots_index' ) ),
					'seo_nofollow'       => $this->normalize_boolean_flag( $this->get_source_meta( $post_id, '_seopress_robots_follow' ) ),
					'seo_nosnippet'      => $this->normalize_boolean_flag( $this->get_source_meta( $post_id, '_seopress_robots_snippet' ) ),
					'social_title'       => $this->get_source_meta( $post_id, '_seopress_social_fb_title' ),
					'social_description' => $this->get_source_meta( $post_id, '_seopress_social_fb_desc' ),
					'social_image'       => $this->get_source_meta( $post_id, '_seopress_social_fb_img' ),
				);
				break;
			case 'the_seo_framework':
				$image_id = absint( $this->get_source_meta( $post_id, '_social_image_id' ) );
				$mapped   = array(
					'seo_title'          => $this->get_source_meta( $post_id, '_genesis_title' ),
					'seo_description'    => $this->get_source_meta( $post_id, '_genesis_description' ),
					'seo_canonical_url'  => $this->get_source_meta( $post_id, '_genesis_canonical_uri' ),
					// Each robots choice is 1 (apply), -1 (never apply), or 0 (follow the site default).
					'seo_noindex'        => $this->normalize_three_way_flag( $this->get_source_meta( $post_id, '_genesis_noindex' ), Lightweight_SEO_Settings::INDEX_OVERRIDE ),
					'seo_nofollow'       => $this->normalize_three_way_flag( $this->get_source_meta( $post_id, '_genesis_nofollow' ), '0' ),
					'seo_noarchive'      => $this->normalize_three_way_flag( $this->get_source_meta( $post_id, '_genesis_noarchive' ), '0' ),
					'social_title'       => $this->get_source_meta( $post_id, '_open_graph_title' ),
					'social_description' => $this->get_source_meta( $post_id, '_open_graph_description' ),
					'social_image'       => $this->get_source_meta( $post_id, '_social_image_url' ),
					'social_image_id'    => $image_id ? (string) $image_id : '',
				);
				break;
			case 'slim_seo':
				// Slim SEO keeps every field of a post in one array.
				$data   = $this->get_source_meta( $post_id, 'slim_seo' );
				$data   = is_array( $data ) ? $data : array();
				$text   = static function ( $key ) use ( $data ) {
					return isset( $data[ $key ] ) && is_scalar( $data[ $key ] ) ? (string) $data[ $key ] : '';
				};
				$image  = $text( 'facebook_image' );
				$mapped = array(
					'seo_title'         => $text( 'title' ),
					'seo_description'   => $text( 'description' ),
					'seo_canonical_url' => $text( 'canonical' ),
					'seo_noindex'       => ! empty( $data['noindex'] ) ? '1' : '',
					// The image is stored as an address or as an attachment ID.
					'social_image'      => ctype_digit( $image ) ? (string) wp_get_attachment_url( (int) $image ) : $image,
				);
				break;
			default:
				return array();
		}

		foreach ( self::TEMPLATED_FIELDS as $field ) {
			if ( ! is_string( $mapped[ $field ] ?? null ) || '' === $mapped[ $field ] ) {
				continue;
			}

			$converted = $this->convert_template_variables( $mapped[ $field ], $source );

			if ( null === $converted ) {
				// Importing an unresolved variable would print it literally in the page head.
				++$this->unsupported_fields;
				$converted = '';
			}

			$mapped[ $field ] = $converted;
		}

		return array_filter( $mapped, array( $this, 'keep_mapped_value' ) );
	}

	/**
	 * Translate another plugin's template variables into Lightweight SEO tokens.
	 *
	 * @since    1.1.0
	 * @param    string    $value     Stored source value.
	 * @param    string    $source    Import source.
	 * @return   string|null Converted text, or null when a variable has no equivalent.
	 */
	private function convert_template_variables( $value, $source ) {
		$value = (string) $value;

		switch ( $source ) {
			case 'yoast':
				$map     = array(
					'%%title%%'       => '%title%',
					'%%sitename%%'    => '%sitename%',
					'%%sitedesc%%'    => '%tagline%',
					'%%sep%%'         => '%sep%',
					'%%page%%'        => '%page%',
					'%%currentyear%%' => '%year%',
					'%%name%%'        => '%author%',
					'%%pagenumber%%'  => '',
					'%%pagetotal%%'   => '',
				);
				$unknown = '/%%[a-z0-9_\-]+%%/i';
				break;
			case 'rank_math':
				$map     = array(
					'%sitedesc%'    => '%tagline%',
					'%currentyear%' => '%year%',
					'%name%'        => '%author%',
				);
				$unknown = '/%(?!(?:title|sitename|tagline|sep|page|category|author|year)%)[a-z0-9_]+(?:\([^)]*\))?%/i';
				break;
			case 'aioseo':
				$map     = array(
					'#post_title'   => '%title%',
					'#site_title'   => '%sitename%',
					'#tagline'      => '%tagline%',
					'#separator_sa' => '%sep%',
					'#current_year' => '%year%',
					'#author_name'  => '%author%',
					'#page_number'  => '',
				);
				$unknown = '/#(?:archive_|attachment_|author_|breadcrumb_|categories|category_title|current_|custom_field-|parent_title|permalink|post_|search_term|tax_name-|taxonomy_)[a-z0-9_\-]*/i';
				break;
			case 'seopress':
				$map     = array(
					'%%post_title%%'    => '%title%',
					'%%title%%'         => '%title%',
					'%%sitetitle%%'     => '%sitename%',
					'%%sitename%%'      => '%sitename%',
					'%%tagline%%'       => '%tagline%',
					'%%sitedesc%%'      => '%tagline%',
					'%%sep%%'           => '%sep%',
					'%%currentyear%%'   => '%year%',
					'%%post_author%%'   => '%author%',
					'%%post_category%%' => '%category%',
					// SEOPress prints its own separator before "Page 2 of 5".
					'%%page%%'          => '%sep% %page%',
				);
				$unknown = '/%%[a-z0-9_\-]+%%/i';
				break;
			case 'slim_seo':
				// Variables are written with any amount of space inside the braces.
				$value   = preg_replace( '/\{\{\s*([a-z0-9_.]+)\s*\}\}/i', '{{$1}}', $value );
				$map     = array(
					'{{post.title}}'          => '%title%',
					'{{site.title}}'          => '%sitename%',
					'{{site.description}}'    => '%tagline%',
					'{{sep}}'                 => '%sep%',
					'{{page}}'                => '%page%',
					'{{current.year}}'        => '%year%',
					'{{author.display_name}}' => '%author%',
				);
				$unknown = '/\{\{.*?\}\}/s';
				break;
			default:
				// The SEO Framework stores plain text.
				return $value;
		}

		$converted = str_ireplace( array_keys( $map ), array_values( $map ), $value );

		if ( 1 === preg_match( $unknown, $converted ) ) {
			return null;
		}

		return trim( preg_replace( '/[ \t]{2,}/', ' ', $converted ) );
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
	 * Normalize a choice stored as 1 (apply), -1 (never apply), or 0 (follow the default).
	 *
	 * @since    1.1.0
	 * @param    mixed     $value       Raw source value.
	 * @param    string    $opposite    Value to store for "never apply".
	 * @return   string "1", the value for "never apply", or an empty string for the default.
	 */
	private function normalize_three_way_flag( $value, $opposite ) {
		$number = is_scalar( $value ) ? (float) $value : 0.0;

		if ( $number > 0.3334 ) {
			return '1';
		}

		return $number < -0.3333 ? $opposite : '';
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
