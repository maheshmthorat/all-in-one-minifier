<?php

/**
 * Class All in one Minifier
 * The file that defines the core plugin class
 *
 * @author Mahesh Thorat
 * @link https://maheshthorat.web.app
 * @version 3.1
 * @package All_in_one_Minifier
 */
class All_In_One_Minifier_Admin
{
	private $plugin_name = AOMIN_PLUGIN_IDENTIFIER;
	private $version = AOMIN_PLUGIN_VERSION;
	private $notice = "";
	public function _getJSReady()
	{
?>
		<script>
			let cacheCount = 0;
			let oldBtnTxt;

			function _buildCache(permalink, totalPost) {
				jQuery('body').addClass('preparingCache');
				fetch(permalink + '?is_minify=1')
					.then(response => {
						if (response.ok) {
							if (totalPost == -1) {
								location.reload();
							} else {
								cacheCount++;
								jQuery('title').html('Generating for ' + cacheCount + ' out of ' + totalPost + ' url(s)');
								jQuery('.generateCache').html('Generated cache for <b>' + cacheCount + '</b> url(s) out of <b>' + totalPost + '</b> url(s)')
							}
							if (totalPost == cacheCount) {
								location.reload();
							}
							if (typeof allPostData !== 'undefined' && typeof allPostData[cacheCount] !== 'undefined') {
								setTimeout(function() {
									_buildCache(allPostData[cacheCount].post, allPostData.length);
								}, 300);
							}
						} else {
							console.error('Failed to fetch:', response.statusText);
						}
					})
					.catch(error => {
						console.error('Fetch error:', error);
					});
			}

			<?php
			if (is_admin()) {
			?>

				function _runCache() {
					jQuery('.generateCache').blur();
					jQuery('.aloneProgress').show();
					jQuery('.generateCache').addClass('alone-disabled');
					oldBtnTxt = jQuery('.generateCache').html();
					jQuery('.generateCache').html('Generating cache for <b>' + allPostData.length + '</b> urls')
					_buildCache(allPostData[0].post, allPostData.length);
				}
			<?php
			}
			?>
		</script>
		<style>
			.preparingCache {
				opacity: 0.5;
				pointer-events: none;
			}

			.minifyClearIcon::before {
				font-size: 16px;
			}
		</style>
	<?php
	}
	/**
	 * Return the tabs menu
	 */
	public function return_tabs_menu($tab)
	{
		$link = admin_url('admin.php');
		$list = array(
			array('tab1', 'all-in-one-minifier-admin', 'fa-cogs', __('<span class="dashicons dashicons-admin-tools"></span> Settings', 'all-in-one-minifier')),
			array('tab2', 'all-in-one-minifier-report', 'fa-chart-line', __('<span class="dashicons dashicons-chart-line"></span> Page Speed Report', 'all-in-one-minifier')),
			array('tab3', 'all-in-one-minifier-about', 'fa-info-circle', __('<span class="dashicons dashicons-editor-help"></span> About', 'all-in-one-minifier')),
			array('tab4', 'all-in-one-minifier-donate', 'fa-info-circle', __('<span class="dashicons dashicons-money-alt"></span> Say Thanks', 'all-in-one-minifier'))
		);

		$menu = null;
		foreach ($list as $item => $value) {
			$menu .= '<div class="tab-label ' . $value[0] . ' ' . (($tab == $value[0]) ? 'active' : '') . '"><a href="' . $link . '?page=' . $value[1] . '"><span>' . $value[3] . '</span></a></div>';
		}

		echo wp_kses_post($menu);
	}

	/**
	 * Register the stylesheet file(s) for the dashboard area
	 */
	public function enqueue_backend_standalone()
	{
		wp_register_style($this->plugin_name . '-standalone', plugin_dir_url(__FILE__) . 'assets/styles/standalone.css', array(), $this->version, 'all');
		wp_enqueue_style($this->plugin_name . '-standalone');
	}

	/**
	 * Update `Options` on form submit
	 */
	public function return_update_options()
	{
		if ((isset($_POST['all-in-one-minifier-update-option'])) && ($_POST['all-in-one-minifier-update-option'] == 'true')
			&& check_admin_referer('pwm-referer-form', 'pwm-referer-option')
		) {
			$opts = array('start_minify' => 'off', 'admin' => 'off', 'loadtime' => 'off');

			if (isset($_POST['_all-in-one_minifier']['start_minify'])) {
				$opts['start_minify'] = 'on';
			}
			// if(isset($_POST['_all-in-one_minifier']['admin']))
			// {
			// 	$opts['admin'] = 'on';
			// }
			// if(isset($_POST['_all-in-one_minifier']['loadtime']))
			// {
			// 	$opts['loadtime'] = 'on';
			// }
			update_option('_all-in-one_minifier', $opts);
			$this->notice = array('success', __('Your settings have been successfully updated.', 'all-in-one-minifier'));

			// header('location:' . admin_url('admin.php?page=all-in-one-minifier-admin') . '&status=updated');
			// die();
		}
	}

