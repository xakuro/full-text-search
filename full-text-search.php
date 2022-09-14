<?php
/*
Plugin Name: Full-Text Search
Plugin URI: https://xakuro.com/wordpress/
Description: Replaces site search with full-text search (Japanese support).
Author: Xakuro
Author URI: https://xakuro.com/
License: GPLv2
Requires at least: 4.9
Requires PHP: 7.1
Version: 2.9.1
Text Domain: full-text-search
Domain Path: /languages/
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'FULL_TEXT_SEARCH_VERSION', '2.9.1' );

require_once( __DIR__ . '/main.php' );

register_uninstall_hook( __FILE__, 'full_text_search::uninstall' );

global $full_text_search;
$full_text_search = new Full_Text_Search();
