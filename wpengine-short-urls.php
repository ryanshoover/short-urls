<?php

/*
Plugin Name: WP Engine Short URLs
Plugin URI: http://wpengine.com
Description: Creates a URL shortening service
Version: 0.1
Author: WP Engine
Author URI: http://wpengine.com
Text Domain: wpeurl
Domain Path: /languages
*/

global $wpeurl_path;
global $wpeurl_url;
$wpeurl_path = plugin_dir_path( __FILE__ );
$wpeurl_url  = plugin_dir_url( __FILE__ );

require_once( $wpeurl_path . 'lib/primary.php' );
require_once( $wpeurl_path . 'lib/admin.php' );

if( class_exists( 'WPEURLPrimary' ) ) {
	$wpeurl_primary = WPEURLPrimary::get_instance();
}

if( class_exists( 'WPEURLAdmin' ) ) {
	$wpeurl_admin = WPEURLAdmin::get_instance();
}