	/**
	 * Return the `Options` page
	 */
	public function return_options_page()
	{
		$this->_getJSReady();

		$opts = get_option('_all-in-one_minifier');

		// if ((isset($_GET['status'])) && ($_GET['status'] == 'updated')) {
		// 	$this->notice = array('success', __('Your settings have been successfully updated.', AOMIN_PLUGIN_IDENTIFIER));
		// }

		$allPostTypes = array();
		$args = array(
			'public' => true,
		);
		$post_types = get_post_types($args, 'objects');
		foreach ($post_types as $post_type_obj) :
			if ($post_type_obj->name != 'attachment') {
				array_push($allPostTypes, $post_type_obj->name);
			}
		endforeach;

		$wc_query = new WP_Query(array(
			'post_type' => $allPostTypes,
			'post_status' => 'publish',
			'posts_per_page' => -1
		));
		$allPostData = array();
		if ($wc_query->have_posts()) {
			while ($wc_query->have_posts()) {
				$wc_query->the_post();
				$post_id = get_the_ID();
				$allPostData[] = ['post' => get_permalink($post_id)];
			}
		}
		$disablePluginOption = '';
		$frontpage_id = get_option('page_on_front');
		if (!isset($opts['start_minify']) || ($opts['start_minify']) == 'off') {
			$disablePluginOption = 'alone-disabled-options';
		}
		if ($frontpage_id == '' || $frontpage_id == '0') {
			$disablePluginOption = 'alone-disabled-options';
		}
	?>
		<div class="wrap">
			<section class="wpbnd-wrapper">
				<div class="wpbnd-container">
					<div class="wpbnd-tabs">
						<?php echo wp_kses_post($this->return_plugin_header()); ?>
						<main class="tabs-main">
							<?php echo wp_kses_post($this->return_tabs_menu('tab1')); ?>
							<section class="tab-section">
								<?php if (isset($this->notice) && !empty($this->notice)) { ?>
									<div class="wpbnd-notice <?php echo esc_attr($this->notice[0]); ?>">
										<span class="close-btn" onclick="this.parentElement.style.display='none';">&times;</span>
										<span><?php echo esc_attr($this->notice[1], AOMIN_PLUGIN_IDENTIFIER); ?></span>
									</div>
								<?php } elseif ((!isset($opts['start_minify']) || ($opts['start_minify']) == 'off')) { ?>
									<div class="wpbnd-notice warning">
										<span class="close-btn" onclick="this.parentElement.style.display='none';">&times;</span>
										<span><?php echo esc_html(__('You have not set up your minifier options ! In order to do so, please use the below form.', 'all-in-one-minifier')); ?></span>
									</div>
								<?php } else { ?>
									<div class="wpbnd-notice info">
										<span class="close-btn" onclick="this.parentElement.style.display='none';">&times;</span>
										<span><?php echo esc_html(__('Your plugin is properly configured! You can change your minifier options using the below settings.', 'all-in-one-minifier')); ?></span>
									</div>
								<?php } ?>
								<form method="POST">
									<input type="hidden" name="all-in-one-minifier-update-option" value="true" />
									<?php wp_nonce_field('pwm-referer-form', 'pwm-referer-option'); ?>
									<div class="wpbnd-form">
										<div class="field">
											<?php $fieldID = uniqid(); ?>
											<label class="label"><span class="dashicons dashicons-yes"></span><?php echo esc_html(__('Enable Minify', 'all-in-one-minifier')); ?></label>
											<label class="switchContainer">
												<input id="<?php echo esc_attr($fieldID); ?>" type="checkbox" name="_all-in-one_minifier[start_minify]" class="onoffswitch-checkbox input-minifier" <?php if ((isset($opts['start_minify'])) && ($opts['start_minify'] == 'on')) {
																																																															echo 'checked="checked"';
																																																														}	?> />
												<span for="<?php echo esc_attr($fieldID); ?>" class="sliderContainer round"></span>
											</label>
											<div class="small">
												<small><?php echo esc_html(__('Do you want to minify your HTML + Internal CSS + JS for frontend ?', 'all-in-one-minifier')); ?></small>
											</div>
											<div class="small">
												<br />
												<input type="submit" class="button button-primary button-theme" value="<?php echo esc_html(__('Update Settings', 'all-in-one-minifier')); ?>">
											</div>
										</div>

										<div class="field" style="display: none;">
											<?php $fieldID = uniqid(); ?>
											<span class="label"><?php echo esc_html(__('Minify CMS', 'all-in-one-minifier')); ?></span>
											<label class="switchContainer">
												<input id="<?php echo esc_attr($fieldID); ?>" type="checkbox" name="_all-in-one_minifier[admin]" class="onoffswitch-checkbox input-minifier" <?php if ((isset($opts['admin'])) && ($opts['admin'] == 'on')) {
																																																												echo 'checked="checked"';
																																																											} ?> />
												<span for="<?php echo esc_attr($fieldID); ?>" class="sliderContainer round"></span>
											</label>
											<div class="small">
												<small><?php echo esc_html(__('Do you want to minify your HTML + Internal CSS + JS for admin panel?', 'all-in-one-minifier')); ?></small>
											</div>
										</div>

										<div class="field" style="display:none">
											<span class="label"><?php echo esc_html(__('Show Load Time', 'all-in-one-minifier')); ?></span>
											<label class="switchContainer">
												<?php $fieldID = uniqid(); ?>
												<input id="<?php echo esc_attr($fieldID); ?>" type="checkbox" name="_all-in-one_minifier[loadtime]" class="onoffswitch-checkbox input-minifier" <?php if ((isset($opts['loadtime'])) && ($opts['loadtime'] == 'on')) {
																																																													echo 'checked="checked"';
																																																												} ?> />
												<span for="<?php echo esc_attr($fieldID); ?>" class="sliderContainer round"></span>
											</label>
											<div class="small">
												<small><?php echo esc_html(__('Do you want to show Load Time in Console?', 'all-in-one-minifier')); ?></small>
											</div>

										</div>

										<?php
										if ($frontpage_id == '' || $frontpage_id == '0') {
										?>
											<div class="tab-section">
												<div class="wpbnd-notice warning">
													<span><?php echo esc_html(__('You have not set front page you need to set it first !', 'all-in-one-minifier')); ?> <a href="./options-reading.php" target="_blank">click here</a></span>
												</div>
											</div>
										<?php
										}
										?>
										<div class="field <?php echo esc_attr($disablePluginOption); ?>">
											<label class="label"><span class="dashicons dashicons-cloud"></span><?php echo esc_html(__('Cache', 'all-in-one-minifier')); ?>
												&lt;EXPREMENTAL /&gt;
											</label>
											<br><br>
											<?php
											if ($frontpage_id != '' || $frontpage_id > 0) {
											?>
												<a class="generateCache" <?php if ($disablePluginOption == '') { ?> href="javascript:_runCache()" <?php } ?>><span class="dashicons dashicons-admin-settings"></span> Generate Cache</a>
												<br>
												<progress class="aloneProgress"></progress>
												<br>
												<div class="small"> for <?php echo esc_attr(count($allPostData)); ?> urls
													<?php
													global $wpdb;
													$table_name = $wpdb->prefix . 'alone_minifier_analysis';
													$pageposts = $wpdb->get_results("SELECT datetime FROM $table_name ORDER BY id DESC LIMIT 1");
													if (count($pageposts) > 0) {
													?>
														<b>
															last cache on
															<?php
															echo esc_attr(gmdate('d M Y H:i A', strtotime($pageposts[0]->datetime)));
															?>
														</b>
													<?php
													}
													?>
												</div>
											<?php
											}
											?>
										</div>
									</div>
								</form>
							</section>
						</main>
					</div>
				</div>
			</section>
		</div>
		<script type="text/javascript">
			let allPostData = <?php echo wp_json_encode($allPostData); ?>;
		</script>
	<?php
	}

