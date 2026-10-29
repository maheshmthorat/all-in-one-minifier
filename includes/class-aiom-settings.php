<?php
/**
 * Settings storage, defaults, validation and runtime state.
 *
 * Frontend requests only read the autoloaded `aiom_settings` and `aiom_runtime`
 * options; labels and descriptions live in the admin (AIOM_Admin_Fields).
 *
 * @package All_In_One_Minifier
 */

defined( 'ABSPATH' ) || exit;

/**
 * Settings access.
 */
class AIOM_Settings {

	const OPTION  = 'aiom_settings';
	const RUNTIME = 'aiom_runtime';

	/**
	 * Defaults. These are the "recommended" settings: conservative transformations
	 * that do not change behaviour, nothing that alters script execution order.
	 */
	const DEFAULTS = array(
		'enabled'                  => true,
		'test_mode'                => false,
		'optimize_logged_in'       => false,

		'html_minify'              => true,
		'html_remove_comments'     => true,
		'html_keep_comments'       => '',
		'html_preserve_classes'    => '',
		'html_block_whitespace'    => false,
		'html_inline_css'          => true,
		'html_inline_js'           => false,
		'html_inline_json'         => true,

		'css_minify'               => true,
		'css_exclude'              => '',
		'css_font_display'         => '',
		'css_google_fonts_swap'    => false,
		'css_critical'             => '',
		'css_async'                => false,
		'css_async_exclude'        => '',

		'js_minify'                => true,
		'js_exclude'               => '',
		'js_defer'                 => false,
		'js_defer_exclude'         => "jquery-core\njquery-migrate",
		'js_delay'                 => false,
		'js_delay_patterns'        => '',
		'js_delay_timeout'         => 10,

		'hints_preconnect'         => '',
		'hints_preload'            => '',

		'exclude_urls'             => '',
		'exclude_post_types'       => array(),
		'wc_bypass'                => true,
		'auto_compat'              => true,

		'page_cache'               => false,
		'page_cache_ttl'           => 10,
		'page_cache_mobile'        => true,
		'page_cache_exclude'       => '',
		'page_cache_cookies'       => '',
		'page_cache_ignore_params' => "utm_*\ngclid\nfbclid\nmsclkid\n_ga\nmc_cid\nmc_eid",

		'cache_max_mb'             => 256,
		'cache_unused_days'        => 30,
		'max_asset_kb'             => 600,
		'generation_budget_ms'     => 1500,
		'stats_enabled'            => true,
	);

	/**
	 * Integer ranges: key => array( min, max ).
	 */
	const RANGES = array(
		'js_delay_timeout'     => array( 0, 60 ),
		'page_cache_ttl'       => array( 1, 168 ),
		'cache_max_mb'         => array( 16, 10240 ),
		'cache_unused_days'    => array( 1, 365 ),
		'max_asset_kb'         => array( 50, 4096 ),
		'generation_budget_ms' => array( 200, 10000 ),
	);

	/**
	 * Allowed values for choice settings.
	 */
	const CHOICES = array(
		'css_font_display' => array( '', 'swap', 'fallback', 'optional', 'block' ),
	);

	/**
	 * Free-text (multi-line CSS) settings.
	 */
	const CSS_FIELDS = array( 'css_critical' );

	/**
	 * Settings that change optimised output (used for cache keys and purging).
	 */
	const OUTPUT_KEYS = array( 'css_font_display' );

	/**
	 * Cached merged settings.
	 *
	 * @var array|null
	 */
	private static $values = null;

	/**
	 * Cached parsed lists.
	 *
	 * @var array
	 */
	private static $lists = array();

	/**
	 * All settings merged with defaults.
	 *
	 * @return array
	 */
	public static function all() {
		if ( null === self::$values ) {
			$stored       = get_option( self::OPTION, array() );
			self::$values = array_merge( self::DEFAULTS, is_array( $stored ) ? array_intersect_key( $stored, self::DEFAULTS ) : array() );
		}
		return self::$values;
	}

	/**
	 * Get one setting.
	 *
	 * @param string $key Key.
	 * @return mixed
	 */
	public static function get( $key ) {
		$all = self::all();
		return isset( $all[ $key ] ) ? $all[ $key ] : null;
	}

	/**
	 * A textarea setting as a list of non-empty lines.
	 *
	 * @param string $key Key.
	 * @return string[]
	 */
	public static function lines( $key ) {
		if ( ! isset( self::$lists[ $key ] ) ) {
			self::$lists[ $key ] = self::parse_lines( (string) self::get( $key ) );
		}
		return self::$lists[ $key ];
	}

	/**
	 * Split text into trimmed, unique, non-empty lines.
	 *
	 * @param string $text Text.
	 * @return string[]
	 */
	public static function parse_lines( $text ) {
		$out = array();
		foreach ( preg_split( '/\r\n|\r|\n/', (string) $text ) as $line ) {
			$line = trim( $line );
			if ( '' !== $line && ! in_array( $line, $out, true ) ) {
				$out[] = $line;
			}
		}
		return $out;
	}

