<?php
/**
 * Exclusion rule matching.
 *
 * Asset rules (one per line): an exact handle ("jquery-core"), or part of the
 * file URL ("/plugins/slider/"). "*" is a wildcard.
 * URL rules: a path starting with "/" ("/checkout/*" or "/landing/"), matched
 * against the request path; anything else is a substring of the request URI.
 *
 * @package All_In_One_Minifier
 */

defined( 'ABSPATH' ) || exit;

/**
 * Rule matcher.
 */
class AIOM_Rules {

	/**
	 * Whether an asset matches any rule.
	 *
	 * @param string[] $rules  Rules.
	 * @param string   $handle Handle.
	 * @param string   $src    URL.
	 * @return string|false The matching rule.
	 */
	public static function match_asset( array $rules, $handle, $src ) {
		foreach ( $rules as $rule ) {
			if ( 0 === strcasecmp( $rule, (string) $handle ) ) {
				return $rule;
			}
			if ( false !== strpos( $rule, '*' ) ) {
				$re = self::wildcard( $rule );
				if ( preg_match( $re, (string) $handle ) || preg_match( $re, (string) $src ) ) {
					return $rule;
				}
			} elseif ( '' !== (string) $src && strlen( $rule ) > 2 && false !== stripos( $src, $rule ) ) {
				return $rule;
			}
		}
		return false;
	}

	/**
	 * Whether a request URI matches any URL rule.
	 *
	 * @param string[] $rules Rules.
	 * @param string   $uri   Request URI (path and optional query).
	 * @return string|false The matching rule.
	 */
	public static function match_url( array $rules, $uri ) {
		$path = (string) wp_parse_url( $uri, PHP_URL_PATH );
		foreach ( $rules as $rule ) {
			if ( '/' === $rule[0] ) {
				$subject = ( false !== strpos( $rule, '?' ) ) ? $uri : $path;
				if ( false !== strpos( $rule, '*' ) ) {
					if ( preg_match( '#^' . str_replace( '\*', '.*', preg_quote( $rule, '#' ) ) . '$#i', $subject ) ) {
						return $rule;
					}
				} elseif ( 0 === strcasecmp( untrailingslashit( $rule ), untrailingslashit( $subject ) ) ) {
					return $rule;
				}
			} elseif ( false !== stripos( $uri, $rule ) ) {
				return $rule;
			}
		}
		return false;
	}

	/**
	 * Case-insensitive regex for a wildcard pattern matching anywhere.
	 *
	 * @param string $rule Rule.
	 * @return string
	 */
	private static function wildcard( $rule ) {
		return '#' . str_replace( '\*', '.*', preg_quote( $rule, '#' ) ) . '#i';
	}
}