	/**
	 * Return the plugin header
	 */
	public function return_plugin_header()
	{
		$html = '<div class="header-plugin"><span class="header-icon"><span class="dashicons dashicons-admin-settings"></span></span> <span class="header-text">' . esc_attr(AOMIN_PLUGIN_FULLNAME) . '</span></div>';
		return $html;
	}

	/**
	 * Return the `About` page
	 */
	public function return_about_page()
	{
	?>
		<div class="wrap">
			<section class="wpbnd-wrapper">
				<div class="wpbnd-container">
					<div class="wpbnd-tabs">
						<?php echo wp_kses_post($this->return_plugin_header()); ?>
						<main class="tabs-main about">
							<?php echo wp_kses_post($this->return_tabs_menu('tab3')); ?>
							<section class="tab-section">
								<img alt="Mahesh Thorat" src="https://secure.gravatar.com/avatar/13ac2a68e7fba0cc0751857eaac3e0bf?s=100&amp;d=mm&amp;r=g" class="avatar avatar-100 photo profile-image" style="height:100px;width:100px;">
								<div class="profile-by">
									<p>© <?php echo esc_attr(gmdate('Y')); ?> - created by <a class="link" href="https://maheshthorat.web.app/" target="_blank"><b>Mahesh Mohan Thorat</b></a></p>
								</div>
							</section>
							<section class="helpful-links">
								<b>Other Plugins</b>
								<ul>
									<li>
										<a href="//wordpress.org/plugins/ajax-loading/">
											<img srcset="https://ps.w.org/ajax-loading/assets/icon-128x128.png?rev=2838964, https://ps.w.org/ajax-loading/assets/icon-256x256.png?rev=2838964 2x" src="https://ps.w.org/ajax-loading/assets/icon-256x256.png?rev=2838964"> </a>

										<div class="plugin-info-container">
											<h4>
												<a href="//wordpress.org/plugins/ajax-loading/">AJAX Loading</a>
											</h4>
										</div>
									</li>
									<li>
										<a href="//wordpress.org/plugins/all-in-one-minifier/">
											<img srcset="https://ps.w.org/all-in-one-minifier/assets/icon-128x128.png?rev=2707658, https://ps.w.org/all-in-one-minifier/assets/icon-256x256.png?rev=2707658 2x" src="https://ps.w.org/all-in-one-minifier/assets/icon-256x256.png?rev=2707658"> </a>

										<div class="plugin-info-container">
											<h4>
												<a href="//wordpress.org/plugins/all-in-one-minifier/">All in one Minifier</a>
											</h4>
										</div>
									</li>
									<li>
										<a href="//wordpress.org/plugins/all-in-one-wp-content-security/">
											<img srcset="https://ps.w.org/all-in-one-wp-content-security/assets/icon-128x128.png?rev=2712431, https://ps.w.org/all-in-one-wp-content-security/assets/icon-256x256.png?rev=2712431 2x" src="https://ps.w.org/all-in-one-wp-content-security/assets/icon-256x256.png?rev=2712431"> </a>

										<div class="plugin-info-container">
											<h4>
												<a href="//wordpress.org/plugins/all-in-one-wp-content-security/">All in one WP Content Protector</a>
											</h4>
										</div>
									</li>
									<li>
										<a href="//wordpress.org/plugins/better-smooth-scroll/">
											<img srcset="https://ps.w.org/better-smooth-scroll/assets/icon-128x128.png?rev=2829532, https://ps.w.org/better-smooth-scroll/assets/icon-256x256.png?rev=2829532 2x" src="https://ps.w.org/better-smooth-scroll/assets/icon-256x256.png?rev=2829532"> </a>

										<div class="plugin-info-container">
											<h4>
												<a href="//wordpress.org/plugins/better-smooth-scroll/">Better Smooth Scroll</a>
											</h4>
										</div>
									</li>
								</ul>
							</section>
							<section class="helpful-links">
								<b>helpful links</b>
								<ul>
									<li><a href="https://pagespeed.web.dev/" target="_blank">PageSpeed</a></li>
									<li><a href="https://gtmetrix.com/" target="_blank">GTmetrix</a></li>
									<li><a href="https://www.webpagetest.org" target="_blank">Web Page Test</a></li>
									<li><a href="https://http3check.net/" target="_blank">http3check</a></li>
									<li><a href="https://sitecheck.sucuri.net/" target="_blank">Sucuri - security check</a></li>
								</ul>
							</section>
						</main>
					</div>
				</div>
			</section>
		</div>
	<?php
	}

