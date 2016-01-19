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

define( 'WPEURL_PATH', plugin_dir_path( __FILE__ ) );
define( 'WPEURL_URL',  plugin_dir_url( __FILE__ ) );

require_once( WPEURL_PATH . 'lib/primary.php' );

if ( is_admin() )
	require_once( WPEURL_PATH . 'lib/admin.php' );
