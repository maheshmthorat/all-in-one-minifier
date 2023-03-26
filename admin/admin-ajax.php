<?php

require_once '../../../../wp-load.php';
$post_id = $_GET['post_id'];
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