	/**
	 * Validate and normalise raw input into a complete settings array.
	 *
	 * Booleans missing from $input are treated as false (unchecked checkboxes)
	 * when $is_form is true; otherwise missing keys keep their current value.
	 *
	 * @param array $input   Raw input.
	 * @param bool  $is_form Whether the input comes from the settings form.
	 * @return array
	 */
	public static function sanitize( array $input, $is_form = true ) {
		$current = self::all();
		$out     = array();
		foreach ( self::DEFAULTS as $key => $default ) {
			if ( ! array_key_exists( $key, $input ) ) {
				$out[ $key ] = ( $is_form && is_bool( $default ) ) ? false : $current[ $key ];
				continue;
			}
			$value = $input[ $key ];
			if ( is_bool( $default ) ) {
				$out[ $key ] = in_array( $value, array( true, 1, '1', 'on', 'yes', 'true' ), true );
			} elseif ( is_int( $default ) ) {
				$range       = self::RANGES[ $key ];
				$out[ $key ] = max( $range[0], min( $range[1], (int) $value ) );
			} elseif ( is_array( $default ) ) {
				$out[ $key ] = self::sanitize_post_types( $value );
			} elseif ( isset( self::CHOICES[ $key ] ) ) {
				$out[ $key ] = in_array( $value, self::CHOICES[ $key ], true ) ? $value : $default;
			} elseif ( in_array( $key, self::CSS_FIELDS, true ) ) {
				$out[ $key ] = self::sanitize_css( $value );
			} else {
				$out[ $key ] = self::sanitize_list( $value );
			}
		}
		return $out;
	}

	/**
	 * Normalise a multi-line rule list.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function sanitize_list( $value ) {
		if ( is_array( $value ) ) {
			$value = implode( "\n", array_map( 'strval', $value ) );
		}
		$lines = array();
		foreach ( self::parse_lines( (string) $value ) as $line ) {
			$line = sanitize_text_field( $line );
			if ( '' !== $line && strlen( $line ) <= 500 && ! in_array( $line, $lines, true ) ) {
				$lines[] = $line;
			}
			if ( count( $lines ) >= 300 ) {
				break;
			}
		}
		return implode( "\n", $lines );
	}

	/**
	 * Critical CSS is printed inside <style>; it can never contain markup.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function sanitize_css( $value ) {
		$css = str_replace( array( '<', "\0" ), '', (string) $value );
		return substr( trim( $css ), 0, 65536 );
	}

	/**
	 * Keep only registered public post type slugs.
	 *
	 * @param mixed $value Raw value.
	 * @return string[]
	 */
	public static function sanitize_post_types( $value ) {
		$value = is_array( $value ) ? $value : array();
		$valid = function_exists( 'get_post_types' ) ? get_post_types( array( 'public' => true ) ) : array();
		$out   = array();
		foreach ( $value as $type ) {
			$type = sanitize_key( $type );
			if ( isset( $valid[ $type ] ) ) {
				$out[] = $type;
			}
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * Save settings (already sanitised).
	 *
	 * @param array $values Settings.
	 */
	public static function save( array $values ) {
		update_option( self::OPTION, $values, true );
		self::flush();
	}

	/**
	 * Forget cached values (after updates).
	 */
	public static function flush() {
		self::$values = null;
		self::$lists  = array();
	}

	/**
	 * Hash of the settings that change optimised asset output.
	 *
	 * @return string
	 */
	public static function output_hash() {
		$parts = array( AIOM_VERSION );
		foreach ( self::OUTPUT_KEYS as $key ) {
			$parts[] = $key . '=' . wp_json_encode( self::get( $key ) );
		}
		return substr( md5( implode( '|', $parts ) ), 0, 8 );
	}

	/**
	 * Runtime state that must not change saved settings (safe mode, pause, conflicts).
	 *
	 * @return array
	 */
	public static function runtime() {
		$rt = get_option( self::RUNTIME, array() );
		return array_merge(
			array(
				'safe_mode'           => false,
				'paused_until'        => 0,
				'page_cache_conflict' => '',
			),
			is_array( $rt ) ? $rt : array()
		);
	}

	/**
	 * Update runtime state.
	 *
	 * @param array $changes Keys to change.
	 */
	public static function set_runtime( array $changes ) {
		update_option( self::RUNTIME, array_merge( self::runtime(), $changes ), true );
	}

	/**
	 * Why optimisation is globally off, or '' when it is on.
	 *
	 * @return string One of: constant, disabled, safe_mode, paused, ''.
	 */
	public static function inactive_reason() {
		if ( defined( 'AIOM_DISABLE' ) && AIOM_DISABLE ) {
			return 'constant';
		}
		if ( ! self::get( 'enabled' ) ) {
			return 'disabled';
		}
		$rt = self::runtime();
		if ( $rt['safe_mode'] ) {
			return 'safe_mode';
		}
		if ( (int) $rt['paused_until'] > time() ) {
			return 'paused';
		}
		return '';
	}
}