	public function AIOMformatSizeUnits($bytes)
	{
		if ($bytes >= 1073741824) {
			$bytes = number_format($bytes / 1073741824, 2) . ' GB';
		} elseif ($bytes >= 1048576) {
			$bytes = number_format($bytes / 1048576, 2) . ' MB';
		} elseif ($bytes >= 1024) {
			$bytes = number_format($bytes / 1024, 2) . ' KB';
		} elseif ($bytes > 1) {
			$bytes = $bytes . ' bytes';
		} elseif ($bytes == 1) {
			$bytes = $bytes . ' byte';
		} else {
			$bytes = '0 bytes';
		}

		return $bytes;
	}

	public function return_generate_loadTime_report()
	{
		$this->_getJSReady();
		$opts = get_option('_all-in-one_minifier');
		$disablePluginOption = '';
		$frontpage_id = get_option('page_on_front');
		if (!isset($opts['start_minify']) || ($opts['start_minify']) == 'off') {
			$disablePluginOption = 'alone-disabled-options';
		}
		if ($frontpage_id == '' || $frontpage_id == '0') {
			$disablePluginOption = 'alone-disabled-options';
		}
	?>
		<div class="wrap">
			<section class="wpbnd-wrapper">
				<div class="wpbnd-container">
					<div class="wpbnd-tabs">
						<?php echo wp_kses_post($this->return_plugin_header()); ?>
						<main class="tabs-main about">
							<?php echo wp_kses_post($this->return_tabs_menu('tab2')); ?>
							<?php
							$allPostTypes = array();

							$nonce = wp_create_nonce('all-in-one-minifier');
							if ((@$_GET['filter-post-type'] == '' || @$_GET['filter-post-type'] == 'all') && wp_verify_nonce($nonce, 'all-in-one-minifier')) {
								$args = array(
									'public' => true,
								);
								$post_types = get_post_types($args, 'objects');
								foreach ($post_types as $post_type_obj) :
									if ($post_type_obj->name != 'attachment') {
										array_push($allPostTypes, $post_type_obj->name);
									}
								endforeach;
							} else {
								array_push($allPostTypes, $_GET['filter-post-type']);
							}
							$paged = @$_GET['paged'];
							set_query_var('paged', $paged);
							$paged = (get_query_var('paged')) ? get_query_var('paged') : 1;
							$wpposts = new WP_Query(
								array(
									'post_type' => $allPostTypes,
									'posts_per_page' => 10,
									'paged' => $paged,
									'post_status' => 'publish',
									'orderby' => 'title',
									'order' => 'ASC'
								)
							);
							if ($frontpage_id == '' || $frontpage_id == '0') {
							?>
								<div class="tab-section">
									<div class="wpbnd-notice warning">
										<span><?php echo esc_html(__('You have not set front page you need to set it first !', 'all-in-one-minifier')); ?> <a href="./options-reading.php" target="_blank">click here</a></span>
									</div>
								</div>
								<?php
							} else {
								if ($disablePluginOption != '') {
								?>
									<div class="tab-section">
										<div class="wpbnd-notice warning">
											<span><?php echo esc_html(__('You have not set up your minifier options !', 'all-in-one-minifier')); ?></span>
										</div>
									</div>
							<?php
								}
							}

							?>
							<section class="<?php echo esc_attr($disablePluginOption); ?>">
								<?php $this->generatePagination($wpposts); ?>
								<table class="wp-list-table widefat fixed striped table-view-list all_in_one_minifier_tables">
									<thead>
										<tr>
											<th scope="col" id="title" class="manage-column column-title column-primary sorted asc">
												<a><span>Content</span></a>
											</th>
											<th scope="col" class="manage-column column-date sortable asc"><a><span>Page Before Optimization</span></a></th>
											<th scope="col" class="manage-column column-date sortable asc"><a><span>Page After Optimization</span></a></th>
											<th scope="col" class="manage-column column-date sortable asc"><a><span>Minified %</span></a></th>
											<th scope="col" class="manage-column column-date sortable asc"><a><span>Date</span></a></th>
										</tr>
									</thead>
									<tbody id="the-list">
										<?php
										while ($wpposts->have_posts()) : $wpposts->the_post();
											$postID = get_the_ID();
											$post_title = get_the_title();
											$current_queried_post_type = get_post_type($postID);

											$docBeforeTimePrint = '';
											$docAfterTimePrint = '';
											$af_docBeforeTime = '';
											$af_docAfterTime = '';

											global $wpdb;
											$table_name = $wpdb->prefix . 'alone_minifier_analysis';
											$pageposts_1 = $wpdb->get_results("SELECT postID, docBeforeTime, docAfterTime, minifyStatus, datetime FROM $table_name WHERE postID = $postID ORDER BY minifyStatus");
											foreach ($pageposts_1 as $value_1) {
												$docBeforeTime = $value_1->docBeforeTime;
												$docAfterTime = $value_1->docAfterTime;
												$datetime = $value_1->datetime;

												$af_docBeforeTime = $docBeforeTime;
												$af_docAfterTime = $docAfterTime;

												$docBeforeTimePrint .= '<span>Before</span> : <span class="right"><b>' . esc_attr($this->AIOMformatSizeUnits($docBeforeTime)) . '</b></span>';
												$docAfterTimePrint .= '<span>After</span> : <span class="right"><b>' . esc_attr($this->AIOMformatSizeUnits($docAfterTime)) . '</b></span>';
											}
											$minifiedPerComp = '';
											$minifiedPerPrint = '';

											if ($af_docAfterTime > 0 && $af_docBeforeTime > 0) {
												$minifiedPerComp = ($af_docAfterTime / $af_docBeforeTime) * 100;
												$minifiedPerComp = $minifiedPerComp - 100;
												$minifiedPerComp = abs($minifiedPerComp);
												$minifiedPerCompClass = 'color_red';

												if ($af_docBeforeTime > $af_docAfterTime) {
													$minifiedPerCompClass = 'color_green';
												}
												$minifiedPerPrint .= '<span class="left"><span class="right ' . $minifiedPerCompClass . '"><b>' . number_format($minifiedPerComp, 2) . '</b> %</span>';
											}
										?>
											<tr id="post-<?php echo esc_attr($postID); ?>" class="iedit author-self level-0 post-<?php echo esc_attr($postID); ?> type-page status-publish hentry">
												<td class="title column-title has-row-actions column-primary page-title" data-colname="Page">
													<strong><a <?php if ($disablePluginOption == '') { ?> href="javascript:_buildCache('<?php echo esc_url(get_permalink($postID)); ?>', -1)" <?php } ?> class="row-title"><?php echo esc_attr($post_title); ?> <span class="dashicons dashicons-media-code"></span></a> — <span class="post-state"><?php echo esc_attr($current_queried_post_type); ?></span></strong>
													<div class="row-actions"></div>
													<button type="button" class="toggle-row"><span class="screen-reader-text">Show more details</span></button>
												</td>
												<?php
												if ($docBeforeTimePrint != '') {
												?>
													<td data-colname="Page Ready Time"><?php echo wp_kses_post($docBeforeTimePrint); ?></td>
													<td data-colname="Page Completely Loaded"><?php echo wp_kses_post($docAfterTimePrint); ?></td>
													<td data-colname="Minified %"><?php echo wp_kses_post($minifiedPerPrint); ?></td>
													<td class="date column-date" data-colname="Date"><small>Last cache on</small><br><?php echo esc_attr($datetime); ?></td>
												<?php
												} else {
												?>
													<td colspan="4" data-colname="Generate Report First">
														<small>Build cache for this <span class="post-state"><?php echo esc_attr($current_queried_post_type); ?></span></small><br>
														<a <?php if ($disablePluginOption == '') { ?> href="javascript:_buildCache('<?php echo esc_attr(get_permalink($postID)); ?>', -1)" <?php } ?> class="row-title">Click here <span class="dashicons dashicons-media-code"></span></a>
													</td>
												<?php
												}
												?>
											</tr>
										<?php
										endwhile;
										wp_reset_query();
										if (!$wpposts->have_posts()) {
										?>
											<tr>
												<td colspan="5">No records found.</td>
											</tr>
										<?php
										}
										?>
									</tbody>
									<tfoot>
										<tr>
											<th scope="col" id="title" class="manage-column column-title column-primary sorted asc">
												<a><span>Page</span></a>
											</th>
											<th scope="col" class="manage-column column-date sortable asc"><a><span>Page Ready Time</span></a></th>
											<th scope="col" class="manage-column column-date sortable asc"><a><span>Page Completely Loaded</span></a></th>
											<th scope="col" class="manage-column column-date sortable asc"><a><span>Minified %</span></a></th>
											<th scope="col" class="manage-column column-date sortable asc"><a><span>Date</span></a></th>
										</tr>
									</tfoot>
								</table>
								<?php $this->generatePagination($wpposts); ?>
							</section>
						</main>
					</div>
				</div>
			</section>
		</div>
	<?php
	}

