<?php

class WPEURLAdmin extends WPEURLPrimary {

	private $url;

	public static function get_instance()
    {
        static $instance = null;

        if ( null === $instance ) {
            $instance = new static();
        }

        return $instance;
    }

    private function __clone(){
    }


    private function __wakeup(){
    }

	protected function __construct() {

		parent::get_instance();

        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_styles' ), 15 );
        add_action( 'manage_link_posts_custom_column', array( $this, 'do_posts_view_count_column' ), 10, 2 );
        add_action( 'add_meta_boxes_link',   array( $this, 'maybe_show_link_views' ) );
        add_action( 'cmb2_init',             array( $this, 'add_links_metaboxes' ) );

        add_action( 'cmb2_render_readonly', array( $this, 'render_callback_readonly'), 10, 5 );
        add_action( 'cmb2_render_post_slug',   array( $this, 'render_callback_post_slug' ), 10, 5 );
        add_action( 'save_post', array( $this, 'post_save_assign_post_slug'), 15 );

        add_filter( 'manage_link_posts_columns', array( $this, 'add_posts_view_count_column' ) );
        add_filter( 'cmb2_override_wpeurl_link_post_slug_meta_value', array( $this, 'filter_post_slug_value'), 10, 2 );
        add_filter( 'cmb2_override_wpeurl_link_redirect_url_meta_value', array( $this, 'filter_redirect_url_value'), 10, 2 );
        add_filter( 'cmb2_override_wpeurl_link_display_url_meta_value', array( $this, 'filter_display_url_value'), 10, 2 );
	}

    /**
     * Enqueue all our needed styles and scripts
     * @since 0.1.0
     */
    public function enqueue_admin_styles() {
        global $wpeurl_path, $wpeurl_url;
        wp_enqueue_style( 'cmb2-styles' );
        wp_enqueue_script( 'wpeurl_admin_script', $wpeurl_url . 'js/admin-scripts.js', array(), filemtime( $wpeurl_path . 'js/admin-scripts.js') );
    }

    /**
     * Show view count in all links table
     *
     * @since 0.3.0
     */
    public function add_posts_view_count_column( $defaults ) {
        $defaults['view_count'] = __('Total Views', 'wpeurl');

        return $defaults;
    }

    public function do_posts_view_count_column( $column_name, $post_ID ) {
        if( 'view_count' == $column_name ) {
            $views = get_post_meta( $post_ID, 'wpe_view_data', true );

            if( isset( $views['total'] ) ) {
                echo $views['total'];
            }
        }
    }

    /**
     * Create a meta box that shows view statistics
     *
     * @since 0.3.0
     */
    public function maybe_show_link_views() {
        global $post;

        $views = get_post_meta( $post->ID, 'wpe_view_data', true );

        if( ! empty( $views ) ) {
            add_meta_box( 'wpe_link_views', __('Analytics', 'wpeurl'), array( $this, 'create_link_views'), null, 'normal', 'low' );
        }
    }

    public function create_link_views( $post ) {
        $views = get_post_meta( $post->ID, 'wpe_view_data', true );

        $days = $views['days'];
        krsort( $days );
        $days_html = '';

        $days = array_slice( $days, 0, 7 );

        foreach ( $days as $day=>$count ) {
            $days_html .= "<tr><td>$day</td><td>$count</td></tr>\n";
        }

        $referers = $views['refers'];
        arsort( $referers );

        $referers_html = '';
        foreach ( $referers as $referer=>$count ) {
            $referers_html .= "<tr><td>$referer</td><td>$count</td></tr>\n";
        }

        echo <<<HTML
            <p>Total hits: <strong>{$views['total']}</strong></p>

            <h3>Last 7 Days</h3>
            <table class="widefat fixed striped">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Total</th>
                    </tr>
                </thead>
                {$days_html}
            </table>

            <br>

            <h3>Referring Site</h3>
            <table class="widefat fixed striped">
                <thead>
                    <tr>
                        <th>Site</th>
                        <th>Total</th>
                    </tr>
                </thead>
                {$referers_html}
            </table>
HTML;
    }

