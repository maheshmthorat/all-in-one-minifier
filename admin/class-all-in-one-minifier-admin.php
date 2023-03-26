<?php
/**
 * Class All in one Minifier
 * The file that defines the core plugin class
 *
 * @author Mahesh Thorat
 * @link https://maheshthorat.web.app
 * @version 2.2
 * @package All_in_one_Minifier
*/
class All_In_One_Minifier_Admin
{
	private $plugin_name = AOMIN_PLUGIN_IDENTIFIER;
	private $version = AOMIN_PLUGIN_VERSION;

	public function _getJSReady(){
		?>
		<script>
			function _0x260f(){const _0x312f7f=['6439716sZhRCg','126ZZQmbC','Cache\x20completed\x20for\x20','5572180rrpVlb','12iQTbEN','Generated\x20cache\x20for\x20<b>','removeClass','json','1685624dSdsJJ','\x20urls','actionBuildCache','reload','post_id','.aloneProgress','alone-disabled','</b>\x20urls\x20out\x20of\x20<b>','128215lXKZYu','addClass','.wpbnd-notice.success\x20span','ajax','html','show','1418915dUaDrl','undefined','Generating\x20cache\x20for\x20<b>','51532RgtUOv','hide','length','</b>\x20urls','.generateCache','4Zmcbma','blur','703024NugpWc'];_0x260f=function(){return _0x312f7f;};return _0x260f();}(function(_0x56e850,_0x353bbb){const _0x1b714b=_0x44cd,_0x32f9fc=_0x56e850();while(!![]){try{const _0x503121=-parseInt(_0x1b714b(0x130))/0x1*(-parseInt(_0x1b714b(0x13e))/0x2)+-parseInt(_0x1b714b(0x142))/0x3*(-parseInt(_0x1b714b(0x139))/0x4)+parseInt(_0x1b714b(0x136))/0x5*(parseInt(_0x1b714b(0x145))/0x6)+parseInt(_0x1b714b(0x140))/0x7+parseInt(_0x1b714b(0x149))/0x8+-parseInt(_0x1b714b(0x141))/0x9+-parseInt(_0x1b714b(0x144))/0xa;if(_0x503121===_0x353bbb)break;else _0x32f9fc['push'](_0x32f9fc['shift']());}catch(_0x252512){_0x32f9fc['push'](_0x32f9fc['shift']());}}}(_0x260f,0x62813));let cacheCount=0x0,oldBtnTxt;function _buildCache(_0x56aefa,_0x4eccc5){const _0x44cd90=_0x44cd;var _0x525b08={'action':_0x44cd90(0x14b),'post_id':_0x56aefa},_0x1be595=ajaxurl;jQuery[_0x44cd90(0x133)]({'url':_0x1be595,'type':'post','data':_0x525b08,'dataType':_0x44cd90(0x148),'success':function(_0x109044){const _0xefef00=_0x44cd90;if(_0x4eccc5==-0x1)location[_0xefef00(0x14c)]();else{if(_0x4eccc5==-0x2){}else cacheCount++,jQuery(_0xefef00(0x13d))[_0xefef00(0x134)](_0xefef00(0x146)+cacheCount+_0xefef00(0x12f)+_0x4eccc5+_0xefef00(0x13c));}_0x4eccc5==cacheCount&&(jQuery(_0xefef00(0x13d))[_0xefef00(0x134)](oldBtnTxt),jQuery(_0xefef00(0x14e))[_0xefef00(0x13a)](),jQuery(_0xefef00(0x13d))[_0xefef00(0x147)](_0xefef00(0x12e)),jQuery('.wpbnd-notice')[_0xefef00(0x131)]('success'),jQuery(_0xefef00(0x132))['eq'](0x1)[_0xefef00(0x134)](_0xefef00(0x143)+cacheCount+_0xefef00(0x14a))),typeof allPostData!==_0xefef00(0x137)&&typeof allPostData[cacheCount]!==_0xefef00(0x137)&&(allPostData[allPostData['length']-0x1]!=_0x56aefa&&setTimeout(function(){const _0x4f78e0=_0xefef00;_buildCache(allPostData[cacheCount][_0x4f78e0(0x14d)],allPostData[_0x4f78e0(0x13b)]);},0xc8));}});}function _0x44cd(_0x242c00,_0x328b32){const _0x260f00=_0x260f();return _0x44cd=function(_0x44cd78,_0x3a4eb6){_0x44cd78=_0x44cd78-0x12e;let _0x21cccb=_0x260f00[_0x44cd78];return _0x21cccb;},_0x44cd(_0x242c00,_0x328b32);}function _runCache(){const _0xa240e4=_0x44cd;jQuery('.generateCache')[_0xa240e4(0x13f)](),jQuery('.aloneProgress')[_0xa240e4(0x135)](),jQuery(_0xa240e4(0x13d))[_0xa240e4(0x131)]('alone-disabled'),oldBtnTxt=jQuery(_0xa240e4(0x13d))[_0xa240e4(0x134)](),jQuery('.generateCache')['html'](_0xa240e4(0x138)+allPostData[_0xa240e4(0x13b)]+_0xa240e4(0x13c)),_buildCache(allPostData[0x0][_0xa240e4(0x14d)],allPostData[_0xa240e4(0x13b)]);}
		</script>
		<?php
	}
	/**
	 * Return the tabs menu
	*/
	public function return_tabs_menu($tab)
	{
		$link = admin_url('admin.php');
		$list = array
		(
			array('tab1', 'all-in-one-minifier-admin', 'fa-cogs', __('<span class="dashicons dashicons-admin-tools"></span> Settings', AOMIN_PLUGIN_IDENTIFIER)),
			array('tab2', 'all-in-one-minifier-report', 'fa-chart-line', __('<span class="dashicons dashicons-chart-line"></span> Page Speed Report', AOMIN_PLUGIN_IDENTIFIER)),
			array('tab3', 'all-in-one-minifier-about', 'fa-info-circle', __('<span class="dashicons dashicons-editor-help"></span> About', AOMIN_PLUGIN_IDENTIFIER)),
			array('tab4', 'all-in-one-minifier-donate', 'fa-info-circle', __('<span class="dashicons dashicons-money-alt"></span> Donate', AOMIN_PLUGIN_IDENTIFIER))
		);

		$menu = null;
		foreach($list as $item => $value)
		{
			$menu.='<div class="tab-label '.$value[0].' '.(($tab == $value[0]) ? 'active' : '').'"><a href="'.$link.'?page='.$value[1].'"><span>'.$value[3].'</span></a></div>';
		}

		echo wp_kses_post($menu);
	}

