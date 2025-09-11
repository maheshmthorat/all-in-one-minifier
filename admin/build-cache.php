<?php

require_once '../../../../wp-load.php';

$json = file_get_contents("php://input");
$data = json_decode($json, true);

$post_id = 0;
if (isset($data['post_id'])) {
	$post_id = intval($data['post_id']);
}
if (isset($_GET['post_id'])) {
	$post_id = intval($_GET['post_id']);
}

if ($post_id > 0) {
	$post_slug    = get_post_field('post_name', $post_id);
	$permalink    = get_permalink($post_id);
	$frontpage_id = get_option('page_on_front');
	if ($post_id == $frontpage_id) {
		$post_slug = 'index';
	}

	$arrContextOptions = array(
		"ssl" => array(
			"verify_peer"      => false,
			"verify_peer_name" => false,
		),
	);

	if (file_exists(AOMIN_PLUGIN_ABS_CACHE_PATH . $post_slug . ".html")) {
		unlink(AOMIN_PLUGIN_ABS_CACHE_PATH . $post_slug . ".html");
	}

	$content        = file_get_contents($permalink, false, stream_context_create($arrContextOptions));
	$withoutSanitize = file_put_contents(AOMIN_PLUGIN_ABS_CACHE_PATH . $post_slug . ".html", $content);
	unlink(AOMIN_PLUGIN_ABS_CACHE_PATH . $post_slug . ".html");
	$withSanitize   = file_put_contents(AOMIN_PLUGIN_ABS_CACHE_PATH . $post_slug . ".html", sanitize_output($content));

	global $wpdb;
	$datetime    = date('Y-m-d H:i:s');
	$minifyStatus = 1;
	$table_name  = $wpdb->prefix . 'alone_minifier_analysis';

	$querystr = $wpdb->prepare("SELECT postID FROM $table_name WHERE postID = %d LIMIT 1", $post_id);
	$pageposts = $wpdb->get_results($querystr, OBJECT);

	if (count($pageposts) > 0) {
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE $table_name 
                 SET docBeforeTime = %s, docAfterTime = %s, datetime = %s 
                 WHERE postID = %d AND minifyStatus = %d",
				$withoutSanitize,
				$withSanitize,
				$datetime,
				$post_id,
				$minifyStatus
			)
		);
	} else {
		$wpdb->insert(
			$table_name,
			array(
				'postID'        => $post_id,
				'minifyStatus'  => $minifyStatus,
				'docBeforeTime' => $withoutSanitize,
				'docAfterTime'  => $withSanitize,
				'datetime'      => $datetime
			),
			array('%d', '%d', '%s', '%s', '%s')
		);
	}
} else {
	if ($_SERVER['REQUEST_METHOD'] == 'GET' && realpath(__FILE__) == realpath($_SERVER['SCRIPT_FILENAME'])) {
		header('HTTP/1.0 403 Forbidden', TRUE, 403);
		die(header('location: /404'));
	}
}