	public function generatePagination($wpposts)
	{
	?>
		<div class="reportPagination">
			<span>Post Type</span>
			<form method="GET">
				<input type="hidden" name="page" value="all-in-one-minifier-report" />
				<select name="filter-post-type">
					<option value="all">All</option>
					<?php
					$args = array(
						'public' => true,
					);
					$post_types = get_post_types($args, 'objects');
					foreach ($post_types as $post_type_obj) :
						$labels = get_post_type_labels($post_type_obj);
						$selectOption = '';
						$nonce = wp_create_nonce('all-in-one-min');
						if (@$_GET['filter-post-type'] == $post_type_obj->name && wp_verify_nonce($nonce, 'all-in-one-min')) {
							$selectOption = 'selected';
						}
						if ($post_type_obj->name != 'attachment') {
					?>
							<option value="<?php echo esc_attr($post_type_obj->name); ?>" <?php echo esc_attr($selectOption) ?>><?php echo esc_html($labels->name); ?></option>
					<?php
						}
					endforeach;
					?>
				</select>
				<input type="submit" name="filter_action" class="button" value="Filter">
			</form>
			<span>
				<?php
				echo esc_attr($wpposts->found_posts); ?> items
			</span>
			<div class="pages">
				<?php
				$big = 999999999;
				$pageUrl = str_replace($big, '%#%', get_pagenum_link($big));
				$pageUrl = str_replace('#038;', '&', $pageUrl);
				echo wp_kses_post(paginate_links(array(
					// 'base' => str_replace( $big, '%#%', get_pagenum_link( $big ) ),
					'base' => $pageUrl,
					'format' => '?paged=%#%',
					'current' => max(1, get_query_var('paged')),
					'total' => $wpposts->max_num_pages
				)));
				?>
			</div>
		</div>
	<?php
	}