	/**
	 * Register the stylesheet file(s) for the dashboard area
	*/
	public function enqueue_backend_standalone()
	{
		wp_register_style($this->plugin_name.'-standalone', plugin_dir_url(__FILE__).'assets/styles/standalone.css', array(), $this->version, 'all');
		wp_enqueue_style($this->plugin_name.'-standalone');
	}

	/**
	 * Update `Options` on form submit
	*/
	public function return_update_options()
	{
		if((isset($_POST['all-in-one-minifier-update-option'])) && ($_POST['all-in-one-minifier-update-option'] == 'true')
			&& check_admin_referer('pwm-referer-form', 'pwm-referer-option'))
		{
			$opts = array('start_minify' => 'off', 'admin' => 'off', 'loadtime' => 'off');

			if(isset($_POST['_all-in-one_minifier']['start_minify']))
			{
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
			$data = update_option('_all-in-one_minifier', $opts);
			header('location:'.admin_url('admin.php?page=all-in-one-minifier-admin').'&status=updated');
			die();
		}
	}

	/**
	 * Return the `Options` page
	*/
	public function return_options_page()
	{
		$this->_getJSReady();

		$opts = get_option('_all-in-one_minifier');

		if((isset($_GET['status'])) && ($_GET['status'] == 'updated'))
		{
			$notice = array('success', __('Your settings have been successfully updated.', AOMIN_PLUGIN_IDENTIFIER));
		}

		$allPostTypes = array();
		$args = array(
			'public' => true,
		);
		$post_types = get_post_types( $args, 'objects' );
		foreach ( $post_types as $post_type_obj ):
			if($post_type_obj->name != 'attachment')
			{
				array_push($allPostTypes, $post_type_obj->name);
			}
		endforeach;
		
		$wc_query = new WP_Query( array(
			'post_type' => $allPostTypes,
			'post_status' => 'publish',
			'posts_per_page' => -1
		) );
		$allPostData = array();
		if ($wc_query->have_posts())
		{
			while ($wc_query->have_posts())
			{
				$wc_query->the_post();
				$post_id = get_the_ID();
				$allPostData[] = ['post_id'=> $post_id];
			}
		}
		$disablePluginOption = '';
		$frontpage_id = get_option( 'page_on_front' );
		if (!isset($opts['start_minify']) || ($opts['start_minify']) == 'off')
		{
			$disablePluginOption = 'alone-disabled-options';
		}
		if($frontpage_id == '' || $frontpage_id == '0')
		{
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
								<?php if(isset($notice)) { ?>
									<div class="wpbnd-notice <?php echo esc_attr($notice[0]); ?>">
										<span class="close-btn" onclick="this.parentElement.style.display='none';">&times;</span>
										<span><?php echo esc_attr($notice[1], AOMIN_PLUGIN_IDENTIFIER); ?></span>
									</div>
								<?php } elseif((!isset($opts['start_minify']) || ($opts['start_minify']) == 'off')) { ?>
									<div class="wpbnd-notice warning">
										<span class="close-btn" onclick="this.parentElement.style.display='none';">&times;</span>
										<span><?php echo _e('You have not set up your minifier options ! In order to do so, please use the below form.', AOMIN_PLUGIN_IDENTIFIER); ?></span>
									</div>
								<?php } else { ?>
									<div class="wpbnd-notice info">
										<span class="close-btn" onclick="this.parentElement.style.display='none';">&times;</span>
										<span><?php echo _e('Your plugin is properly configured! You can change your minifier options using the below settings.', AOMIN_PLUGIN_IDENTIFIER); ?></span>
									</div>
								<?php } ?>
								<form method="POST">
									<input type="hidden" name="all-in-one-minifier-update-option" value="true" />
									<?php wp_nonce_field('pwm-referer-form', 'pwm-referer-option'); ?>
									<div class="wpbnd-form">
										<div class="field">
											<?php $fieldID = uniqid(); ?>
											<label class="label"><span class="dashicons dashicons-yes"></span><?php echo _e('Enable Minify', AOMIN_PLUGIN_IDENTIFIER); ?></label>
											<label class="switchContainer">
												<input id="<?php echo esc_attr($fieldID); ?>" type="checkbox" name="_all-in-one_minifier[start_minify]" class="onoffswitch-checkbox input-minifier" <?php if((isset($opts['start_minify'])) && ($opts['start_minify'] == 'on')) { echo 'checked="checked"'; } ?>/>
												<span for="<?php echo esc_attr($fieldID); ?>" class="sliderContainer round"></span>
											</label>
											<div class="small">
												<small><?php echo _e('Do you want to minify your HTML + Internal CSS + JS for frontend ?', AOMIN_PLUGIN_IDENTIFIER); ?></small>
											</div>
										</div>

										<div class="field" style="display: none;">
											<?php $fieldID = uniqid(); ?>
											<span class="label"><?php echo _e('Minify CMS', AOMIN_PLUGIN_IDENTIFIER); ?></span>
											<label class="switchContainer">
												<input id="<?php echo esc_attr($fieldID); ?>" type="checkbox" name="_all-in-one_minifier[admin]" class="onoffswitch-checkbox input-minifier" <?php if((isset($opts['admin'])) && ($opts['admin'] == 'on')) { echo 'checked="checked"'; } ?>/>
												<span for="<?php echo esc_attr($fieldID); ?>" class="sliderContainer round"></span>
											</label>
											<div class="small">
												<small><?php echo _e('Do you want to minify your HTML + Internal CSS + JS for admin panel?', AOMIN_PLUGIN_IDENTIFIER); ?></small>
											</div>
										</div>

										<div class="field" style="display:none">
											<span class="label"><?php echo _e('Show Load Time', AOMIN_PLUGIN_IDENTIFIER); ?></span>
											<label class="switchContainer">
												<?php $fieldID = uniqid(); ?>
												<input id="<?php echo esc_attr($fieldID); ?>" type="checkbox" name="_all-in-one_minifier[loadtime]" class="onoffswitch-checkbox input-minifier" <?php if((isset($opts['loadtime'])) && ($opts['loadtime'] == 'on')) { echo 'checked="checked"'; } ?>/>
												<span for="<?php echo esc_attr($fieldID); ?>" class="sliderContainer round"></span>
											</label>
											<div class="small">
												<small><?php echo _e('Do you want to show Load Time in Console?', AOMIN_PLUGIN_IDENTIFIER); ?></small>
											</div>
										</div>
										<div class="form-footer">
											<input type="submit" class="button button-primary button-theme" value="<?php _e('Update Settings', AOMIN_PLUGIN_IDENTIFIER); ?>">
										</div>
										<?php
										if($frontpage_id == '' || $frontpage_id == '0')
										{
											?>
											<div class="tab-section">
												<div class="wpbnd-notice warning">
													<span><?php echo _e('You have not set front page you need to set it first !', AOMIN_PLUGIN_IDENTIFIER); ?> <a href="./options-reading.php" target="_blank">click here</a></span>
												</div>
											</div>
											<?php
										}
										?>
										<div class="field <?php echo esc_attr($disablePluginOption); ?>">
											<label class="label"><span class="dashicons dashicons-cloud"></span><?php echo _e('Cache', AOMIN_PLUGIN_IDENTIFIER); ?></label>
											<br><br>
											<?php
											if($frontpage_id != '' || $frontpage_id > 0)
											{
												?>
												<a class="generateCache" <?php if($disablePluginOption == '') { ?> href="javascript:_runCache()"<?php } ?> ><span class="dashicons dashicons-admin-settings"></span> Generate Cache</a>
												<br>
												<progress class="aloneProgress"></progress>
												<br>
												<div class="small"> for <?php echo esc_attr(count($allPostData)); ?> urls
													<?php
													global $wpdb;
													$table_name = $wpdb->prefix . 'alone_minifier_analysis';
													$querystr = "SELECT datetime FROM $table_name WHERE 1 = 1 LIMIT 1 ";
													$pageposts = $wpdb->get_results($querystr, OBJECT);
													if(count($pageposts) > 0)
													{
														?>
														<b>
															last cache on
															<?php
															echo esc_attr(date('d M Y H:i A',strtotime($pageposts[0]->datetime)));
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
		$html = '<div class="header-plugin"><span class="header-icon"><span class="dashicons dashicons-admin-settings"></span></span> <span class="header-text">'.__(AOMIN_PLUGIN_FULLNAME, AOMIN_PLUGIN_IDENTIFIER).'</span></div>';
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
								<img alt="Mahesh Thorat" src="https://secure.gravatar.com/avatar/13ac2a68e7fba0cc0751857eaac3e0bf?s=100&amp;d=mm&amp;r=g" srcset="https://secure.gravatar.com/avatar/13ac2a68e7fba0cc0751857eaac3e0bf?s=200&amp;d=mm&amp;r=g 2x" class="avatar avatar-100 photo profile-image" height="100" width="100" >

								<div class="profile-by">
									<p>© <?php echo date('Y'); ?> - created by <a class="link" href="https://maheshthorat.web.app/" target="_blank"><b>Mahesh Mohan Thorat</b></a></p>
								</div>
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
		if ($bytes >= 1073741824)
		{
			$bytes = number_format($bytes / 1073741824, 2) . ' GB';
		}
		elseif ($bytes >= 1048576)
		{
			$bytes = number_format($bytes / 1048576, 2) . ' MB';
		}
		elseif ($bytes >= 1024)
		{
			$bytes = number_format($bytes / 1024, 2) . ' KB';
		}
		elseif ($bytes > 1)
		{
			$bytes = $bytes . ' bytes';
		}
		elseif ($bytes == 1)
		{
			$bytes = $bytes . ' byte';
		}
		else
		{
			$bytes = '0 bytes';
		}

		return $bytes;
	}

	public function return_generate_loadTime_report()
	{
		$this->_getJSReady();
		$opts = get_option('_all-in-one_minifier');
		$disablePluginOption = '';
		$frontpage_id = get_option( 'page_on_front' );
		if (!isset($opts['start_minify']) || ($opts['start_minify']) == 'off')
		{
			$disablePluginOption = 'alone-disabled-options';
		}
		if($frontpage_id == '' || $frontpage_id == '0')
		{
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
							
							if(@$_GET['filter-post-type'] == '' || @$_GET['filter-post-type'] == 'all')
							{
								$args = array(
									'public' => true,
								);
								$post_types = get_post_types( $args, 'objects' );
								foreach ( $post_types as $post_type_obj ):
									if($post_type_obj->name != 'attachment')
									{
										array_push($allPostTypes, $post_type_obj->name);
									}
								endforeach;
							}
							else
							{
								array_push($allPostTypes, $_GET['filter-post-type']);
							}
							$paged = @$_GET['paged'];
							set_query_var( 'paged', $paged );
							$paged = ( get_query_var( 'paged' ) ) ? get_query_var( 'paged' ) : 1;
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
							if($frontpage_id == '' || $frontpage_id == '0')
							{
								?>
								<div class="tab-section">
									<div class="wpbnd-notice warning">
										<span><?php echo _e('You have not set front page you need to set it first !', AOMIN_PLUGIN_IDENTIFIER); ?> <a href="./options-reading.php" target="_blank">click here</a></span>
									</div>
								</div>
								<?php
							}
							else
							{
								if($disablePluginOption != '')
								{
									?>
									<div class="tab-section">
										<div class="wpbnd-notice warning">
											<span><?php echo _e('You have not set up your minifier options !', AOMIN_PLUGIN_IDENTIFIER); ?></span>
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
												<a><span>Page</span></a>
											</th>
											<th scope="col" class="manage-column column-date sortable asc"><a><span>Page Before Optimization</span></a></th>
											<th scope="col" class="manage-column column-date sortable asc"><a><span>Page After Optimization</span></a></th>
											<th scope="col" class="manage-column column-date sortable asc"><a><span>Minified %</span></a></th>
											<th scope="col" class="manage-column column-date sortable asc"><a><span>Date</span></a></th>
										</tr>
									</thead>
									<tbody id="the-list">
										<?php
										while ( $wpposts->have_posts() ) : $wpposts->the_post();
											$postID = get_the_ID();
											$post_title = get_the_title();
											$post_link = get_permalink( $postID );
											$current_queried_post_type = get_post_type( $postID );

											$docBeforeTimePrint = ''; $docAfterTimePrint = '';
											$bef_docBeforeTime = ''; $bef_docAfterTime = ''; $af_docBeforeTime = ''; $af_docAfterTime = '';

											global $wpdb;
											$table_name = $wpdb->prefix . 'alone_minifier_analysis';
											$querystr_1 = "SELECT postID, docBeforeTime, docAfterTime, minifyStatus, datetime FROM $table_name WHERE postID = $postID ORDER BY minifyStatus ";
											$pageposts_1 = $wpdb->get_results($querystr_1, OBJECT);
											$array_1 = array();
											foreach ($pageposts_1 as $value_1) {
												$docBeforeTime = $value_1->docBeforeTime;
												$docAfterTime = $value_1->docAfterTime;
												$minifyStatus = $value_1->minifyStatus;
												$datetime = $value_1->datetime;
												
												$af_docBeforeTime = $docBeforeTime;
												$af_docAfterTime = $docAfterTime;

												$docBeforeTimePrint.= '<span>Before</span> : <span class="right"><b>'.esc_attr($this->AIOMformatSizeUnits($docBeforeTime)).'</b></span>';
												$docAfterTimePrint.= '<span>After</span> : <span class="right"><b>'.esc_attr($this->AIOMformatSizeUnits($docAfterTime)).'</b></span>';
												
											}
											$minifiedPerComp = ''; $minifiedPerPrint = '';
											if($af_docAfterTime != '' && $af_docBeforeTime != '')
											{
												$minifiedPerComp = ($af_docAfterTime / $af_docBeforeTime) * 100;
												$minifiedPerComp = $minifiedPerComp - 100;
												$minifiedPerComp = abs($minifiedPerComp);
												$minifiedPerReadyClass = 'color_red'; $minifiedPerCompClass = 'color_red';
												
												if($af_docBeforeTime > $af_docafterTime)
												{
													$minifiedPerCompClass = 'color_green';
												}
												$minifiedPerPrint.= '<span class="left"><span class="right '.$minifiedPerCompClass.'"><b>'.number_format($minifiedPerComp,2).'</b> %</span>';
											}
											?>
											<tr id="post-<?php echo esc_attr($postID); ?>" class="iedit author-self level-0 post-<?php echo esc_attr($postID); ?> type-page status-publish hentry">
												<td class="title column-title has-row-actions column-primary page-title" data-colname="Page">
													<strong><a <?php if($disablePluginOption == '') {?> href="javascript:_buildCache(<?php echo esc_attr($postID); ?>, -1)" <?php } ?> class="row-title" ><?php echo esc_attr($post_title); ?> <span class="dashicons dashicons-external"></span></a> — <span class="post-state"><?php echo esc_attr($current_queried_post_type); ?></span></strong>
													<div class="row-actions"></div>
													<button type="button" class="toggle-row"><span class="screen-reader-text">Show more details</span></button>
												</td>
												<?php
												if($docBeforeTimePrint != '')
												{
													?>
													<td data-colname="Page Ready Time"><?php echo wp_kses_post($docBeforeTimePrint); ?></td>
													<td data-colname="Page Completely Loaded"><?php echo wp_kses_post($docAfterTimePrint); ?></td>
													<td data-colname="Minified %"><?php echo wp_kses_post($minifiedPerPrint); ?></td>
													<td class="date column-date" data-colname="Date"><small>Last cache on</small><br><?php echo esc_attr($datetime); ?></td>
													<?php
												}
												else
												{
													?>
													<td colspan="4" data-colname="Generate Report First">
														<small>Build cache for this <span class="post-state"><?php echo esc_attr($current_queried_post_type); ?></span></small><br>
														<a <?php if($disablePluginOption == '') {?> href="javascript:_buildCache(<?php echo esc_attr($postID); ?>, -1)" <?php } ?> class="row-title" >Click here <span class="dashicons dashicons-external"></span></a>
													</td>
													<?php
												}
												?>
											</tr>
											<?php
										endwhile; 
										wp_reset_query();
										if(!$wpposts->have_posts())
										{
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
					$post_types = get_post_types( $args, 'objects' );
					foreach ( $post_types as $post_type_obj ):
						$labels = get_post_type_labels( $post_type_obj );
						$selectOption = '';
						if(@$_GET['filter-post-type'] == $post_type_obj->name)
						{
							$selectOption = 'selected';
						}
						if($post_type_obj->name != 'attachment')
						{
							?>
							<option value="<?php echo esc_attr( $post_type_obj->name ); ?>" <?php echo esc_attr($selectOption) ?>><?php echo esc_html( $labels->name ); ?></option>
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
				$pageUrl = str_replace( $big, '%#%', get_pagenum_link( $big ) );
				$pageUrl = str_replace('#038;', '&', $pageUrl);
				echo wp_kses_post(paginate_links( array(
					// 'base' => str_replace( $big, '%#%', get_pagenum_link( $big ) ),
					'base' => $pageUrl,
					'format' => '?paged=%#%',
					'current' => max( 1, get_query_var('paged') ),
					'total' => $wpposts->max_num_pages
				) ));
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
											<td><a href="https://rzp.io/l/maheshmthorat" target="_blank"><img width="160" src="<?php echo esc_url(plugin_dir_url(dirname( __FILE__ ))); ?>admin/assets/img/razorpay.svg" /></a></td>
										</tr>
										<tr>
											<td>
												<h3>Scan below code</h3>
												<img width="350" src="<?php echo esc_url(plugin_dir_url(dirname( __FILE__ ))); ?>admin/assets/img/qr.svg" />
												<br>
												<img width="350" src="<?php echo esc_url(plugin_dir_url(dirname( __FILE__ ))); ?>admin/assets/img/upi.png" />
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

	public function call_action_console_loadTime()
	{
		?>
		<script type="text/javascript">
			'use strict';
			var beforeload = (new Date()).getTime();
			function getPageLoadTime(condtion)
			{
				var afterload = (new Date()).getTime();
				var seconds = null;

				seconds = (afterload - beforeload) / 1000;
				console.log('Page '+condtion+' in ' + seconds + ' sec(s).');
			}
			document.addEventListener('readystatechange', event => { 
				if (event.target.readyState === "interactive") {
					getPageLoadTime('ready');
				}
				if (event.target.readyState === "complete") {
					getPageLoadTime('completely loaded');
				}
			});
		</script>
		<?php
	}

	public function call_action_generate_loadTime_report()
	{
		if(get_the_ID() > 0)
		{
			?>
			<script type="text/javascript">
				'use strict';
				var url_string = location.href;
				var url = new URL(url_string);
				var is_minify = url.searchParams.get("is_minify");
				var is_report = url.searchParams.get("is_report");

				var minifyStatus = 0; 
				if(is_minify == 1)
				{
					minifyStatus = 1;
				}

				var beforeload = (new Date()).getTime(); var intedocBeforeTime; var docAfterTime; 
				function retPageLoadTime(condtion)
				{
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
						if(is_report == null)
						{
							_loadAjx('complete')
						}
					}
				});
				function _loadAjx(condition) {
					let httpc = new XMLHttpRequest();
					let url = "<?php echo esc_url(plugins_url(AOMIN_PLUGIN_IDENTIFIER)); ?>/admin/admin-ajax.php";
					httpc.open("POST", url, true);
					httpc.onreadystatechange = function() {
						if(httpc.readyState == 4 && httpc.status == 200) {
							if(is_minify == null)
							{
								location.href = location.href + "&is_minify=1"
							}
							else if(is_minify == 1 && is_report == null)
							{
								location.href = location.href + "&is_report=true"
							}
						}
					};
					var data = JSON.stringify({
						'condition':condition,
						'id':<?php echo get_the_ID(); ?>,
						'minifyStatus':minifyStatus,
						'intedocBeforeTime':intedocBeforeTime,
						'docAfterTime':docAfterTime,
					});
					httpc.send(data);
				}
			</script>
			<?php
		}
	}

	public function call_action_generate_report_css()
	{
		if(@$_GET['generatereport'] == 'true' && !isset($_GET['is_report']) && (@$_GET['is_minify'] == '' || @$_GET['is_minify'] == '1'))
		{
			?>
			<div class="popPageReport">
				<div class="popPageReport-content">
					<h4>Please wait while we generating report for this page.</h4>
					<p>
						This will take less than a minute.<br>
						<small><b>Note - Page will reload several times to generate report</b></small>
					</p>
					<?php if($_GET['is_minify'] == '') { ?>
						<h5 class="color_red">Generating without <span>web optimizations</span></h5>
					<?php } if($_GET['is_minify'] == '1') { ?>
						<h5 class="color_green">Generating with <span>web optimizations</span></h5>
					<?php } ?>
					<a class="btnCancel" href="<?php echo esc_url(get_permalink(get_the_ID())); ?>">Cancel Report</a>
				</div>
			</div>
			<?php
		}
		else if(@$_GET['is_report'] == 'true')
		{
			$postID = get_the_ID();
			global $wpdb;
			$table_name = $wpdb->prefix . 'alone_minifier_analysis';
			$querystr_1 = "SELECT postID, docBeforeTime, docAfterTime, minifyStatus, datetime FROM $table_name WHERE postID = $postID ORDER BY minifyStatus ";
			$pageposts_1 = $wpdb->get_results($querystr_1, OBJECT);
			$array_1 = array();
			foreach ($pageposts_1 as $value_1) {
				$docBeforeTime = $value_1->docBeforeTime;
				$docAfterTime = $value_1->docAfterTime;
				$minifyStatus = $value_1->minifyStatus;
				$datetime = $value_1->datetime;
				if($minifyStatus == 0)
				{
					$bef_docBeforeTime = $docBeforeTime;
					$bef_docAfterTime = $docAfterTime;
					
					$docBeforeTimePrint.= '<span>Before</span> : <span class="right"><b>'.$docBeforeTime.'</b> Sec</span><br>';
					$docAfterTime.= '<span>Before</span> : <span class="right"><b>'.$docAfterTime.'</b> Sec</span><br>';
				}
				if($minifyStatus == 1)
				{
					$af_docBeforeTime = $docBeforeTime;
					$af_docAfterTime = $docAfterTime;
					
					$docBeforeTimePrint.= '<span>After</span> : <span class="right"><b>'.$docBeforeTime.'</b> Sec</span>';
					$docAfterTime.= '<span>After</span> : <span class="right"><b>'.$docAfterTime.'</b> Sec</span>';
				}
			}
			$minifiedPerReady = ''; $minifiedPerComp = ''; $minifiedPerPrint = '';
			if($bef_docBeforeTime != '' && $bef_docAfterTime != '' && $af_docBeforeTime != '' && $af_docAfterTime != '')
			{
				$minifiedPerReady = ($bef_docBeforeTime / $af_docBeforeTime) * 100;
				$minifiedPerComp = ($bef_docAfterTime / $af_docAfterTime) * 100;
				$minifiedPerReadyClass = 'color_red'; $minifiedPerCompClass = 'color_red';
				if($bef_docBeforeTime > $af_docBeforeTime)
				{
					$minifiedPerReadyClass = 'color_green';
				}
				if($bef_docAfterTime > $af_docAfterTime)
				{
					$minifiedPerCompClass = 'color_green';
				}
				$minifiedPerPrint = '<span class="left">Page Ready</span> : <span class="right '.$minifiedPerReadyClass.'"><b>'.number_format($minifiedPerReady,2).'</b> %</span>';
				$minifiedPerPrint.= '<br><span class="left">Page Complete</span> : <span class="right '.$minifiedPerCompClass.'"><b>'.number_format($minifiedPerComp,2).'</b> %</span>';
			}
			$backBtnLink = admin_url('admin.php').'?page=all-in-one-minifier-report';
			?>
			<div class="popPageReport">
				<div class="popPageReport-content">
					<h4>
						<span class="dashicons dashicons-chart-line"></span> Page Speed Report
					</h4>
					<div class="reportBlock">
						Page Ready Time<br>
						<?php echo @$docBeforeTimePrint; ?>
					</div>
					<div class="reportBlock">
						Page Completely Loaded
						<?php echo @$docAfterTime; ?>
					</div>
					<div class="reportBlock">
						Minified %<br>
						<?php echo $minifiedPerPrint; ?>
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
			.color_red{
				color: #eb1e2e;
				font-weight: bolder;
			}
			.color_green{
				color: #54b058;
				font-weight: bolder;
			}
			.reportBlock{
				padding: 10px;
				background-color: #efefef;
				margin-bottom: 10px;
			}
			.btnCancel, .btnClose, .btnBack, .btnRescan{
				color: #fff;
				padding: 8px 12px;
				border-radius: 4px;
				transition: 0.2s ease;
				margin-top: 20px;
				display: inline-block;
				margin-right: 10px;
			}
			.btnCancel{
				background-color: #eb1e2f;
			}
			.btnClose{
				background-color: #eb1e2f;
			}
			.btnBack{
				background-color: #0b5f9f;
			}
			.btnRescan{
				background-color: #86c233;
			}
			.btnCancel:hover, .btnClose:hover, .btnBack:hover, .btnRescan:hover{
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
				background-color: rgb(0,0,0);
				background-color: rgba(0,0,0,0.4);
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
		$url = get_admin_url().'admin.php?page=all-in-one-minifier-admin';
		$settings_link = "<a href='$url'>" . __( 'Settings' ) . '</a>';
		array_push(
			$links,
			$settings_link
		);
		return $links;
	}
}

add_action( 'wp_ajax_actionBuildCache', 'buildCacheAjax' );
add_action( 'wp_ajax_actionBuildCache', 'buildCacheAjax' );
function buildCacheAjax() {
	if(isset($_POST['post_id']))
	{
		$post_id = @$_POST['post_id'];
	}
	if($post_id != '')
	{
		$post_slug = get_post_field( 'post_name', $post_id );
		$permalink = get_permalink($post_id);
		$frontpage_id = get_option( 'page_on_front' );
		if($post_id == $frontpage_id)
		{
			$post_slug = 'index';
		}

		$arrContextOptions=array(
			"ssl"=>array(
				"verify_peer"=>false,
				"verify_peer_name"=>false,
			),
		);  
		if(file_exists(AOMIN_PLUGIN_ABS_CACHE_PATH.$post_slug.".html"))
		{
			unlink(AOMIN_PLUGIN_ABS_CACHE_PATH.$post_slug.".html");
		}
		$content = file_get_contents($permalink."?isMinify=false", false, stream_context_create($arrContextOptions));
		$withoutSanitize = file_put_contents(AOMIN_PLUGIN_ABS_CACHE_PATH.$post_slug.".html",$content);
		unlink(AOMIN_PLUGIN_ABS_CACHE_PATH.$post_slug.".html");
		$withSanitize = file_put_contents(AOMIN_PLUGIN_ABS_CACHE_PATH.$post_slug.".html",sanitize_output($content));

		global $wpdb;
		$datetime = date('Y-m-d H:i:s');
		$minifyStatus = 1;
		$table_name = $wpdb->prefix . 'alone_minifier_analysis';
		$querystr = "SELECT postID FROM $table_name WHERE postID = $post_id LIMIT 1 ";
		$pageposts = $wpdb->get_results($querystr, OBJECT);
		if(count($pageposts) > 0)
		{
			$wpdb->query($wpdb->prepare("UPDATE $table_name SET docBeforeTime = '$withoutSanitize', docAfterTime = '$withSanitize', datetime = '$datetime' WHERE postID = $post_id AND minifyStatus = $minifyStatus "));
		}
		else
		{
			$wpdb->insert($table_name, array('postID' => $post_id, 'minifyStatus' => $minifyStatus, 'docBeforeTime' => $withoutSanitize, 'docAfterTime' => $withSanitize, 'datetime' => $datetime));
		}
	}
}