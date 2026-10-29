<?php
/**
 * Settings screen.
 *
 * @package All_In_One_Minifier
 */

defined( 'ABSPATH' ) || exit;

$aiom_sections = AIOM_Admin_Fields::sections();
$aiom_s        = AIOM_Settings::all();
$aiom_conflict = AIOM_Settings::runtime()['page_cache_conflict'];
$aiom_kses     = array(
	'code'   => array(),
	'strong' => array(),
	'em'     => array(),
);
?>
<div class="aiom-intro aiom-card">
	<div>
		<h2 class="aiom-card-title"><?php esc_html_e( 'Not sure what to choose?', 'all-in-one-minifier' ); ?></h2>
		<p><?php esc_html_e( 'The recommended settings minify HTML, CSS and JavaScript without changing when scripts run. Options marked “Test first” can affect how some themes and plugins behave: turn on test mode, try them, and check your key pages.', 'all-in-one-minifier' ); ?></p>
	</div>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="aiom_recommended">
		<input type="hidden" name="aiom_page" value="aiom-settings">
		<?php wp_nonce_field( 'aiom_recommended' ); ?>
		<button class="button" data-aiom-confirm="<?php esc_attr_e( 'Reset every switch and number to the recommended value? Your exclusion rules and critical CSS are kept.', 'all-in-one-minifier' ); ?>"><?php esc_html_e( 'Apply recommended settings', 'all-in-one-minifier' ); ?></button>
	</form>