    /**
     * Create links post type metaboxes
     *
     * Create the options for the links post type
     *
     * @since 0.1.0
     */
    public function add_links_metaboxes() {
        $prefix = 'wpeurl_link_';

        $utm_description = __( '<p>Enter your UTM values below to build an analytics-friendly URL.</p><p>All values are optional.</p>', 'wpeurl' );

        $cmb = new_cmb2_box( array(
            'id'      => $prefix . 'analytics',
            'title'   => __( 'Custom Link Settings', 'wpeurl' ),
            'object_types'   => array( 'link', 'post', 'page' ),
        ) );

        if( isset( $_GET['action'] ) && 'edit' == $_GET['action'] ) {
            $cmb->add_field( array(
                'name' => __( 'Your Shortened URL', 'wpeurl' ),
                'value' => get_the_permalink( $_GET['post'] ),
                'id'   => $prefix . 'display_url',
                'type' => 'readonly',
                ) );
        }

        $cmb->add_field( array(
            'name' => __( 'Link Text<sup>*</sup>', 'wpeurl' ),
            'desc' => __( 'What is the part of the link that goes after ' . get_site_url() . '/', 'wpeurl' ),
            'id'   => $prefix . 'post_slug',
            'type' => 'post_slug',
            'value' => 'slug',
        ) );

        $cmb->add_field( array(
            'name' => __( 'Destination URL<sup>*</sup>', 'wpeurl' ),
            'desc' => __( 'Where is the final destination of this link?', 'wpeurl' ),
            'id'   => $prefix . 'redirect_url',
            'type' => 'text_url',
            'value' => 'http://wpengine.com/',
        ) );

        $cmb->add_field( array(
            'name' => __( 'URL Builder', 'wpeurl' ),
            'desc' => $utm_description,
            'id'   => $prefix . 'title',
            'type' => 'title',
            ) );

        $cmb->add_field( array(
            'name' => __( 'Campaign Source', 'wpeurl' ),
            'id'   => $prefix . 'utm_source',
            'type' => 'text_small',
            ) );

        $cmb->add_field( array(
            'name' => __( 'Campaign Medium', 'wpeurl' ),
            'id'   => $prefix . 'utm_medium',
            'type' => 'text_small',
            ) );

        $cmb->add_field( array(
            'name' => __( 'Campaign Term', 'wpeurl' ),
            'id'   => $prefix . 'utm_term',
            'type' => 'text_small',
            ) );

        $cmb->add_field( array(
            'name' => __( 'Campaign Content', 'wpeurl' ),
            'id'   => $prefix . 'utm_content',
            'type' => 'text_small',
            ) );

        $cmb->add_field( array(
            'name' => __( 'Campaign Name', 'wpeurl' ),
            'id'   => $prefix . 'utm_campaign',
            'type' => 'text_small',
            ) );
    }

    public function render_callback_post_slug( $field, $escaped_value, $object_id, $object_type, $field_type_object ) {
        echo $field_type_object->input( array(
            'type' => 'post_slug',
            'class' => 'cmb2-text',
            ) );
    }

    public function render_callback_readonly( $field, $escaped_value, $object_id, $object_type, $field_type_object ) {
        $a = array(
            'type'  => 'text',
            'class' => 'regular-text',
            'name'  => $field_type_object->_name(),
            'id'    => $field_type_object->_id(),
            'value' => $field_type_object->field->escaped_value(),
            'desc'  => $field_type_object->_desc( true ),
            );

        $copy_button = '<a href="#" class="button" id="btn-copy-url">Copy URL</a>';

        $url_pattern = '#\b(([\w-]+://?|www[.])[^\s()<>]+(?:\([\w\d]+\)|([^[:punct:]\s]|/)))#iS';

        $meta = get_post_meta( get_the_ID() );

        $utms = array();

        foreach( $meta as $key => $value ) {
            if( false !== strpos( $key, 'wpeurl_link_utm' ) ) {
                $utms[ str_replace( 'wpeurl_link_', '', $key ) ] = $value[0];
            }
        }

        $spacer = empty( strpos( $meta['wpeurl_link_redirect_url'][0], '?' ) ) ? '?' : '&';

        $redirect = $meta['wpeurl_link_redirect_url'][0] . $spacer . http_build_query( $utms );

        $description = preg_match( $url_pattern, $redirect ) ? '<p class="cmb2-metabox-description">Your expanded URL is<br>' . $redirect . '</p>' : '';

        printf( '<input%s readonly>%s %s %s', $field_type_object->concat_attrs( $a, array( 'desc' ) ), $a['desc'], $copy_button, $description );
    }

    public function post_save_assign_post_slug( $post_id ) {
        // update the post slug

        // If this is a revision, get real post ID
        if ( $parent_id = wp_is_post_revision( $post_id ) )
            $post_id = $parent_id;

        remove_action( 'save_post', array( $this, 'post_save_assign_post_slug'), 15 );

        $value = get_post_meta( $post_id, 'wpeurl_link_post_slug', true );

        if( $value ) {
            wp_update_post( array(
                'ID'        => $post_id,
                'post_name' => $value,
                ) );
        }

        add_action( 'save_post', array( $this, 'post_save_assign_post_slug'), 15 );
    }

    public function filter_post_slug_value( $data, $object_id ) {

        $current_slug = get_post_meta( $object_id, 'wpeurl_link_post_slug', true );

        $data = $current_slug ? $data : substr(md5(microtime()),rand(0,26),6);

        return $data;
    }

    public function filter_redirect_url_value( $data, $object_id ) {

        $current_url = get_post_meta( $object_id, 'wpeurl_link_redirect_url', true );

        $data = $current_url ? $data : 'http://wpengine.com/';

        return $data;
    }

    public function filter_display_url_value( $data, $object_id ) {

        $data = isset( $_GET['action'] ) && 'edit' == $_GET['action'] ? get_the_permalink( $_GET['post'] ) : '';

        return $data;
    }
}
