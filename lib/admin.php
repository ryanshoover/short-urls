<?php

class WPEURLAdmin extends WPEURLPrimary {

	private $url;
    protected $options_page = '';
    protected $title = '';

	public static function get_instance() {
        static $instance = null;

        if ( null === $instance ) {
            $instance = new static();
        }

        return $instance;
    }

    private function __clone(){}


    private function __wakeup(){}

	protected function __construct() {

		parent::__construct();

        $this->title = __( 'Link Shortener', 'wpeurl' );

        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_styles' ), 15 );

        // Custom management of the links post type
        add_filter( 'manage_link_posts_columns', array( $this, 'add_posts_view_count_column' ) );
        add_action( 'save_post', array( $this, 'post_save_assign_post_slug'), 15 );
        add_action( 'add_meta_boxes_link',   array( $this, 'maybe_show_link_views' ) );
        add_action( 'manage_link_posts_custom_column', array( $this, 'do_posts_view_count_column' ), 10, 2 );

        // Create the link metabox
        add_action( 'cmb2_admin_init', array( $this, 'add_links_metaboxes' ) );

        add_action( 'cmb2_render_readonly', array( $this, 'render_callback_readonly'), 10, 5 );
        add_action( 'cmb2_render_post_slug',   array( $this, 'render_callback_post_slug' ), 10, 5 );

        // Filter saving the CMB2 meta values
        add_filter( 'cmb2_override_wpeurl_link_post_slug_meta_value', array( $this, 'filter_post_slug_value'), 10, 2 );
        add_filter( 'cmb2_override_wpeurl_link_redirect_url_meta_value', array( $this, 'filter_redirect_url_value'), 10, 2 );
        add_filter( 'cmb2_override_wpeurl_link_display_url_meta_value', array( $this, 'filter_display_url_value'), 10, 2 );

        // Create the options page
        add_action( 'admin_init', array( $this, 'init' ) );
        add_action( 'admin_menu', array( $this, 'add_options_page' ) );
        add_action( 'cmb2_init',  array( $this, 'add_options_metaboxes' ) );

        // Customize the WordPress dashboard
        add_action( 'admin_menu', array( $this, 'maybe_remove_menus' ), 99 );
	}

    /**
     * Enqueue all our needed styles and scripts
     * @since 0.1.0
     */
    public function enqueue_admin_styles() {
        wp_enqueue_style( 'cmb2-styles' );
        wp_enqueue_style( 'wpeurl_admin_styles', WPEURL_URL . 'css/admin-styles.css' );
        wp_enqueue_script( 'wpeurl_admin_script', WPEURL_URL . 'js/admin-scripts.js' );
    }