</div>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="aiom-settings" id="aiom-settings-form">
	<input type="hidden" name="action" value="aiom_save_settings">
	<?php wp_nonce_field( 'aiom_save_settings' ); ?>
	<div class="aiom-settings-layout">
		<nav class="aiom-settings-nav" aria-label="<?php esc_attr_e( 'Setting groups', 'all-in-one-minifier' ); ?>">
			<?php foreach ( $aiom_sections as $aiom_id => $aiom_sec ) : ?>
				<a href="#aiom-sec-<?php echo esc_attr( $aiom_id ); ?>"><span class="dashicons dashicons-<?php echo esc_attr( $aiom_sec['icon'] ); ?>" aria-hidden="true"></span><?php echo esc_html( $aiom_sec['title'] ); ?></a>
			<?php endforeach; ?>
		</nav>
		<div class="aiom-settings-main">
			<?php foreach ( $aiom_sections as $aiom_id => $aiom_sec ) : ?>
				<section class="aiom-card aiom-section" id="aiom-sec-<?php echo esc_attr( $aiom_id ); ?>">
					<h2 class="aiom-card-title"><span class="dashicons dashicons-<?php echo esc_attr( $aiom_sec['icon'] ); ?>" aria-hidden="true"></span><?php echo esc_html( $aiom_sec['title'] ); ?></h2>
					<p class="aiom-section-desc"><?php echo wp_kses( $aiom_sec['desc'], $aiom_kses ); ?></p>
					<?php if ( 'page_cache' === $aiom_id && '' !== $aiom_conflict ) : ?>
						<div class="aiom-callout aiom-callout--info">
							<?php
							/* translators: %s: plugin name. */
							echo esc_html( sprintf( __( '%s is active and already caches pages, so this page cache stays off even if enabled here.', 'all-in-one-minifier' ), $aiom_conflict ) );
							?>
						</div>
					<?php endif; ?>
					<?php
					foreach ( $aiom_sec['fields'] as $aiom_key => $aiom_f ) :
						$aiom_default = AIOM_Settings::DEFAULTS[ $aiom_key ];
						$aiom_value   = $aiom_s[ $aiom_key ];
						$aiom_fid     = 'aiom-' . str_replace( '_', '-', $aiom_key );
						$aiom_name    = 'aiom[' . $aiom_key . ']';
						$aiom_by      = ( ! empty( $aiom_f['depends'] ) && 'page_cache' !== $aiom_f['depends'] && $aiom_s['auto_compat'] ) ? AIOM_Compat::yields_to( $aiom_f['depends'] ) : '';
						$aiom_is_bool = is_bool( $aiom_default );
						?>
						<div class="aiom-field<?php echo $aiom_is_bool ? ' aiom-field--toggle' : ''; ?>">
							<div class="aiom-field-text">
								<label for="<?php echo esc_attr( $aiom_fid ); ?>" class="aiom-field-label"><?php echo esc_html( $aiom_f['label'] ); ?></label>
								<?php if ( ! empty( $aiom_f['risk'] ) ) : ?>
									<span class="aiom-chip aiom-chip--warn"><?php esc_html_e( 'Test first', 'all-in-one-minifier' ); ?></span>
								<?php endif; ?>
								<?php if ( $aiom_default !== $aiom_value && ( $aiom_is_bool || is_int( $aiom_default ) ) ) : ?>
									<span class="aiom-chip aiom-chip--muted"><?php esc_html_e( 'Changed', 'all-in-one-minifier' ); ?></span>
								<?php endif; ?>
								<?php if ( ! empty( $aiom_f['desc'] ) ) : ?>
									<p class="description"><?php echo wp_kses( $aiom_f['desc'], $aiom_kses ); ?></p>
								<?php endif; ?>
								<?php if ( ! empty( $aiom_f['warn'] ) ) : ?>
									<p class="aiom-warn"><span class="dashicons dashicons-warning" aria-hidden="true"></span><?php echo esc_html( $aiom_f['warn'] ); ?></p>
								<?php endif; ?>
								<?php if ( '' !== $aiom_by ) : ?>
									<p class="aiom-note">
										<?php
										/* translators: %s: plugin name. */
										echo esc_html( sprintf( __( '%s already does this, so this option currently stands aside (see “Avoid duplicate optimisation”).', 'all-in-one-minifier' ), $aiom_by ) );
										?>
									</p>
								<?php endif; ?>
							</div>
							<div class="aiom-field-control">
								<?php if ( $aiom_is_bool ) : ?>
									<span class="aiom-switch">
										<input type="checkbox" role="switch" id="<?php echo esc_attr( $aiom_fid ); ?>" name="<?php echo esc_attr( $aiom_name ); ?>" value="1" <?php checked( $aiom_value ); ?>>
										<span class="aiom-switch-track" aria-hidden="true"></span>
									</span>
								<?php elseif ( is_int( $aiom_default ) ) : ?>
									<span class="aiom-number">
										<input type="number" id="<?php echo esc_attr( $aiom_fid ); ?>" name="<?php echo esc_attr( $aiom_name ); ?>" value="<?php echo esc_attr( $aiom_value ); ?>" min="<?php echo esc_attr( AIOM_Settings::RANGES[ $aiom_key ][0] ); ?>" max="<?php echo esc_attr( AIOM_Settings::RANGES[ $aiom_key ][1] ); ?>" class="small-text">
										<?php if ( ! empty( $aiom_f['unit'] ) ) : ?>
											<span><?php echo esc_html( $aiom_f['unit'] ); ?></span>
										<?php endif; ?>
									</span>
								<?php elseif ( is_array( $aiom_default ) ) : ?>
									<fieldset id="<?php echo esc_attr( $aiom_fid ); ?>" class="aiom-checklist">
										<?php
										foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $aiom_pt ) :
											if ( 'attachment' === $aiom_pt->name ) {
												continue;
											}
											?>
											<label><input type="checkbox" name="<?php echo esc_attr( $aiom_name ); ?>[]" value="<?php echo esc_attr( $aiom_pt->name ); ?>" <?php checked( in_array( $aiom_pt->name, (array) $aiom_value, true ) ); ?>> <?php echo esc_html( $aiom_pt->labels->name ); ?></label>
										<?php endforeach; ?>
									</fieldset>
								<?php elseif ( isset( $aiom_f['options'] ) ) : ?>
									<select id="<?php echo esc_attr( $aiom_fid ); ?>" name="<?php echo esc_attr( $aiom_name ); ?>">
										<?php foreach ( $aiom_f['options'] as $aiom_ov => $aiom_ol ) : ?>
											<option value="<?php echo esc_attr( $aiom_ov ); ?>" <?php selected( $aiom_value, $aiom_ov ); ?>><?php echo esc_html( $aiom_ol ); ?></option>
										<?php endforeach; ?>
									</select>
								<?php else : ?>
									<textarea id="<?php echo esc_attr( $aiom_fid ); ?>" name="<?php echo esc_attr( $aiom_name ); ?>" rows="<?php echo esc_attr( isset( $aiom_f['rows'] ) ? $aiom_f['rows'] : 3 ); ?>" class="large-text<?php echo ! empty( $aiom_f['code'] ) ? ' code' : ''; ?>" spellcheck="false" placeholder="<?php echo esc_attr( isset( $aiom_f['placeholder'] ) ? $aiom_f['placeholder'] : '' ); ?>"><?php echo esc_textarea( $aiom_value ); ?></textarea>
								<?php endif; ?>
							</div>
						</div>
					<?php endforeach; ?>
				</section>
			<?php endforeach; ?>
		</div>
	</div>
	<div class="aiom-savebar">
		<button type="submit" class="button button-primary button-large"><?php esc_html_e( 'Save changes', 'all-in-one-minifier' ); ?></button>
		<span class="description"><?php esc_html_e( 'Saving clears cached pages here and in detected cache plugins, so visitors get the new output.', 'all-in-one-minifier' ); ?></span>
	</div>
</form>