	public function return_donate_page()
	{
	?>
		<div class="wrap">
			<section class="wpbnd-wrapper">
				<div class="wpbnd-container">
					<div class="wpbnd-tabs">
						<?php echo wp_kses_post($this->return_plugin_header()); ?>
						<main class="tabs-main about">
							<?php echo wp_kses_post($this->return_tabs_menu('tab4')); ?>
							<section class="">
								<table class="wp-list-table widefat fixed striped table-view-list">
									<tbody id="the-list">
										<tr>
											<td><a href="https://rzp.io/l/maheshmthorat" target="_blank"><img width="160" src="<?php echo esc_url(plugin_dir_url(dirname(__FILE__))); ?>admin/assets/img/razorpay.svg" /></a></td>
										</tr>
										<tr>
											<td>
												<h3>Scan below code</h3>
												<img width="350" src="<?php echo esc_url(plugin_dir_url(dirname(__FILE__))); ?>admin/assets/img/qr.svg" />
												<br>
												<img width="350" src="<?php echo esc_url(plugin_dir_url(dirname(__FILE__))); ?>admin/assets/img/upi.png" />
												<br>
												<b>Mr Mahesh Mohan Thorat</b>
												<h3>UPI - maheshmthorat@oksbi</h3>
											</td>
										</tr>
									</tbody>
								</table>
							</section>
							<section class="helpful-links">
								<b>helpful links</b>
								<ul>
									<li><a href="https://pagespeed.web.dev/" target="_blank">PageSpeed</a></li>
									<li><a href="https://gtmetrix.com/" target="_blank">GTmetrix</a></li>
									<li><a href="https://www.webpagetest.org" target="_blank">Web Page Test</a></li>
									<li><a href="https://http3check.net/" target="_blank">http3check</a></li>
									<li><a href="https://sitecheck.sucuri.net/" target="_blank">Sucuri - security check</a></li>
								</ul>
							</section>
						</main>
					</div>
				</div>
			</section>
		</div>
		<?php	}

	/**
	 * Return Backend Menu
	 */
	public function return_admin_menu()
	{
		add_menu_page(AOMIN_PLUGIN_FULLNAME, AOMIN_PLUGIN_FULLNAME, 'manage_options', 'all-in-one-minifier-admin', array($this, 'return_options_page'), 'dashicons-editor-code', 20);
		add_submenu_page('all-in-one-minifier-admin', 'Settings', 'Settings', 'manage_options', 'all-in-one-minifier-admin', array($this, 'return_options_page'));
		add_submenu_page('all-in-one-minifier-admin', 'Page Speed Report', 'Page Speed Report', 'manage_options', 'all-in-one-minifier-report', array($this, 'return_generate_loadTime_report'));
		add_submenu_page('all-in-one-minifier-admin', 'About', 'About', 'manage_options', 'all-in-one-minifier-about', array($this, 'return_about_page'));
		add_submenu_page('all-in-one-minifier-admin', 'Donate', 'Donate', 'manage_options', 'all-in-one-minifier-donate', array($this, 'return_donate_page'));
	}

