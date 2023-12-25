<?php
/**
 * Full-Text Search
 *
 * @package full-text-search
 * @author  ishitaka
 * @license GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       Full-Text Search
 * Plugin URI:        https://xakuro.com/wordpress/
 * Description:       Replaces site search with full-text search.
 * Version:           2.14.0
 * Requires at least: 5.5
 * Requires PHP:      7.2
 * Author:            Xakuro
 * Author URI:        https://xakuro.com/
 * License:           GPL v2 or later
 * Text Domain:       full-text-search
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'FULL_TEXT_SEARCH_VERSION', '2.14.0' );

require_once __DIR__ . '/main.php';

register_uninstall_hook( __FILE__, 'full_text_search::uninstall' );

global $full_text_search;
$full_text_search = new Full_Text_Search();
