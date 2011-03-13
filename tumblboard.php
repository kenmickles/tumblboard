<?php

// ini_set('display_errors', 1);
// error_reporting(E_ALL);

// only post this many links. anything after this number will be logged without posting
// this is useful for doing an initial import
$post_limit = 100;

// convert the config data to constants
$config = parse_ini_file('config.ini');
foreach ( $config as $key => $val ) {
	define(strtoupper($key), $val);
}

// log file for processed links
$processed_file = realpath(dirname(__FILE__)).'/processed.txt';

// fetch pinboard feed
$pinboard_feed = 'http://feeds.pinboard.in/json/v1/u:'.PINBOARD_USER.'/';
$data = json_decode(file_get_contents($pinboard_feed), 1);

// if we can't get anything from pinboard, we're done
if ( empty($data) ) {
	exit;
}

// get processed link list
$processed_links = array();

$lines = file($processed_file);
foreach ( $lines as $line ) {
	if ( preg_match('/^([0-9]*)\|(.*)/u', trim($line), $matches) ) {
		$processed_links[$matches[1]] = $matches[2];
	}
}

// process pinboard data
foreach ( $data as $i => $item ) {
	if ( !in_array($item['u'], $processed_links) ) {
		
		// we haven't reached out post limit yet
		if ( ($i+1) <= $post_limit ) {
			
			// see http://www.tumblr.com/docs/en/api#api_write for details
			$url = 'http://www.tumblr.com/api/write';

			$post_data = array(
				'email' => TUMBLR_EMAIL,
				'password' => TUMBLR_PASSWORD,
				'group' => TUMBLR_BLOG,
				'type' => 'link',
				'url' => $item['u'],
				'title' => $item['d'],
				'name' => $item['d'],
				'description' => $item['n'],
				'generator' => "Tumblboard",
				'date' => $item['dt'],
			);

			// make the request
			$ch = curl_init($url);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);	
			curl_setopt($ch, CURLOPT_HEADER, 0);	
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
			$response = curl_exec($ch);
			curl_close($ch);
		}
		// we've over the post limit. create a dummy post id for the log
		else {
			$response = '0000'.time().rand();
		}
		
		// if we got a post_id back, it worked. add the url to the list
		if ( is_numeric($response) ) {
			$processed_links[$response] = $item['u'];			
		}
		else {
			echo "Tumblr API error: {$response}\n";
		}
	}
}

// write processed links to file
$log_lines = array();
foreach ( $processed_links as $post_id => $url ) {
	$log_lines[] = "{$post_id}|{$url}";
}

file_put_contents($processed_file, implode("\n", $log_lines));