	public function call_action_frontend_js()
	{
		$this->_getJSReady();
	}

	public function call_action_generate_loadTime_report()
	{
		if (get_the_ID() > 0) {
		?>
			<script type="text/javascript">
				'use strict';
				var url_string = location.href;
				var url = new URL(url_string);
				var is_minify = url.searchParams.get("is_minify");
				var is_report = url.searchParams.get("is_report");

				var minifyStatus = 0;
				if (is_minify == 1) {
					minifyStatus = 1;
				}

				var beforeload = (new Date()).getTime();
				var intedocBeforeTime;
				var docAfterTime;

				function retPageLoadTime(condtion) {
					var afterload = (new Date()).getTime();
					var seconds = null;

					seconds = (afterload - beforeload) / 1000;
					return seconds;
				}
				document.addEventListener('readystatechange', event => {
					if (event.target.readyState === "interactive") {
						intedocBeforeTime = retPageLoadTime('load');
					}
					if (event.target.readyState === "complete") {
						docAfterTime = retPageLoadTime('complete');
						if (is_report == null) {
							_loadAjx('complete')
						}
					}
				});

				function _loadAjx(condition) {
					let httpc = new XMLHttpRequest();
					let url = "<?php echo esc_url(plugins_url(AOMIN_PLUGIN_IDENTIFIER)); ?>/admin/admin-ajax.php";
					httpc.open("POST", url, true);
					httpc.onreadystatechange = function() {
						if (httpc.readyState == 4 && httpc.status == 200) {
							if (is_minify == null) {
								location.href = location.href + "&is_minify=1"
							} else if (is_minify == 1 && is_report == null) {
								location.href = location.href + "&is_report=true"
							}
						}
					};
					var data = JSON.stringify({
						'condition': condition,
						'id': <?php echo esc_attr(get_the_ID()); ?>,
						'minifyStatus': minifyStatus,
						'intedocBeforeTime': intedocBeforeTime,
						'docAfterTime': docAfterTime,
					});
					httpc.send(data);
				}
			</script>
		<?php
		}
	}

