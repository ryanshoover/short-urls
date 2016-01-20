<?php

class WPEURLPrimary {

    protected $options_slug = 'wpe_url_shortener';
    protected $plugin_options = array();
    protected $url_pattern;

	public static function get_instance() {

        static $instance = null;

        if ( null === $instance )
			$instance = new static();

        return $instance;
    }

	private function __clone(){}

    private function __wakeup(){}

	protected function __construct() {
        $this->plugin_options = get_option( $this->options_slug, array() );
        $this->url_pattern    = '#\b(([\w-]+://?|www[.])[^\s()<>]+(?:\([\w\d]+\)|([^[:punct:]\s]|/)))#iS';

        add_action('init', array( $this, 'create_link_cpt' ) );

        add_action( 'wp', array( $this, 'maybe_redirect_to_url' ) );

        add_filter( 'post_type_link', array( $this, 'remove_cpt_slug' ), 10, 2 );

        add_action( 'pre_get_posts', array( $this, 'parse_request_tricksy' ) );
	}

    /**
     * Register a link post type.
     *
     * @since 0.1.0
     * @link http://codex.wordpress.org/Function_Reference/register_post_type
     */
    function create_link_cpt() {

        $labels = array(
            'name'               => _x( 'Links', 'post type general name', 'wpeurl' ),
            'singular_name'      => _x( 'Link', 'post type singular name', 'wpeurl' ),
            'menu_name'          => _x( 'Links', 'admin menu', 'wpeurl' ),
            'name_admin_bar'     => _x( 'Link', 'add new on admin bar', 'wpeurl' ),
            'add_new'            => _x( 'Add New', 'book', 'wpeurl' ),
            'add_new_item'       => __( 'Add New Link', 'wpeurl' ),
            'new_item'           => __( 'New Link', 'wpeurl' ),
            'edit_item'          => __( 'Edit Link', 'wpeurl' ),
            'view_item'          => __( 'View Link', 'wpeurl' ),
            'all_items'          => __( 'All Links', 'wpeurl' ),
            'search_items'       => __( 'Search Links', 'wpeurl' ),
            'parent_item_colon'  => __( 'Parent Links:', 'wpeurl' ),
            'not_found'          => __( 'No links found.', 'wpeurl' ),
            'not_found_in_trash' => __( 'No links found in Trash.', 'wpeurl' )
        );

        $args = array(
            'labels'             => $labels,
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => true,
            'rewrite'            => array( 'slug' => 'link', 'with_front' => true ),
            'capability_type'    => 'post',
            'has_archive'        => true,
            'hierarchical'       => true,
            'menu_position'      => 2,
            'menu_icon'          => 'dashicons-migrate',
            'supports'           => array( 'title', 'author' )
        );

        register_post_type( 'link', $args );
        flush_rewrite_rules();
    }

    /**
     * Maybe redirect to a URL if we have a match
     *
     * Sees if we have a valid URL in postmeta. If so, off we go!
     *
     * @since 0.1.0
     */
    public function maybe_redirect_to_url() {

        if( is_admin() ) {
            return false;
        }

        $meta = get_post_meta( get_the_ID() );

        $utms = array();

        foreach( $meta as $key => $value ) {
            if( false !== strpos( $key, 'wpeurl_link_utm' ) ) {
                $utms[ str_replace( 'wpeurl_link_', '', $key ) ] = $value[0];
            }
        }

        $spacer = empty( strpos( $meta['wpeurl_link_redirect_url'][0], '?' ) ) ? '?' : '&';

        $redirect = $meta['wpeurl_link_redirect_url'][0] . $spacer . http_build_query( $utms );

        if ( preg_match( $this->url_pattern, $redirect ) ) {
            $this->track_post_view();
            wp_redirect( $redirect );
            exit;
        }

        // If we didn't redirect to a shortened link, see if we should redirect to default
        $this->maybe_redirect_to_default();
    }

    /**
     * Redirects the front page and 404 pages to the provided URL
     *
     * If we should be redirecting these pages, we'll send the user to the provided URL in plugin options
     */
    public function maybe_redirect_to_default() {
        // Abort if we don't have a URL to redirect to
        if ( ! isset( $this->plugin_options['wpe_redirect_url'] ) || empty( $this->plugin_options['wpe_redirect_url'] ) ) {
            return false;
        }

        if ( preg_match( $this->url_pattern, $this->plugin_options['wpe_redirect_url'] ) ) {
            wp_redirect( $this->plugin_options['wpe_redirect_url'] );
            exit;
        }
    }

    /**
     * Log a visit to the post
     *
     * Logs the visit in the post meta values
     */
    public function track_post_view() {

        // No need to track view if I'm logged in
        if( is_user_logged_in() ) {
            return false;
        }

        $views = get_post_meta( get_the_ID(), 'wpe_view_data', true );

        $referer = isset( $_SERVER['HTTP_REFERER'] ) ? parse_url( $_SERVER['HTTP_REFERER'], PHP_URL_HOST ) : false ;

        $refers = !empty( $referer ) ? $referer : 'not-set';
        $source = isset( $_GET['utm_source'] ) ? $_GET['utm_source'] : 'not-set';
        $medium = isset( $_GET['utm_medium'] ) ? $_GET['utm_medium'] : 'not-set';
        $uagent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : 'not-set';
        $date = date( 'Y-m-d' );

        if( empty( $views ) ) {
            $view = array(
                'total'  => 0,
                'days'   => array(),
                'refers' => array(),
                'source' => array(),
                'medium' => array(),
                'agents' => array(),
                );
        }

        $views['total']++;
        $views['days'][ $date ]     = isset( $views['days'][ $date ] )     ? $views['days'][ $date ] + 1     : 1;
        $views['refers'][ $refers ] = isset( $views['refers'][ $refers ] ) ? $views['refers'][ $refers ] + 1 : 1;
        $views['agents'][ $uagent ] = isset( $views['agents'][ $uagent ] ) ? $views['agents'][ $uagent ] + 1 : 1;

        update_post_meta( get_the_ID(), 'wpe_view_data', $views );
    }

    /**
     * Remove the slug from published post permalinks. Only affect our CPT though.
     *
     * @since 0.1.0
     */
    public function remove_cpt_slug( $post_link, $post ) {

        if ( ! in_array( $post->post_type, array( 'link' ) ) || 'publish' != $post->post_status )
            return $post_link;

        $post_link = str_replace( '/' . $post->post_type . '/', '/', $post_link );

        return $post_link;
    }

    /**
     * Add our CPT to the queries without a slug
     *
     * @since 0.1.0
     */
    public function parse_request_tricksy( $query ) {

        // Only loop the main query
        if ( ! $query->is_main_query() )
            return;

        // Only loop our very specific rewrite rule match
        if ( 2 != count( $query->query )
            || ! isset( $query->query['page'] ) )
            return;

        // 'name' will be set if post permalinks are just post_name, otherwise the page rule will match
        if ( ! empty( $query->query['name'] ) )
            $query->set( 'post_type', array( 'post', 'link', 'page' ) );
    }
}

WPEURLPrimary::get_instance();
