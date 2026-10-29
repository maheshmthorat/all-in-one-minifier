<?php
/**
 * Compact Peast formatter: no indentation, newlines, comments or optional spaces.
 *
 * @package All_In_One_Minifier
 */

defined( 'ABSPATH' ) || exit;

/**
 * Formatter used by AIOM_JS_Renderer.
 */
class AIOM_JS_Formatter extends \AIOM\Vendor\Peast\Formatter\Base {
	// phpcs:disable WordPress.NamingConventions.ValidVariableName -- Peast property names.

	/**
	 * New line character.
	 *
	 * @var string
	 */
	protected $newLine = '';

	/**
	 * Indentation character.
	 *
	 * @var string
	 */
	protected $indentation = '';

	/**
	 * No spaces around operators.
	 *
	 * @var bool
	 */
	protected $spacesAroundOperators = false;

	/**
	 * Block decisions are made by AIOM_JS_Renderer.
	 *
	 * @var bool
	 */
	protected $alwaysWrapBlocks = false;

	/**
	 * Comments are dropped.
	 *
	 * @var bool
	 */
	protected $renderComments = false;
	// phpcs:enable
}
