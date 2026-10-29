<?php
/**
 * Exception thrown by the minification engines when input cannot be processed safely.
 *
 * @package All_In_One_Minifier
 */

defined( 'ABSPATH' ) || exit;

/**
 * Signals that the original content must be served unchanged.
 */
class AIOM_Minify_Exception extends Exception {
}
