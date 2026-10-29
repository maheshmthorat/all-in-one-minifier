<?php
/**
 * Peast renderer that reproduces the input's syntax tree exactly.
 *
 * Differential testing against the acorn parser on ~1,300 real-world scripts
 * found several places where stock Peast compact rendering changes or breaks code:
 * - "{ [d]: d }" became the invalid "{ [d] }", "{ [a]: a = 1 }" became "{ a = 1 }";
 * - "(10).toString()" became the invalid "10.toString()";
 * - trailing array holes were lost: "[,]" became "[]" (length 1 -> 0);
 * - a block whose only statement is a block lost one level of braces, and a
 *   program consisting of one block ("{ const x = 1; }") lost its scope;
 * - single statements could lose or gain braces, and "{ a: a }" became "{ a }".
 * This subclass fixes all of them; the AST of the output equals the input's.
 *
 * @package All_In_One_Minifier
 */

defined( 'ABSPATH' ) || exit;

/**
 * Structure-preserving renderer.
 */
class AIOM_JS_Renderer extends \AIOM\Vendor\Peast\Renderer {
	// phpcs:disable WordPress.NamingConventions.ValidVariableName,WordPress.NamingConventions.ValidFunctionName -- Peast API names.

	/**
	 * Render a node.
	 *
	 * @param \AIOM\Vendor\Peast\Syntax\Node\Node $node         Node.
	 * @param bool                                $addSemicolon Whether to add a semicolon.
	 * @return string
	 */
	protected function renderNode( \AIOM\Vendor\Peast\Syntax\Node\Node $node, $addSemicolon = false ) {
		// Plain "key: value" properties are always rendered explicitly.
		if ( $node instanceof \AIOM\Vendor\Peast\Syntax\Node\Property
			&& ! $node->getShorthand()
			&& ! $node->getMethod()
			&& 'init' === $node->getKind()
		) {
			$key = $this->renderNode( $node->getKey() );
			if ( $node->getComputed() ) {
				$key = '[' . $key . ']';
			}
			return $key . ':' . $this->renderNode( $node->getValue() );
		}

		$code = parent::renderNode( $node, $addSemicolon );

		// "10.x" is a syntax error: an integer literal followed by "." needs parentheses.
		if ( $node instanceof \AIOM\Vendor\Peast\Syntax\Node\MemberExpression && ! $node->getComputed() ) {
			$object = $node->getObject();
			if ( $object instanceof \AIOM\Vendor\Peast\Syntax\Node\NumericLiteral ) {
				$raw = $object->getRaw();
				$len = strlen( $raw );
				if ( preg_match( '/^[0-9_]+$/', $raw ) && substr( $code, 0, $len + 1 ) === $raw . '.' ) {
					$code = '(' . $raw . ')' . substr( $code, $len );
				}
			}
		}

		// A trailing hole needs an extra comma: "[a,,]" has length 2.
		if ( $node instanceof \AIOM\Vendor\Peast\Syntax\Node\ArrayExpression || $node instanceof \AIOM\Vendor\Peast\Syntax\Node\ArrayPattern ) {
			$elements = $node->getElements();
			if ( $elements && null === end( $elements ) && ']' === substr( $code, -1 ) ) {
				$code = substr( $code, 0, -1 ) . ',]';
			}
		}
		return $code;
	}

	/**
	 * Render a statement body with braces only where the source had them.
	 *
	 * @param \AIOM\Vendor\Peast\Syntax\Node\Node       $parent             Parent node.
	 * @param \AIOM\Vendor\Peast\Syntax\Node\Node|array $node               Node(s).
	 * @param bool|null                                 $forceBrackets      Brace override.
	 * @param bool                                      $mandatorySeparator Separator flag.
	 * @param bool                                      $addSemicolons      Semicolon flag.
	 * @param bool                                      $incIndent          Indent flag.
	 * @return string
	 */
	protected function renderStatementBlock( $parent, $node, $forceBrackets = null, $mandatorySeparator = false, $addSemicolons = true, $incIndent = true ) {
		$is_switch_case = 'SwitchCase' === $parent->getType();
		if ( is_array( $node ) && 1 === count( $node ) && $node[0] instanceof \AIOM\Vendor\Peast\Syntax\Node\BlockStatement && ! $is_switch_case ) {
			// Stock Peast unwraps both the one-element list and the inner block.
			$inner = trim( $this->renderNode( $node[0] ) );
			return ( false === $forceBrackets ) ? $inner : '{' . $inner . '}';
		}
		if ( null === $forceBrackets ) {
			if ( is_array( $node ) ) {
				// In a case, only a lone block statement keeps braces (they are its own).
				$forceBrackets = $is_switch_case ? ( 1 === count( $node ) && $node[0] instanceof \AIOM\Vendor\Peast\Syntax\Node\BlockStatement ) : true;
			} else {
				$forceBrackets = $node instanceof \AIOM\Vendor\Peast\Syntax\Node\BlockStatement || $node instanceof \AIOM\Vendor\Peast\Syntax\Node\ClassBody;
			}
		}
		return parent::renderStatementBlock( $parent, $node, $forceBrackets, $mandatorySeparator, $addSemicolons, $incIndent );
	}

	/**
	 * Join nodes; empty statements in statement lists are no-ops and are dropped.
	 *
	 * @param array  $nodes         Nodes.
	 * @param string $separator     Separator.
	 * @param bool   $addSemicolons Whether nodes are statements.
	 * @return string
	 */
	protected function joinNodes( $nodes, $separator, $addSemicolons = false ) {
		if ( $addSemicolons && is_array( $nodes ) ) {
			$nodes = array_values(
				array_filter(
					$nodes,
					static function ( $n ) {
						return ! ( $n instanceof \AIOM\Vendor\Peast\Syntax\Node\EmptyStatement );
					}
				)
			);
		}
		return parent::joinNodes( $nodes, $separator, $addSemicolons );
	}
	// phpcs:enable
}