	public function call_action_generate_report_css()
	{
		$nonce = wp_create_nonce('all-in-one-min');
		if ((@$_GET['generatereport'] == 'true' && !isset($_GET['is_report']) && (@$_GET['is_minify'] == '' || @$_GET['is_minify'] == '1')) && wp_verify_nonce($nonce, 'ajax-loading')) {
		?>
			<div class="popPageReport">
				<div class="popPageReport-content">
					<h4>Please wait while we generating report for this page.</h4>
					<p>
						This will take less than a minute.<br>
						<small><b>Note - Page will reload several times to generate report</b></small>
					</p>
					<?php if ($_GET['is_minify'] == '') { ?>
						<h5 class="color_red">Generating without <span>web optimizations</span></h5>
					<?php }
					if ($_GET['is_minify'] == '1') { ?>
						<h5 class="color_green">Generating with <span>web optimizations</span></h5>
					<?php } ?>
					<a class="btnCancel" href="<?php echo esc_url(get_permalink(get_the_ID())); ?>">Cancel Report</a>
				</div>
			</div>
		<?php
		} else if (@$_GET['is_report'] == 'true') {
			$postID = get_the_ID();
			global $wpdb;
			$table_name = $wpdb->prefix . 'alone_minifier_analysis';
			$pageposts_1 = $wpdb->get_results("SELECT postID, docBeforeTime, docAfterTime, minifyStatus, datetime FROM $table_name WHERE postID = $postID ORDER BY minifyStatus");
			foreach ($pageposts_1 as $value_1) {
				$docBeforeTime = $value_1->docBeforeTime;
				$docAfterTime = $value_1->docAfterTime;
				$minifyStatus = $value_1->minifyStatus;
				$datetime = $value_1->datetime;
				$docBeforeTimePrint = '';
				if ($minifyStatus == 0) {
					$bef_docBeforeTime = $docBeforeTime;
					$bef_docAfterTime = $docAfterTime;

					$docBeforeTimePrint .= '<span>Before</span> : <span class="right"><b>' . $docBeforeTime . '</b> Sec</span><br>';
					$docAfterTime .= '<span>Before</span> : <span class="right"><b>' . $docAfterTime . '</b> Sec</span><br>';
				}
				if ($minifyStatus == 1) {
					$af_docBeforeTime = $docBeforeTime;
					$af_docAfterTime = $docAfterTime;

					$docBeforeTimePrint .= '<span>After</span> : <span class="right"><b>' . $docBeforeTime . '</b> Sec</span>';
					$docAfterTime .= '<span>After</span> : <span class="right"><b>' . $docAfterTime . '</b> Sec</span>';
				}
			}
			$minifiedPerReady = '';
			$minifiedPerComp = '';
			$minifiedPerPrint = '';
			if ($bef_docBeforeTime != '' && $bef_docAfterTime != '' && $af_docBeforeTime != '' && $af_docAfterTime != '') {
				$minifiedPerReady = ($bef_docBeforeTime / $af_docBeforeTime) * 100;
				$minifiedPerComp = ($bef_docAfterTime / $af_docAfterTime) * 100;
				$minifiedPerReadyClass = 'color_red';
				$minifiedPerCompClass = 'color_red';
				if ($bef_docBeforeTime > $af_docBeforeTime) {
					$minifiedPerReadyClass = 'color_green';
				}
				if ($bef_docAfterTime > $af_docAfterTime) {
					$minifiedPerCompClass = 'color_green';
				}
				$minifiedPerPrint = '<span class="left">Page Ready</span> : <span class="right ' . $minifiedPerReadyClass . '"><b>' . number_format($minifiedPerReady, 2) . '</b> %</span>';
				$minifiedPerPrint .= '<br><span class="left">Page Complete</span> : <span class="right ' . $minifiedPerCompClass . '"><b>' . number_format($minifiedPerComp, 2) . '</b> %</span>';
			}
			$backBtnLink = admin_url('admin.php') . '?page=all-in-one-minifier-report';
		?>
			<div class="popPageReport">
				<div class="popPageReport-content">
					<h4>
						<span class="dashicons dashicons-chart-line"></span> Page Speed Report
					</h4>
					<div class="reportBlock">
						Page Ready Time<br>
						<?php echo wp_kses_post(@$docBeforeTimePrint); ?>
					</div>
					<div class="reportBlock">
						Page Completely Loaded
						<?php echo wp_kses_post(@$docAfterTime); ?>
					</div>
					<div class="reportBlock">
						Minified %<br>
						<?php echo wp_kses_post($minifiedPerPrint); ?>
					</div>
					<a class="btnClose" href="<?php echo esc_url(get_permalink(get_the_ID())); ?>"><span class="dashicons dashicons-no"></span> Close Report</a>
					<a class="btnBack" href="<?php echo esc_url($backBtnLink); ?>"><span class="dashicons dashicons-arrow-left-alt"></span> Back to All Report</a>
					<a class="btnRescan" href="<?php echo esc_url(get_permalink(get_the_ID())); ?>?generatereport=true"><span class="dashicons dashicons-code-standards"></span> Re-Scan Page</a>

				</div>
			</div>
		<?php
		}
		?>
		<style type="text/css">
			.color_red {
				color: #eb1e2e;
				font-weight: bolder;
			}

			.color_green {
				color: #54b058;
				font-weight: bolder;
			}

			.reportBlock {
				padding: 10px;
				background-color: #efefef;
				margin-bottom: 10px;
			}

			.btnCancel,
			.btnClose,
			.btnBack,
			.btnRescan {
				color: #fff;
				padding: 8px 12px;
				border-radius: 4px;
				transition: 0.2s ease;
				margin-top: 20px;
				display: inline-block;
				margin-right: 10px;
			}

			.btnCancel {
				background-color: #eb1e2f;
			}

			.btnClose {
				background-color: #eb1e2f;
			}

			.btnBack {
				background-color: #0b5f9f;
			}

			.btnRescan {
				background-color: #86c233;
			}

			.btnCancel:hover,
			.btnClose:hover,
			.btnBack:hover,
			.btnRescan:hover {
				color: #fff;
				opacity: 0.6;
			}

			.popPageReport {
				display: block;
				position: fixed;
				z-index: 99999;
				padding-top: 100px;
				left: 0;
				top: 0;
				width: 100%;
				height: 100%;
				overflow: auto;
				background-color: rgb(0, 0, 0);
				background-color: rgba(0, 0, 0, 0.4);
			}

			.popPageReport-content {
				background-color: #fefefe;
				margin: auto;
				padding: 30px 20px;
				border: 1px solid #888;
				width: 50%;
			}
		</style>
<?php
	}

	public function aiomin_settings_link($links)
	{
		$url = get_admin_url() . 'admin.php?page=all-in-one-minifier-admin';
		$settings_link = ["<a href='$url'>" . __('Settings') . '</a>', "<a href='https://rzp.io/l/maheshmthorat' target='_blank'>Say Thanks</a>"];
		$links = array_merge(
			$settings_link,
			$links
		);
		return $links;
	}

	public function my_custom_admin_bar_link($wp_admin_bar)
	{
		if (!is_admin()) {
			$opts = get_option('_all-in-one_minifier');
			if (isset($opts['start_minify']) && $opts['start_minify'] == 'on') {
				$wp_admin_bar->add_menu(array(
					'id' => AOMIN_PLUGIN_IDENTIFIER . '-shortcut',
					'title' => '<span class="ab-icon dashicons dashicons-update minifyClearIcon"></span> Clear Cache',
					'href' => '#',
					'meta' => array(
						'onclick' => '_buildCache("' . get_permalink() . '", -1); return false;',
					),
				));
			}
		}
	}
}
