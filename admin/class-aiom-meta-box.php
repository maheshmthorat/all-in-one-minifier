<?php
/**
 * Per-page optimisation controls in the post editor (block and classic editors).
 *
 * @package All_In_One_Minifier
 */

defined( 'ABSPATH' ) || exit;

/**
 * Meta box.
 */
class AIOM_Meta_Box {

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'add' ) );
		add_action( 'save_post', array( __CLASS__, 'save' ), 10, 2 );
	}

	/**
	 * Add the box to viewable post types.
	 *
	 * @param string $post_type Post type.
	 */
	public static function add( $post_type ) {
		if ( 'attachment' === $post_type || ! is_post_type_viewable( $post_type ) ) {
			return;
		}
		add_meta_box( 'aiom-optimisation', __( 'Optimisation', 'all-in-one-minifier' ), array( __CLASS__, 'render' ), $post_type, 'side', 'low' );
	}

	/**
	 * Flag labels.
	 *
	 * @return array
	 */
	private static function labels() {
		return array(
			'all'        => __( 'Turn off all optimisation for this page', 'all-in-one-minifier' ),
			'html'       => __( 'Do not minify HTML', 'all-in-one-minifier' ),
			'css'        => __( 'Do not minify CSS or apply CSS loading changes', 'all-in-one-minifier' ),
			'js'         => __( 'Do not minify, defer or delay JavaScript', 'all-in-one-minifier' ),
			'advanced'   => __( 'Skip advanced loading (defer, delay, async CSS, critical CSS, hints)', 'all-in-one-minifier' ),
			'page_cache' => __( 'Do not page-cache this page', 'all-in-one-minifier' ),
		);
	}

	/**
	 * Render.
	 *
	 * @param WP_Post $post Post.
	 */
	public static function render( $post ) {
		$flags = get_post_meta( $post->ID, AIOM_Context::META, true );
		$flags = is_array( $flags ) ? $flags : array();
		wp_nonce_field( 'aiom_meta_' . $post->ID, 'aiom_meta_nonce' );
		$global = AIOM_Settings::inactive_reason();
		echo '<div class="aiom-metabox">';
		if ( '' !== $global ) {
			echo '<p class="aiom-warn">' . esc_html( AIOM_Admin::status_label() ) . '</p>';
		}
		foreach ( self::labels() as $flag => $label ) {
			printf(
				'<label class="aiom-metabox-flag"><input type="checkbox" name="aiom_flags[]" value="%1$s" %2$s> %3$s</label>',
				esc_attr( $flag ),
				checked( in_array( $flag, $flags, true ), true, false ),
				esc_html( $label )
			);
		}
		if ( current_user_can( 'manage_options' ) ) {
			echo '<p><label for="aiom-critical-css"><strong>' . esc_html__( 'Critical CSS for this page', 'all-in-one-minifier' ) . '</strong></label>';
			echo '<textarea id="aiom-critical-css" name="aiom_critical_css" rows="4" class="widefat code" spellcheck="false">' . esc_textarea( (string) get_post_meta( $post->ID, '_aiom_critical_css', true ) ) . '</textarea>';
			echo '<span class="description">' . esc_html__( 'Added after the site-wide critical CSS.', 'all-in-one-minifier' ) . '</span></p>';
		}

		if ( 'publish' === $post->post_status ) {
			$url    = (string) get_permalink( $post );
			$cached = AIOM_Settings::get( 'page_cache' ) ? AIOM_Page_Cache::cached_at( $url ) : false;
			echo '<hr><p class="aiom-metabox-status"><strong>' . esc_html__( 'Status', 'all-in-one-minifier' ) . '</strong><br>';
			if ( AIOM_Settings::get( 'page_cache' ) ) {
				echo esc_html(
					$cached
						/* translators: %s: time span. */
						? sprintf( __( 'Page cache: cached %s ago', 'all-in-one-minifier' ), human_time_diff( $cached ) )
						: __( 'Page cache: not cached yet', 'all-in-one-minifier' )
				);
				echo '<br>';
			}
			$history = AIOM_Reports::history( $url );
			if ( $history ) {
				$h = $history[0];
				echo esc_html(
					sprintf(
						/* translators: 1: percent change, 2: date. */
						__( 'Last report: HTML %1$s (%2$s)', 'all-in-one-minifier' ),
						AIOM_Admin::delta( $h['html']['bytes'], $h['html']['opt_bytes'] ),
						wp_date( get_option( 'date_format' ), $h['created'] )
					)
				);
				echo '<br>';
			}
			if ( current_user_can( 'manage_options' ) ) {
				echo '<a href="' . esc_url( admin_url( 'admin.php?page=aiom-reports&url=' . rawurlencode( $url ) ) ) . '">' . esc_html__( 'Measure this page', 'all-in-one-minifier' ) . '</a>';
			}
			echo '</p>';
		}
		echo '</div>';
	}

	/**
	 * Save.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post.
	 */
	public static function save( $post_id, $post ) {
		if ( ! isset( $_POST['aiom_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['aiom_meta_nonce'] ) ), 'aiom_meta_' . $post_id ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$flags = isset( $_POST['aiom_flags'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['aiom_flags'] ) ) : array();
		$flags = array_values( array_intersect( $flags, AIOM_Context::PAGE_FLAGS ) );
		if ( $flags ) {
			update_post_meta( $post_id, AIOM_Context::META, $flags );
		} else {
			delete_post_meta( $post_id, AIOM_Context::META );
		}
		if ( current_user_can( 'manage_options' ) && isset( $_POST['aiom_critical_css'] ) ) {
			$css = AIOM_Settings::sanitize_css( wp_unslash( $_POST['aiom_critical_css'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitised by sanitize_css().
			if ( '' === $css ) {
				delete_post_meta( $post_id, '_aiom_critical_css' );
			} else {
				update_post_meta( $post_id, '_aiom_critical_css', wp_slash( $css ) );
			}
		}
		if ( $post instanceof WP_Post && 'publish' === $post->post_status ) {
			AIOM_Page_Cache::purge_url( (string) get_permalink( $post ) );
		}
	}
}
