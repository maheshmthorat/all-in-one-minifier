<?php
/**
 * JavaScript minifier built on the Peast ECMAScript parser (BSD-3-Clause, bundled).
 *
 * The source is parsed into an AST and rendered back without whitespace or
 * comments. Because output is generated from the syntax tree, semicolons and
 * line terminators that matter (ASI) are emitted explicitly, and strings,
 * template literals, regular expressions and numbers keep their raw source text.
 * Anything Peast cannot parse (syntax errors, unsupported proposals, JSX,
 * non-UTF-8 files) raises an exception and the original asset is served.
 *
 * The renderer subclass keeps block braces and object properties exactly as
 * written, so the output's syntax tree is identical to the input's.
 *
 * @package All_In_One_Minifier
 */

defined( 'ABSPATH' ) || exit;

/**
 * Safe JavaScript minification facade.
 */
class AIOM_JS_Minifier {

	/**
	 * Measured peak memory of the parser is ~55 bytes per source byte; keep a margin.
	 */
	const MEMORY_PER_BYTE = 70;

	/**
	 * Whether the required PHP extensions are present.
	 *
	 * @return bool
	 */
	public static function available() {
		return function_exists( 'mb_convert_encoding' ) && function_exists( 'mb_strlen' ) && function_exists( 'mb_check_encoding' );
	}

	/**
	 * Approximate memory needed to minify a script of the given size.
	 *
	 * @param int $bytes Source size.
	 * @return int
	 */
	public static function memory_needed( $bytes ) {
		return (int) ( $bytes * self::MEMORY_PER_BYTE ) + 8388608;
	}

	/**
	 * Minify JavaScript.
	 *
	 * @param string $js     Source.
	 * @param bool   $module Parse as an ES module.
	 * @return string
	 * @throws AIOM_Minify_Exception When the script cannot be minified safely.
	 */
	public static function minify( $js, $module = false ) {
		if ( ! self::available() ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal message, logged and escaped on display, never printed.
			throw new AIOM_Minify_Exception( 'The PHP mbstring extension is required for JavaScript minification.' );
		}
		if ( ! mb_check_encoding( $js, 'UTF-8' ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal message, logged and escaped on display, never printed.
			throw new AIOM_Minify_Exception( 'Script is not valid UTF-8; left unchanged to avoid corrupting text.' );
		}
		if ( '' === trim( $js ) ) {
			return $js;
		}
		$source_type = $module ? \AIOM\Vendor\Peast\Peast::SOURCE_TYPE_MODULE : \AIOM\Vendor\Peast\Peast::SOURCE_TYPE_SCRIPT;
		try {
			$ast      = \AIOM\Vendor\Peast\Peast::latest( $js, array( 'sourceType' => $source_type ) )->parse();
			$renderer = new AIOM_JS_Renderer();
			$renderer->setFormatter( new AIOM_JS_Formatter() );
			$out = $renderer->render( $ast );
		} catch ( Exception $e ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal message, logged and escaped on display, never printed.
			throw new AIOM_Minify_Exception( 'JavaScript parse error: ' . $e->getMessage() );
		} catch ( Error $e ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal message, logged and escaped on display, never printed.
			throw new AIOM_Minify_Exception( 'JavaScript minifier error: ' . $e->getMessage() );
		}
		if ( ! is_string( $out ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal message, logged and escaped on display, never printed.
			throw new AIOM_Minify_Exception( 'JavaScript renderer returned no output.' );
		}
		return $out;
	}
}