    /**
     * Show view count in all links table
     *
     * @since 0.3.0
     */
    public function add_posts_view_count_column( $defaults ) {
        $defaults['view_count'] = __('Total Clicks', 'wpeurl');

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

    /**
     * Renders the metabox that shows the latest link views
     * @param  object $post The post object that is currently being edited
     */
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

        $utm_description = __( '<p>Enter your UTM values below to build an analytics-friendly URL.</p><p>* All values are optional.</p>', 'wpeurl' );

        $default_url = isset( $this->plugin_options['wpe_redirect_url'] ) ? $this->plugin_options['wpe_redirect_url'] : 'https://wpengine.com';

        $cmb = new_cmb2_box( array(
            'id'      => $prefix . 'analytics',
            'title'   => __( 'Link Settings', 'wpeurl' ),
            'object_types'   => array( 'link' ),
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
            'value' => $default_url,
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
            'row_classes' => 'inline',
            ) );

        $cmb->add_field( array(
            'name' => __( 'Campaign Medium', 'wpeurl' ),
            'id'   => $prefix . 'utm_medium',
            'type' => 'text_small',
            'row_classes' => 'inline',
            ) );

        $cmb->add_field( array(
            'name' => __( 'Campaign Term', 'wpeurl' ),
            'id'   => $prefix . 'utm_term',
            'type' => 'text_small',
            'row_classes' => 'inline',
            ) );

        $cmb->add_field( array(
            'name' => __( 'Campaign Content', 'wpeurl' ),
            'id'   => $prefix . 'utm_content',
            'type' => 'text_small',
            'row_classes' => 'inline',
            ) );

        $cmb->add_field( array(
            'name' => __( 'Campaign Name', 'wpeurl' ),
            'id'   => $prefix . 'utm_campaign',
            'type' => 'text_small',
            'row_classes' => 'inline',
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

        $description = preg_match( $url_pattern, $redirect ) ? '<p class="cmb2-metabox-description">Your expanded URL is</p><pre class="overflow-scroll">' . $redirect . '</pre>' : '';

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

    /**
     * Register our setting to WP
     * @since  0.1.0
     */
    public function init() {
        register_setting( $this->options_slug, $this->options_slug );
    }

    /**
     * Add menu options page
     * @since 0.1.0
     */
    public function add_options_page() {
        $this->options_page = add_menu_page( $this->title, $this->title, 'manage_options', $this->options_slug, array( $this, 'admin_page_display' ), 'dashicons-editor-unlink' );
        // Include CMB CSS in the head to avoid FOUT
        add_action( "admin_print_styles-{$this->options_slug}", array( 'CMB2_hookup', 'enqueue_cmb_css' ) );
    }

    /**
     * Admin page markup. Mostly handled by CMB2
     * @since  0.1.0
     */
    public function admin_page_display() {
        ?>
        <div class="wrap cmb2-options-page <?php echo $this->options_slug; ?>">

            <h2><?php echo esc_html( get_admin_page_title() ); ?></h2>

            <div class="card">
                <?php cmb2_metabox_form( $this->options_slug . '_options', $this->options_slug, array( 'cmb_styles' => false ) ); ?>
            </div>

        </div>
        <?php
    }

    /**
     * Create an options page
     *
     * Create an options page for the plugin
     */
    public function add_options_metaboxes() {
        $prefix = 'wpe_';

        $cmb_options = new_cmb2_box( array(
            'id'      => $this->options_slug . '_options',
            'title'   => __( 'WP Engine Plugin Options', 'wpeurl' ),
            'hookup'  => false,
            'show_on' => array(
                'key'   => 'options-page',
                'value' => array( $this->options_slug )
            ),
        ) );

        $cmb_options->add_field( array(
            'id'    => $prefix . 'show_menus',
            'name'  => __( 'Hide default WordPress Menus?', 'wpeurl' ),
            'desc'  => __( 'Do you want to hide the default WordPress menus on this site?', 'wpeurl' ),
            'type'  => 'checkbox',
            ) );

        $cmb_options->add_field( array(
            'id'    => $prefix . 'redirect_url',
            'name'  => __( 'Redirect front page and 404s?', 'wpeurl' ),
            'desc'  => __( 'Where do you want to redirect the front page and 404 pages? ', 'wpeurl' ),
            'type'  => 'text_url',
            ) );
    }

    public function maybe_remove_menus() {
        // Abort if we're not supposed to hide the menus
        if ( ! isset( $this->plugin_options['wpe_show_menus'] ) || 'on' != $this->plugin_options['wpe_show_menus'] ) {
            return false;
        }

        // Remove any unneeded default dashboard menu items
        remove_menu_page( 'edit.php' );                   //Posts
        remove_menu_page( 'upload.php' );                 //Media
        remove_menu_page( 'edit.php?post_type=page' );    //Pages
        remove_menu_page( 'edit-comments.php' );          //Comments
        remove_menu_page( 'themes.php' );                 //Appearance
        remove_menu_page( 'tools.php' );                  //Tools
        remove_menu_page( 'options-general.php' );        //Settings
    }
}

WPEURLAdmin::get_instance();
