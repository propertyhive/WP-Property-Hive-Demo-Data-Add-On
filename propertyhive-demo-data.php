<?php
/**
 * Plugin Name: Property Hive Demo Data Add On
 * Plugin Uri: https://wp-property-hive.com/addons/demo-data/
 * Description: Add On for Property Hive allowing sets of test data to be automatically generated
 * Version: 1.0.3
 * Author: PropertyHive
 * Author URI: https://wp-property-hive.com
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( ! class_exists( 'PH_Demo_Data' ) ) :

final class PH_Demo_Data {

    const YES_OR_BLANK = array('', 'yes');

    /**
     * @var string
     */
    public $version = '1.0.3';

    /**
     * @var Property Hive The single instance of the class
     */
    protected static $_instance = null;

    /**
     * @var string
     */
    public $id = '';

    /**
     * @var string
     */
    public $label = '';
    
    /**
     * Main Property Hive Demo Data Instance
     *
     * Ensures only one instance of Property Hive Demo Data is loaded or can be loaded.
     *
     * @static
     * @return Property Hive Demo Data - Main instance
     */
    public static function instance()
    {
        if ( is_null( self::$_instance ) )
        {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * Constructor.
     */
    public function __construct() {

        $this->id    = 'demo_data';
        $this->label = __( 'Demo Data', 'propertyhive' );

        // Define constants
        $this->define_constants();

        // Include required files
        $this->includes();

        add_action( 'admin_notices', array( $this, 'demo_data_error_notices') );
        add_action( 'admin_enqueue_scripts', array( $this, 'load_demo_data_admin_scripts' ) );

        add_filter( 'propertyhive_settings_tabs_array', array( $this, 'add_settings_tab' ), 25 );
        add_action( 'propertyhive_settings_' . $this->id, array( $this, 'output' ) );
        add_action( 'propertyhive_sections_' . $this->id, array( $this, 'output_sections' ) );

        add_action( 'wp_ajax_propertyhive_get_section_demo_data', array( $this, 'ajax_get_section_demo_data' ) );
        add_action( 'wp_ajax_propertyhive_create_demo_data_records', array( $this, 'ajax_create_demo_data_records' ) );
        add_action( 'wp_ajax_propertyhive_delete_demo_data', array( $this, 'ajax_delete_demo_data' ) );

        add_filter( "plugin_action_links_" . plugin_basename( __FILE__ ), array( $this, 'plugin_add_settings_link' ) );
    }

    private function includes()
    {
        include_once( 'includes/class-ph-demo-data-address.php' );
        include_once( 'includes/class-ph-demo-data-banks.php' );
    }

    /**
     * Define PH Demo Data Constants
     */
    private function define_constants() 
    {
        define( 'PH_DEMO_DATA_PLUGIN_FILE', __FILE__ );
        define( 'PH_DEMO_DATA_VERSION', $this->version );
    }

    /**
     * Get sections
     *
     * @return array
     */
    public function get_sections() {
        $sections = array(
            ''       => __( 'Generate Data', 'propertyhive' ),
            'delete' => __( 'Delete Data', 'propertyhive' ),
        );

        return $sections;
    }

    /**
     * Output sections
     */
    public function output_sections() {
        global $current_section;

        $sections = $this->get_sections();

        if ( empty( $sections ) )
            return;

        echo '<ul class="subsubsub">';

        $array_keys = array_keys( $sections );

        foreach ( $sections as $id => $label )
            echo '<li><a href="' . admin_url( 'admin.php?page=ph-settings&tab=' . $this->id . '&section=' . sanitize_title( $id ) ) . '" class="' . ( $current_section == $id ? 'current' : '' ) . '">' . $label . '</a> ' . ( end( $array_keys ) == $id ? '' : '|' ) . ' </li>';

        echo '</ul><br class="clear" />';
    }

    private function get_num_demo_data_items()
    {
        return apply_filters( 'propertyhive_demo_data_num_items', 10 );
    }

    private function get_num_features()
    {
        return apply_filters( 'propertyhive_demo_data_num_features', 6 );
    }

    private function get_num_property_photos()
    {
        return apply_filters( 'propertyhive_demo_data_num_property_photos', 1 );
    }

    public function plugin_add_settings_link( $links )
    {
        $settings_link = '<a href="' . admin_url('admin.php?page=ph-settings&tab=demo_data') . '">' . __( 'Settings' ) . '</a>';
        array_push( $links, $settings_link );
        return $links;
    }

    /**
     * Output error message if core Property Hive plugin isn't active
     */
    public function demo_data_error_notices() 
    {
        if (!is_plugin_active('propertyhive/propertyhive.php'))
        {
            $message = "The Property Hive plugin must be installed and activated before you can use the Property Hive Demo Data add-on";
            echo"<div class=\"error\"> <p>$message</p></div>";
        }
    }

    public function load_demo_data_admin_scripts()
    {
        $assets_path = str_replace( array( 'http:', 'https:' ), '', untrailingslashit( plugins_url( '/', __FILE__ ) ) ) . '/assets/';

        wp_register_script( 
            'ph-demo-data', 
            $assets_path . 'js/demo-data.js', 
            array('jquery'), 
            PH_DEMO_DATA_VERSION,
            true
        );
        wp_enqueue_script( 'ph-demo-data' );

        $params = array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
        );
        wp_localize_script( 'ph-demo-data', 'ph_demo_data', $params );
    }

    public function ajax_get_section_demo_data()
    {
        $data_items = array();

        if ( isset( $_POST['section'] ) )
        {
            $data_fields = $this->get_section_data_fields($_POST['section']);

            for ($x = 1; $x <= $this->get_num_demo_data_items(); ++$x) {
                $data_items[] = $this->build_data_item($data_fields);
            }
        }

        header( 'Content-Type: application/json; charset=utf-8' );
        echo json_encode($data_items);
        die();
    }

    private function build_data_item($fields, $id_stored_as = null)
    {
        $data_item = array(
            'post' => $fields['post'],
            'meta_fields' => array(
                '_demo_data' => 'yes',
            ),
            'taxonomies' => array(),
            'related' => array(),
        );

        if ( !is_null($id_stored_as) )
        {
            $data_item['id_stored_as'] = $id_stored_as;
        }

        if ( isset( $data_item['post']['post_title'] ) && $data_item['post']['post_title'] == 'contact_name' )
        {
            $data_item['post']['post_title'] = $this->generate_contact_name();
        }

        if ( isset( $data_item['post']['post_excerpt'] ) && $data_item['post']['post_excerpt'] == 'summary_description' )
        {
            $data_item['post']['post_excerpt'] = $this->generate_lorem_ipsum();
        }

        if ( isset( $fields['meta'] ) )
        {
            foreach( $fields['meta'] as $meta_key => $options )
            {
                if ( !isset( $options['parent_meta_key'] ) )
                {
                    $existing_meta_array = $data_item['meta_fields'];
                }
                else
                {
                    if ( !isset( $data_item['meta_fields'][$options['parent_meta_key']] ) )
                    {
                        $data_item['meta_fields'][$options['parent_meta_key']] = array();
                    }
                    $existing_meta_array = $data_item['meta_fields'][$options['parent_meta_key']];
                }

                if (
                    !isset( $options['dependent_field'] )
                    ||
                    (
                        isset( $existing_meta_array[$options['dependent_field']] )
                        &&
                        isset( $options['dependent_values'] )
                        &&
                        in_array( $existing_meta_array[$options['dependent_field']], $options['dependent_values'] )
                    )
                )
                {
                    $data_value = null;

                    if ( isset( $options['field_value'] ) )
                    {
                        $data_value = $options['field_value'];
                    }
                    elseif ( isset( $options['possible_values'] ) )
                    {
                        $rand = array_rand($options['possible_values']);
                        $data_value = $options['possible_values'][$rand];
                    }
                    elseif ( isset( $options['field_type'] ) )
                    {
                        switch ( $options['field_type'] )
                        {
                            case 'contact_name':
                                $data_value = $this->generate_contact_name();
                                break;
                            case 'date':
                            case 'datetime':
                                $start_timestamp = strtotime('-2 month', time());
                                $end_timestamp = strtotime('+2 month', time());
                                $random_timestamp = rand($start_timestamp, $end_timestamp);

                                // Round timestamp down to nearest half hour
                                $random_timestamp = $random_timestamp - ($random_timestamp % 1800);

                                $date_format = $options['field_type'] == 'date' ? 'Y-m-d' : 'Y-m-d H:i:s';
                                $data_value = date($date_format, $random_timestamp);
                                break;
                            case 'integer':
                                if ( isset( $options['field_bounds'] ) )
                                {
                                    $min = 0;
                                    if ( isset( $options['field_bounds']['min'] ) )
                                    {
                                        if ( is_string( $options['field_bounds']['min'] ) && isset( $existing_meta_array[$options['field_bounds']['min']] ) )
                                        {
                                            $min = $existing_meta_array[$options['field_bounds']['min']];
                                        }
                                        else
                                        {
                                            $min = $options['field_bounds']['min'];
                                        }
                                    }

                                    $max = getrandmax();
                                    if ( isset( $options['field_bounds']['max'] ) )
                                    {
                                        if ( is_string( $options['field_bounds']['max'] ) && isset( $existing_meta_array[$options['field_bounds']['max']] ) )
                                        {
                                            $max = $existing_meta_array[$options['field_bounds']['max']];
                                        }
                                        else
                                        {
                                            $max = $options['field_bounds']['max'];
                                        }
                                    }

                                    $data_value = rand($min, $max);

                                    if ( isset( $options['field_bounds']['round'] ) )
                                    {
                                        $data_value = round($data_value, $options['field_bounds']['round']);
                                    }
                                }
                                else
                                {
                                    $data_value = rand();
                                }
                                break;
                            case 'taxonomy_array':
                                $args = array(
                                    'hide_empty' => false,
                                    'parent' => 0
                                );
                                $terms = get_terms( $options['field_taxonomy'], $args );
                                if ( !empty( $terms ) && !is_wp_error( $terms ) )
                                {
                                    $taxonomy_ids = array();
                                    $i = 1;
                                    $num_entries = rand(1, count($terms));
                                    shuffle($terms);
                                    foreach( $terms as $term )
                                    {
                                        if ( $i > $num_entries ) { break; }

                                        $taxonomy_ids[] = $term->term_id;
                                        ++$i;
                                    }

                                    if ( count($taxonomy_ids) > 0 )
                                    {
                                        $data_value = $taxonomy_ids;
                                    }
                                }
                                break;
                            case 'address':
                                $PH_Demo_Data_Address = new PH_Demo_Data_Address();
                                $demo_address_fields = $PH_Demo_Data_Address->generate_demo_address_meta_fields();
                                foreach ( $demo_address_fields as $meta_key => $meta_value )
                                {
                                    $data_item['meta_fields'][$meta_key] = $meta_value;
                                }

                                if ( isset( $data_item['post']['post_title'] ) && $data_item['post']['post_title'] == 'property_address' )
                                {
                                    $data_item['post']['post_title'] = implode(', ', array(
                                        $demo_address_fields['_address_name_number'] . ' ' . $demo_address_fields['_address_street'],
                                        $demo_address_fields['_address_three']
                                    ));
                                }
                                break;
                            case 'features':
                                if ( get_option('propertyhive_features_type') == 'checkbox' )
                                {
                                    $features_taxonomies = [];
                                    $args = array(
                                        'hide_empty' => false,
                                        'parent' => 0
                                    );
                                    $terms = get_terms( 'property_feature', $args );
                                    if ( !empty( $terms ) && !is_wp_error( $terms ) )
                                    {
                                        $i = 1;
                                        shuffle($terms);
                                        foreach( $terms as $term )
                                        {
                                            if ( $i > $this->get_num_features() ) { break; }

                                            $features_taxonomies[] = $term->term_id;
                                            ++$i;
                                        }

                                        if ( count($features_taxonomies) > 0 )
                                        {
                                            $data_item['taxonomies']['property_feature'] = $features_taxonomies;
                                        }
                                    }
                                }
                                else
                                {
                                    $data_item['meta_fields']['_features'] = $this->get_num_features();
                                    for ($i = 0; $i < $this->get_num_features(); ++$i)
                                    {
                                        $data_item['meta_fields']['_feature_' . $i] = 'Feature ' . ( $i + 1 );
                                    }
                                }
                                break;
                            case 'location':
                                $args = array(
                                    'hide_empty' => false,
                                    'parent' => 0
                                );
                                $terms = get_terms( 'location', $args );
                                if ( !empty( $terms ) && !is_wp_error( $terms ) )
                                {
                                    $rand = rand(0, count($terms)-1);
                                    if ( get_option('propertyhive_applicant_locations_type') == 'text' )
                                    {
                                        $location_key = 'location_text';
                                        $location_value = $terms[$rand]->name;
                                    }
                                    else
                                    {
                                        $location_key = 'locations';
                                        $location_value = $terms[$rand]->term_id;
                                    }
                                    $data_item['meta_fields'][$options['parent_meta_key']][$location_key] = array( $location_value );
                                }
                                break;
                            case 'photos':

                                if ( get_option('propertyhive_images_stored_as', '') == 'urls' )
                                {

                                }
                                else
                                {
                                    $files = glob(dirname(__FILE__) . '/assets/images/*.*');
                                    shuffle($files);

                                    $media_ids = array();
                                    for ($i = 0; $i < $this->get_num_property_photos(); ++$i)
                                    {
                                        if ( isset($files[$i]) )
                                        {
                                            $upload = wp_upload_bits( $files[$i], null, file_get_contents($files[$i]) );

                                            if ( !isset($upload['error']) || $upload['error'] === FALSE )
                                            {
                                                // We don't already have a thumbnail and we're presented with an image
                                                $wp_filetype = wp_check_filetype( $upload['file'], null );

                                                $attachment = array(
                                                    'post_mime_type' => $wp_filetype['type'],
                                                    'post_content' => '',
                                                    'post_status' => 'inherit'
                                                );
                                                $attach_id = wp_insert_attachment( $attachment, $upload['file'] );

                                                if ( !empty($attach_id) )
                                                {
                                                    $attach_data = wp_generate_attachment_metadata( $attach_id, $upload['file'] );
                                                    wp_update_attachment_metadata( $attach_id,  $attach_data );

                                                    $media_ids[] = $attach_id;
                                                }
                                            }
                                        }
                                    }

                                    if ( !empty($media_ids) )
                                    {
                                        $data_item['meta_fields'][$meta_key] = $media_ids;
                                    }
                                }
                                break;
                            case 'post_id':

                                if ( isset( $options['post_type'] ) )
                                {
                                    $args = array(
                                        'post_type'      => $options['post_type'],
                                        'posts_per_page' => 1,
                                        'orderby'        => 'rand',
                                        'post_status'    => 'publish',
                                        'meta_query'  => array(
                                            array(
                                                'key' => '_demo_data',
                                                'value' => 'yes',
                                            )
                                        )
                                    );

                                    if ( isset( $options['meta_query'] ) )
                                    {
                                        foreach( $options['meta_query'] as $meta_query )
                                        {
                                            $additional_meta_query = array(
                                                'key' => $meta_query['meta_key'],
                                            );

                                            if ( isset( $meta_query['parent_meta_value'] ) && isset( $existing_meta_array[$meta_query['parent_meta_value']] ) )
                                            {
                                                $additional_meta_query['value'] = $existing_meta_array[$meta_query['parent_meta_value']];
                                            }
                                            else
                                            {
                                                $additional_meta_query['value'] = $meta_query['meta_value'];
                                            }

                                            if ( isset( $meta_query['compare'] ) )
                                            {
                                                $additional_meta_query['compare'] = $meta_query['compare'];
                                            }

                                            $args['meta_query'][] = $additional_meta_query;
                                        }
                                    }
                                    $query = new WP_Query( $args );

                                    if ( $query->have_posts() ) {
                                        while ( $query->have_posts() ) {
                                            $query->the_post();
                                            $data_value = get_the_ID();
                                        }
                                    }
                                    wp_reset_postdata();
                                }
                                break;
                        }
                    }

                    if ( $data_value !== null )
                    {
                        if ( !isset( $options['parent_meta_key'] ) )
                        {
                            $data_item['meta_fields'][$meta_key] = $data_value;

                            if ( isset( $options['duplicate_to'] ) )
                            {
                                $data_item['meta_fields'][$options['duplicate_to']] = $data_value;
                            }
                        }
                        else
                        {
                            $data_item['meta_fields'][$options['parent_meta_key']][$meta_key] = $data_value;

                            if ( isset( $options['duplicate_to'] ) )
                            {
                                $data_item['meta_fields'][$options['parent_meta_key']][$options['duplicate_to']] = $data_value;
                            }
                        }
                    }
                }
            }
        }

        if ( isset( $fields['taxonomy'] ) )
        {
            foreach( $fields['taxonomy'] as $taxonomy )
            {
                if (
                    !isset( $taxonomy['dependent_field'] )
                    ||
                    (
                        isset( $data_item['meta_fields'][$taxonomy['dependent_field']] )
                        &&
                        isset( $taxonomy['dependent_values'] )
                        &&
                        in_array( $data_item['meta_fields'][$taxonomy['dependent_field']], $taxonomy['dependent_values'] )
                    )
                )
                {
                    $args = array(
                        'hide_empty' => false,
                        'parent' => 0
                    );
                    $terms = get_terms( $taxonomy['name'], $args );

                    // If we're adding an availability and the item has a department, make sure we add one related to that department
                    if ( $taxonomy['name'] == 'availability' && isset($data_item['meta_fields']['_department']) )
                    {
                        $availability_departments = get_option( 'propertyhive_availability_departments', array() );
                        if ( !is_array($availability_departments) ) { $availability_departments = array(); }

                        if ( !empty($availability_departments) )
                        {
                            $department_availabilites = array_keys( array_filter( $availability_departments, function( $a ) use ( $data_item ) { return in_array( $data_item['meta_fields']['_department'], $a ); } ) );

                            $terms = array_filter( $terms, function( $term ) use( $department_availabilites ) { return in_array( $term->term_id, $department_availabilites ); } );
                            $terms = array_values($terms);
                        }
                    }

                    if ( !empty( $terms ) && !is_wp_error( $terms ) )
                    {
                        $rand = rand(0, count($terms)-1);
                        $data_item['taxonomies'][$taxonomy['name']] = $terms[$rand]->term_id;
                    }
                }
            }
        }

        if ( isset( $fields['related'] ) )
        {
            foreach( $fields['related'] as $related )
            {
                if ( isset( $related['meta_key'] ) )
                {
                    $related_data_fields = $this->get_section_data_fields( $related['section'] );

                    $stored_as = isset( $related['stored_as'] ) ? $related['stored_as'] : null;
                    $related_data_item = $this->build_data_item( $related_data_fields, $stored_as );

                    $data_item['related'][$related['meta_key']] = $related_data_item;
                }
            }
        }

        return $data_item;
    }

    private function generate_lorem_ipsum( $num_paragraphs = 1, $length = 'short' )
    {
        // Predefined Lorem Ipsum paragraphs (short and medium)
        $short_paragraphs = [
            "Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.",
            "Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.",
            "Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur.",
            "Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.",
            "Curabitur pretium tincidunt lacus. Nulla gravida orci a odio. Nullam varius, turpis et commodo pharetra.",
            "Etiam a tortor quis justo posuere placerat. Duis venenatis nulla in diam. Sed arcu. Cras consequat.",
            "Nulla imperdiet sit amet magna. Vestibulum dapibus, mauris nec malesuada fames ac turpis velit.",
            "Morbi in sem quis dui placerat ornare. Pellentesque odio nisi, euismod in, pharetra a, ultricies in, diam.",
            "Sed arcu. Cras consequat. Praesent dapibus, neque id cursus faucibus, tortor neque egestas augue.",
            "Aenean ultricies mi vitae est. Mauris placerat eleifend leo. Quisque sit amet est et sapien ullamcorper pharetra."
        ];

        $medium_paragraphs = [
            "Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.",
            "Curabitur pretium tincidunt lacus. Nulla gravida orci a odio. Nullam varius, turpis et commodo pharetra. Etiam a tortor quis justo posuere placerat. Duis venenatis nulla in diam. Sed arcu. Cras consequat. Nulla imperdiet sit amet magna. Vestibulum dapibus, mauris nec malesuada fames ac turpis velit. Morbi in sem quis dui placerat ornare. Pellentesque odio nisi, euismod in, pharetra a, ultricies in, diam. Sed arcu. Cras consequat. Praesent dapibus, neque id cursus faucibus, tortor neque egestas augue.",
            "Aenean ultricies mi vitae est. Mauris placerat eleifend leo. Quisque sit amet est et sapien ullamcorper pharetra. Vestibulum erat wisi, condimentum sed, commodo vitae, ornare sit amet, wisi. Aenean fermentum, elit eget tincidunt condimentum, eros ipsum rutrum orci, sagittis tempus lacus enim ac dui. Donec non enim in turpis pulvinar facilisis. Ut felis. Praesent dapibus, neque id cursus faucibus, tortor neque egestas augue, eu vulputate magna eros eu erat. Aliquam erat volutpat. Nam dui mi, tincidunt quis, accumsan porttitor, facilisis luctus, metus."
        ];

        // Choose the appropriate set of paragraphs based on the length
        $paragraphs = ($length === 'medium') ? $medium_paragraphs : $short_paragraphs;

        // Shuffle the array to ensure randomness
        shuffle($paragraphs);

        // Get the requested number of paragraphs
        $selected_paragraphs = array_slice($paragraphs, 0, $num_paragraphs);

        // Join paragraphs with double line breaks
        $lorem_ipsum = implode("\n\n", $selected_paragraphs);

        return $lorem_ipsum;
    }

    private function generate_contact_name()
    {
        $forename_array = PH_Demo_Data_Banks::$forenames;
        $surname_array = PH_Demo_Data_Banks::$surnames;

        return $forename_array[array_rand($forename_array)] . ' ' . $surname_array[array_rand($surname_array)];
    }

    private function get_section_data_fields($section)
    {
        $data_fields = array();

        switch( $section )
        {
            case 'property':

                $data_fields['post'] = array(
                    'post_type'      => $section,
                    'post_title'     => 'property_address',
                    'post_excerpt'   => 'summary_description',
                    'post_content'   => '',
                    'post_status'    => 'publish',
                    'comment_status' => 'closed',
                );

                $data_fields['related'] = array(
                    array(
                        'section' => 'property_owner',
                        'meta_key' => '_owner_contact_id',
                        'stored_as' => 'array',
                    ),
                );

                $data_fields['taxonomy'] = array(
                    array(
                        'name' => 'location',
                    ),
                    array(
                        'name' => 'availability',
                    ),
                    array(
                        'name' => 'price_qualifier',
                    ),
                    array(
                        'name' => 'sale_by',
                    ),
                    array(
                        'name' => 'tenure',
                    ),
                    array(
                        'name' => 'furnished',
                        'dependent_field' => '_department',
                        'dependent_values' => array('residential-lettings'),
                    ),
                    array(
                        'name' => 'property_type',
                        'dependent_field' => '_department',
                        'dependent_values' => array('residential-sales', 'residential-lettings'),
                    ),
                    array(
                        'name' => 'commercial_property_type',
                        'dependent_field' => '_department',
                        'dependent_values' => array('commercial'),
                    ),
                    array(
                        'name' => 'parking',
                        'dependent_field' => '_department',
                        'dependent_values' => array('residential-sales', 'residential-lettings'),
                    ),
                    array(
                        'name' => 'sale_by',
                        'dependent_field' => '_for_sale',
                        'dependent_values' => array('yes'),
                    ),
                    array(
                        'name' => 'commercial_tenure',
                        'dependent_field' => '_for_sale',
                        'dependent_values' => array('yes'),
                    ),
                );

                $data_fields['meta'] = array(
                    'property_address' => array(
                        'field_type' => 'address',
                    ),
                );

                $default_country_code = get_option('propertyhive_default_country', 'GB');
                $PH_Countries = new PH_Countries();

                $default_country = $PH_Countries->get_country( $default_country_code );

                $data_fields['meta']['_address_country'] = array(
                        'field_value' => $default_country_code
                );

                $data_fields['meta']['_currency'] = array(
                        'field_value' => $default_country['currency_code'],
                );

                $data_fields['meta']['_negotiator_id'] = array(
                    'possible_values' => $this->get_negotiators(),
                );

                $data_fields['meta']['_office_id'] = array(
                    'possible_values' => $this->get_offices(),
                );

                $data_fields['meta']['_department'] = array(
                    'possible_values' => $this->get_active_departments(),
                );

                $data_fields['meta'] = array_merge( $data_fields['meta'],
                    array(
                        '_on_market' => array(
                            'possible_values' => PH_Demo_Data::YES_OR_BLANK,
                        ),
                        '_featured' => array(
                            'possible_values' => PH_Demo_Data::YES_OR_BLANK,
                        ),
                        '_features_concatenated' => array(
                            'field_type' => 'features',
                        ),
                        '_owner_contact_id' => array(
                            'field_type' => 'owner_id',
                        ),
                        '_photos' => array(
                            'field_type' => 'photos',
                        ),
                        '_price' => array(
                            'field_type' => 'integer',
                            'field_bounds' => array('min' => 50000, 'max' => 2000000, 'round' => -4),
                            'dependent_field' => '_department',
                            'dependent_values' => array('residential-sales'),
                        ),
                        '_rent' => array(
                            'field_type' => 'integer',
                            'field_bounds' => array('min' => 200, 'max' => 10000, 'round' => -2),
                            'dependent_field' => '_department',
                            'dependent_values' => array('residential-lettings'),
                        ),
                        '_rent_frequency' => array(
                            'possible_values' => array('pppw', 'pw', 'pcm', 'pq', 'pa'),
                            'dependent_field' => '_department',
                            'dependent_values' => array('residential-lettings'),
                        ),
                        '_deposit' => array(
                            'field_type' => 'integer',
                            'field_bounds' => array('min' => '_rent', 'max' => 10000, 'round' => -2),
                            'dependent_field' => '_department',
                            'dependent_values' => array('residential-lettings'),
                        ),
                        '_available_date' => array(
                            'field_type' => 'date',
                            'dependent_field' => '_department',
                            'dependent_values' => array('residential-lettings'),
                        ),
                        '_bedrooms' => array(
                            'field_type' => 'integer',
                            'field_bounds' => array('min' => 0, 'max' => 5),
                            'dependent_field' => '_department',
                            'dependent_values' => array('residential-sales', 'residential-lettings'),
                        ),
                        '_bathrooms' => array(
                            'field_type' => 'integer',
                            'field_bounds' => array('min' => 0, 'max' => 4),
                            'dependent_field' => '_department',
                            'dependent_values' => array('residential-sales', 'residential-lettings'),
                        ),
                        '_reception_rooms' => array(
                            'field_type' => 'integer',
                            'field_bounds' => array('min' => 0, 'max' => 3),
                            'dependent_field' => '_department',
                            'dependent_values' => array('residential-sales', 'residential-lettings'),
                        ),
                        '_rooms' => array(
                            'field_value' => '1',
                            'dependent_field' => '_department',
                            'dependent_values' => array('residential-sales', 'residential-lettings'),
                        ),
                        '_room_name_0' => array(
                            'field_value' => 'Full Description',
                            'dependent_field' => '_department',
                            'dependent_values' => array('residential-sales', 'residential-lettings'),
                        ),
                        '_room_dimensions_0' => array(
                            'field_value' => '',
                            'dependent_field' => '_department',
                            'dependent_values' => array('residential-sales', 'residential-lettings'),
                        ),
                        '_room_description_0' => array(
                            'field_value' => trim( $this->generate_lorem_ipsum(3, 'medium') ),
                            'dependent_field' => '_department',
                            'dependent_values' => array('residential-sales', 'residential-lettings'),
                        ),
                        '_descriptions' => array(
                            'field_value' => '1',
                            'dependent_field' => '_department',
                            'dependent_values' => array('commercial'),
                        ),
                        '_description_name_0' => array(
                            'field_value' => 'Full Description',
                            'dependent_field' => '_department',
                            'dependent_values' => array('commercial'),
                        ),
                        '_description_0' => array(
                            'field_value' => trim( $this->generate_lorem_ipsum(3, 'medium') ),
                            'dependent_field' => '_department',
                            'dependent_values' => array('commercial'),
                        ),
                        '_for_sale' => array(
                            'possible_values' => PH_Demo_Data::YES_OR_BLANK,
                            'dependent_field' => '_department',
                            'dependent_values' => array('commercial'),
                        ),
                        '_to_rent' => array(
                            'possible_values' => PH_Demo_Data::YES_OR_BLANK,
                            'dependent_field' => '_department',
                            'dependent_values' => array('commercial'),
                        ),
                        '_price_from' => array(
                            'field_type' => 'integer',
                            'field_bounds' => array('min' => 50000, 'max' => 2000000, 'round' => -4),
                            'dependent_field' => '_for_sale',
                            'dependent_values' => array('yes'),
                        ),
                        '_price_to' => array(
                            'field_type' => 'integer',
                            'field_bounds' => array('min' => '_price_from', 'max' => 2000000, 'round' => -4),
                            'dependent_field' => '_for_sale',
                            'dependent_values' => array('yes'),
                        ),
                        '_price_units' => array(
                            'possible_values' => array_merge(array(''), array_keys( get_commercial_price_units() )),
                            'dependent_field' => '_for_sale',
                            'dependent_values' => array('yes'),
                        ),
                        '_rent_from' => array(
                            'field_type' => 'integer',
                            'field_bounds' => array('min' => 200, 'max' => 50000, 'round' => -2),
                            'dependent_field' => '_to_rent',
                            'dependent_values' => array('yes'),
                        ),
                        '_rent_to' => array(
                            'field_type' => 'integer',
                            'field_bounds' => array('min' => '_rent_from', 'max' => 50000, 'round' => -3),
                            'dependent_field' => '_to_rent',
                            'dependent_values' => array('yes'),
                        ),
                        '_rent_units' => array(
                            'possible_values' => array('pppw', 'pw', 'pcm', 'pq', 'pa'),
                            'dependent_field' => '_department',
                            'dependent_values' => array('residential-lettings'),
                        ),
                        '_floor_area_from' => array(
                            'field_type' => 'integer',
                            'field_bounds' => array('min' => 200, 'max' => 5000, 'round' => -2),
                            'dependent_field' => '_department',
                            'dependent_values' => array('commercial'),
                        ),
                        '_floor_area_to' => array(
                            'field_type' => 'integer',
                            'field_bounds' => array('min' => '_floor_area_from', 'max' => 10000, 'round' => -2),
                            'dependent_field' => '_department',
                            'dependent_values' => array('commercial'),
                        ),
                        '_floor_area_units' => array(
                            'possible_values' => array_keys( get_area_units() ),
                            'dependent_field' => '_department',
                            'dependent_values' => array('commercial'),
                        ),
                    )
                );
                break;
            case 'property_owner':

                $data_fields['post'] = $this->get_contact_post_parameters();

                $data_fields['meta'] = $this->get_contact_meta_parameters( array('owner') );

                break;
            case 'applicant':

                $data_fields['post'] = $this->get_contact_post_parameters();

                $data_fields['meta'] = $this->get_contact_meta_parameters(array('applicant'));

                $data_fields['meta']['_applicant_profiles'] = array(
                    'field_value' => 1,
                );

                $data_fields['meta']['department'] = array(
                    'possible_values' => $this->get_active_departments(),
                    'parent_meta_key' => '_applicant_profile_0',
                );

                $data_fields['meta']['location'] = array(
                    'field_type' => 'location',
                    'parent_meta_key' => '_applicant_profile_0',
                );

                $data_fields['meta']['notes'] = array(
                    'field_value' => '',
                    'parent_meta_key' => '_applicant_profile_0',
                );

                $data_fields['meta']['send_matching_properties'] = array(
                    'field_value' => '',
                    'parent_meta_key' => '_applicant_profile_0',
                );

                $data_fields['meta']['auto_match_disabled'] = array(
                    'field_value' => 'yes',
                    'parent_meta_key' => '_applicant_profile_0',
                );

                $data_fields['meta']['grading'] = array(
                    'possible_values' => array('', 'hot'),
                    'parent_meta_key' => '_applicant_profile_0',
                );

                $data_fields['meta']['max_price'] = array(
                    'field_type' => 'integer',
                    'field_bounds' => array('min' => 50000, 'max' => 2000000, 'round' => -4),
                    'dependent_field' => 'department',
                    'dependent_values' => array('residential-sales'),
                    'duplicate_to' => 'max_price_actual',
                    'parent_meta_key' => '_applicant_profile_0',
                );

                $data_fields['meta']['match_price_range_lower'] = array(
                    'field_type' => 'integer',
                    'field_bounds' => array('min' => 50000, 'max' => 'max_price', 'round' => -4),
                    'dependent_field' => 'department',
                    'dependent_values' => array('residential-sales'),
                    'duplicate_to' => 'match_price_range_lower_actual',
                    'parent_meta_key' => '_applicant_profile_0',
                );

                $data_fields['meta']['match_price_range_higher'] = array(
                    'field_type' => 'integer',
                    'field_bounds' => array('min' => 'max_price', 'max' => 2000000, 'round' => -4),
                    'dependent_field' => 'department',
                    'dependent_values' => array('residential-sales'),
                    'duplicate_to' => 'match_price_range_higher_actual',
                    'parent_meta_key' => '_applicant_profile_0',
                );

                $data_fields['meta']['max_rent'] = array(
                    'field_type' => 'integer',
                    'field_bounds' => array('min' => 200, 'max' => 3000, 'round' => -2),
                    'dependent_field' => 'department',
                    'dependent_values' => array('residential-lettings'),
                    'duplicate_to' => 'max_price_actual',
                    'parent_meta_key' => '_applicant_profile_0',
                );

                $data_fields['meta']['rent_frequency'] = array(
                    'field_value' => 'pcm',
                    'dependent_field' => 'department',
                    'dependent_values' => array('residential-lettings'),
                    'parent_meta_key' => '_applicant_profile_0',
                );

                $data_fields['meta']['min_beds'] = array(
                    'field_type' => 'integer',
                    'field_bounds' => array('min' => 0, 'max' => 5),
                    'dependent_field' => 'department',
                    'dependent_values' => array('residential-sales', 'residential-lettings'),
                    'parent_meta_key' => '_applicant_profile_0',
                );

                $data_fields['meta']['property_types'] = array(
                    'field_type' => 'taxonomy_array',
                    'field_taxonomy' => 'property_type',
                    'dependent_field' => 'department',
                    'dependent_values' => array('residential-sales', 'residential-lettings'),
                    'parent_meta_key' => '_applicant_profile_0',
                );

                $data_fields['meta']['available_as'] = array(
                    'possible_values' => array(
                        array( 'sale' ),
                        array( 'rent' ),
                        array( 'sale', 'rent' ),
                    ),
                    'dependent_field' => 'department',
                    'dependent_values' => array('commercial'),
                    'parent_meta_key' => '_applicant_profile_0',
                );

                $data_fields['meta']['min_floor_area'] = array(
                    'field_type' => 'integer',
                    'field_bounds' => array('min' => 200, 'max' => 10000, 'round' => -2),
                    'dependent_field' => 'department',
                    'dependent_values' => array('commercial'),
                    'duplicate_to' => 'min_floor_area_actual',
                    'parent_meta_key' => '_applicant_profile_0',
                );

                $data_fields['meta']['max_floor_area'] = array(
                    'field_type' => 'integer',
                    'field_bounds' => array('min' => 'min_floor_area', 'max' => 10000, 'round' => -2),
                    'dependent_field' => 'department',
                    'dependent_values' => array('commercial'),
                    'duplicate_to' => 'max_floor_area_actual',
                    'parent_meta_key' => '_applicant_profile_0',
                );

                $data_fields['meta']['floor_area_units'] = array(
                    'field_value' => 'sqft',
                    'dependent_field' => 'department',
                    'dependent_values' => array('commercial'),
                    'parent_meta_key' => '_applicant_profile_0',
                );

                $data_fields['meta']['commercial_property_types'] = array(
                    'field_type' => 'taxonomy_array',
                    'field_taxonomy' => 'commercial_property_type',
                    'dependent_field' => 'department',
                    'dependent_values' => array('commercial'),
                    'parent_meta_key' => '_applicant_profile_0',
                );

                break;
            case 'appraisal':

                $data_fields['post'] = array(
                    'post_type'      => 'appraisal',
                    'post_content'   => '',
                    'post_status'    => 'publish',
                    'comment_status' => 'closed',
                    'ping_status'    => 'closed',
                );

                $data_fields['related'] = array(
                    array(
                        'section' => 'property_owner',
                        'meta_key' => '_property_owner_contact_id',
                    ),
                );

                $data_fields['taxonomy'] = array(
                    array(
                        'name' => 'property_type',
                    ),
                    array(
                        'name' => 'parking',
                    ),
                    array(
                        'name' => 'outside_space',
                    ),
                );

                $data_fields['meta'] = array(
                    'property_address' => array(
                        'field_type' => 'address',
                    ),
                    '_address_country' => array(
                        'field_value' => get_option('propertyhive_default_country', 'GB')
                    ),
                    '_bedrooms' => array(
                        'field_type' => 'integer',
                        'field_bounds' => array('min' => 0, 'max' => 5),
                    ),
                    '_bathrooms' => array(
                        'field_type' => 'integer',
                        'field_bounds' => array('min' => 0, 'max' => 4),
                    ),
                    '_reception_rooms' => array(
                        'field_type' => 'integer',
                        'field_bounds' => array('min' => 0, 'max' => 3),
                    ),
                    '_negotiator_id' => array(
                        'possible_values' => $this->get_negotiators(),
                    ),
                    '_start_date_time' => array(
                        'field_type' => 'datetime',
                    ),
                    '_duration' => array(
                        'field_value' => 3600,
                    ),
                    '_department' => array(
                        'possible_values' => array( 'residential-sales', 'residential-lettings' ),
                    ),
                    '_status' => array(
                        'possible_values' => array( 'pending', 'cancelled', 'carried_out', 'won', 'lost' ),
                    ),
                );
                break;
            case 'viewing':

                $data_fields['post'] = array(
                    'post_type'      => 'viewing',
                    'post_content'   => '',
                    'post_status'    => 'publish',
                    'comment_status' => 'closed',
                    'ping_status'    => 'closed',
                );

                $data_fields['meta'] = array(
                    '_start_date_time' => array(
                        'field_type' => 'datetime',
                    ),
                    '_duration' => array(
                        'field_value' => 3600,
                    ),
                    '_negotiator_id' => array(
                        'possible_values' => $this->get_negotiators(),
                    ),
                    '_status' => array(
                        'possible_values' => array( 'pending', 'cancelled', 'carried_out', 'no_show' ),
                    ),
                    '_feedback_status' => array(
                        'possible_values' => array( 'not_required', 'interested', 'not_interested' ),
                        'dependent_field' => '_status',
                        'dependent_values' => array('carried_out'),
                    ),
                    '_feedback_passed_on' => array(
                        'possible_values' => PH_Demo_Data::YES_OR_BLANK,
                        'dependent_field' => '_feedback_status',
                        'dependent_values' => array('interested', 'not_interested'),
                    ),
                );

                $departments = $this->get_active_departments();
                $random_department = $departments[array_rand($departments)];

                $data_fields['meta'] = array_merge(
                    $data_fields['meta'],
                    array(
                        '_property_id' => array(
                            'field_type' => 'post_id',
                            'post_type' => 'property',
                            'meta_query' => array(
                                array(
                                    'meta_key' => '_department',
                                    'meta_value' => $random_department,
                                ),
                            ),
                        ),
                        '_applicant_contact_id' => array(
                            'field_type' => 'post_id',
                            'post_type' => 'contact',
                            'meta_query' => array(
                                array(
                                    'meta_key' => '_applicant_profile_0',
                                    'meta_value' => $random_department,
                                    'compare' => 'LIKE',
                                ),
                            ),
                        ),
                    )
                );
                break;
            case 'offer':

                $data_fields['post'] = array(
                    'post_type'      => 'offer',
                    'post_content'   => '',
                    'post_status'    => 'publish',
                    'comment_status' => 'closed',
                    'ping_status'    => 'closed',
                );

                $data_fields['meta'] = array(
                    '_offer_date_time' => array(
                        'field_type' => 'datetime',
                    ),
                    '_amount' => array(
                        'field_type' => 'integer',
                        'field_bounds' => array('min' => 50000, 'max' => 2000000, 'round' => -4),
                    ),
                    '_status' => array(
                        'possible_values' => array( 'pending', 'declined', 'accepted' ),
                    ),
                    '_property_id' => array(
                        'field_type' => 'post_id',
                        'post_type' => 'property',
                        'meta_query' => array(
                            array(
                                'meta_key' => '_department',
                                'meta_value' => 'residential-sales',
                            ),
                        ),
                    ),
                    '_applicant_contact_id' => array(
                        'field_type' => 'post_id',
                        'post_type' => 'contact',
                        'meta_query' => array(
                            array(
                                'meta_key' => '_applicant_profile_0',
                                'meta_value' => 'residential-sales',
                                'compare' => 'LIKE',
                            ),
                        ),
                    ),
                );
                break;
            case 'sale':

                $data_fields['post'] = array(
                    'post_type'      => 'sale',
                    'post_content'   => '',
                    'post_status'    => 'publish',
                    'comment_status' => 'closed',
                    'ping_status'    => 'closed',
                );

                $data_fields['meta'] = array(
                    '_sale_date_time' => array(
                        'field_type' => 'datetime',
                    ),
                    '_amount' => array(
                        'field_type' => 'integer',
                        'field_bounds' => array('min' => 50000, 'max' => 2000000, 'round' => -4),
                    ),
                    '_status' => array(
                        'possible_values' => array( 'current', 'fallen_through', 'exchanged', 'completed' ),
                    ),
                    '_property_id' => array(
                        'field_type' => 'post_id',
                        'post_type' => 'property',
                        'meta_query' => array(
                            array(
                                'meta_key' => '_department',
                                'meta_value' => 'residential-sales',
                            ),
                        ),
                    ),
                    '_applicant_contact_id' => array(
                        'field_type' => 'post_id',
                        'post_type' => 'contact',
                        'meta_query' => array(
                            array(
                                'meta_key' => '_applicant_profile_0',
                                'meta_value' => 'residential-sales',
                                'compare' => 'LIKE',
                            ),
                        ),
                    ),
                );

                break;
            case 'tenancy':

                $data_fields['post'] = array(
                    'post_type'      => 'tenancy',
                    'post_content' 	 => '',
                    'post_status'    => 'publish',
                    'comment_status' => 'closed',
                    'ping_status'    => 'closed',
                );

                $default_country_code = get_option('propertyhive_default_country', 'GB');
                $PH_Countries = new PH_Countries();

                $default_country = $PH_Countries->get_country( $default_country_code );

                $data_fields['meta'] = array(
                    '_status' => array(
                        'field_value' => 'application',
                    ),
                    '_length' => array(
                        'field_type' => 'integer',
                        'field_bounds' => array('min' => 6, 'max' => 18),
                    ),
                    '_length_units' => array(
                        'field_value' => 'month',
                    ),
                    '_lease_type' => array(
                        'possible_values' => array( 'assured', 'assured_shorthold' ),
                    ),
                    '_start_date' => array(
                        'field_type' => 'date',
                    ),
                    '_end_date' => array(
                        'field_type' => 'date',
                    ),
                    '_rent' => array(
                        'field_type' => 'integer',
                        'field_bounds' => array('min' => 800, 'max' => 3000, 'round' => -2),
                    ),
                    '_rent_frequency' => array(
                        'possible_values' => array('pw', 'pcm', 'pq', 'pa'),
                    ),
                    '_currency' => array(
                        'field_value' => $default_country['currency_code'],
                    ),
                    '_deposit' => array(
                        'field_type' => 'integer',
                        'field_bounds' => array('min' => '_rent', 'max' => 6000, 'round' => -2),
                    ),
                    '_deposit_scheme' => array(
                        'possible_values' => array( 'dps', 'mydeposits', 'tds', 'lps', 'safedeposits', 'none' ),
                    ),
                    '_management_type' => array(
                        'possible_values' => array( 'let_only', 'fully_managed' ),
                    ),
                    '_property_id' => array(
                        'field_type' => 'post_id',
                        'post_type' => 'property',
                        'meta_query' => array(
                            array(
                                'meta_key' => '_department',
                                'meta_value' => 'residential-lettings',
                            ),
                        ),
                    ),
                    '_applicant_contact_id' => array(
                        'field_type' => 'post_id',
                        'post_type' => 'contact',
                        'meta_query' => array(
                            array(
                                'meta_key' => '_applicant_profile_0',
                                'meta_value' => 'residential-lettings',
                                'compare' => 'LIKE',
                            ),
                        ),
                    ),
                );
                break;
            case 'enquiry':

                $data_fields['post'] = array(
                    'post_type'      => 'enquiry',
                    'post_title'     => 'Demo Data Enquiry',
                    'post_content' 	 => '',
                    'post_status'    => 'publish',
                    'comment_status' => 'closed',
                    'ping_status'    => 'closed',
                );

                $current_user = wp_get_current_user();

                $data_fields['meta'] = array(
                    '_status' => array(
                        'possible_values' => array( 'open', 'closed' ),
                    ),
                    '_negotiator_id' => array(
                        'possible_values' => $this->get_negotiators(),
                    ),
                    '_office_id' => array(
                        'possible_values' => $this->get_offices(),
                    ),
                    '_source' => array(
                        'possible_values' => $this->get_enquiry_sources(),
                    ),
                    '_added_manually' => array(
                        'field_value' => 'yes',
                    ),
                    'name' => array(
                        'field_type' => 'contact_name',
                    ),
                    'telephone' => array(
                        'field_value' => '01234 567890',
                    ),
                    'email' => array(
                        'field_value' => $this->make_email_address_unique($current_user->user_email),
                    ),
                    'property_id' => array(
                        'field_type' => 'post_id',
                        'post_type' => 'property',
                    ),
                );
                break;
        }

        $data_fields = apply_filters( 'propertyhive_demo_data_' . $section . '_fields', $data_fields );

        return $data_fields;
    }

    private function get_contact_post_parameters()
    {
        return array(
            'post_type' => 'contact',
            'post_title' => 'contact_name',
            'post_content' 	 => '',
            'post_status'    => 'publish',
            'comment_status' => 'closed',
            'ping_status'    => 'closed',
        );
    }

    private function get_contact_meta_parameters( $contact_types )
    {
        $current_user = wp_get_current_user();

        return array(
            'property_address' => array(
                'field_type' => 'address',
            ),
            '_address_country' => array(
                'field_value' => get_option('propertyhive_default_country', 'GB')
            ),
            '_telephone_number' => array(
                'field_value' => '01234 567890'
            ),
            '_telephone_number_clean' => array(
                'field_value' => '01234567890'
            ),
            '_email_address' => array(
                'field_value' => $current_user->user_email
            ),
            '_contact_types' => array(
                'field_value' => $contact_types
            ),
        );
    }

    private function get_active_departments()
    {
        $departments = array();
        if ( get_option('propertyhive_active_departments_sales') == 'yes' )
        {
            $departments[] = 'residential-sales';
        }
        if ( get_option('propertyhive_active_departments_lettings') == 'yes' )
        {
            $departments[] = 'residential-lettings';
        }
        if ( get_option('propertyhive_active_departments_commercial') == 'yes' )
        {
            $departments[] = 'commercial';
        }

        $departments = array_merge( $departments, array_keys( ph_get_custom_departments( true ) ) );

        return $departments;
    }

    private function get_negotiators()
    {
        $negotiators = array();
        $args = array(
            'number' => 9999,
            'role__not_in' => apply_filters( 'property_negotiator_exclude_roles', array('property_hive_contact', 'subscriber') ),
        );
        $user_query = new WP_User_Query( $args );

        if ( ! empty( $user_query->results ) )
        {
            foreach ( $user_query->results as $user )
            {
                $negotiators[] = $user->ID;
            }
        }

        return $negotiators;
    }

    private function get_offices()
    {
        $args = array(
            'fields' => 'ids',
            'post_type' => 'office',
            'nopaging' => true
        );
        $office_query = new WP_Query($args);

        $office_ids = $office_query->posts;

        $office_query->reset_postdata();

        return $office_ids;
    }

    private function get_enquiry_sources()
    {
        $sources = array(
            'office' => __( 'Office', 'propertyhive' ),
            'website' => __( 'Website', 'propertyhive' )
        );

        $sources = apply_filters( 'propertyhive_enquiry_sources', $sources );

        return array_keys($sources);
    }

    private function make_email_address_unique( $email_address )
    {
        return str_replace( '@', '+' . rand(1, 10000) . '@', $email_address);
    }

    public function ajax_create_demo_data_records()
    {
        $records_inserted = 0;
        if ( isset( $_POST['data_items'] ) && is_array( $_POST['data_items'] ) )
        {
            foreach( $_POST['data_items'] as $data_item )
            {
                $post_id = $this->create_demo_data_record($data_item);
                ++$records_inserted;
            }
        }

        echo $records_inserted;
        die();
    }

    private function create_demo_data_record( $data_item )
    {
        $post_id = wp_insert_post( $data_item['post'], true );

        if ( isset($data_item['meta_fields']) && !empty($data_item['meta_fields']) )
        {
            foreach( $data_item['meta_fields'] as $meta_key => $meta_value)
            {
                update_post_meta( $post_id, $meta_key, $meta_value );
            }
        }

        if ( isset($data_item['taxonomies']) && !empty($data_item['taxonomies']) )
        {
            foreach( $data_item['taxonomies'] as $taxonomy_name => $taxonomy_value)
            {
                wp_set_post_terms( $post_id, $taxonomy_value, $taxonomy_name );
            }
        }

        if ( isset($data_item['related']) && !empty($data_item['related']) )
        {
            foreach ( $data_item['related'] as $related_meta_key => $related_item)
            {
                $related_post_id = $this->create_demo_data_record($related_item);
                update_post_meta( $post_id, $related_meta_key, $related_post_id );
            }
        }

        if ( $data_item['post']['post_type'] == 'property' )
        {
            $ph_countries = new PH_Countries();
            $ph_countries->update_property_price_actual( $post_id );
        }

        if ( isset( $data_item['id_stored_as'] ) && $data_item['id_stored_as'] == 'array' )
        {
            return array( $post_id );
        }
        else
        {
            return $post_id;
        }
    }

    public function ajax_delete_demo_data()
    {
        $records_deleted = 0;
        if ( isset( $_POST['section'] ) )
        {
            $records_deleted = $this->delete_demo_data($_POST['section']);
        }

        echo $records_deleted;
        die();
    }

    private function delete_demo_data( $section )
    {
        $posts_deleted = 0;

        $args = array(
            'fields'         => 'ids',
            'post_type'      => $section,
            'posts_per_page' => '-1',
            'meta_query'     => array(
                array(
                    'key' => '_demo_data',
                    'value' => 'yes',
                )
            )
        );
        $query = new WP_Query( $args );

        if ( $query->have_posts() ) {
            while ( $query->have_posts() ) {
                $query->the_post();
                wp_delete_post(get_the_ID(), true);
                ++$posts_deleted;
            }
        }
        wp_reset_postdata();

        return $posts_deleted;
    }

    /**
     * Add a new settings tab to the Property Hive settings tabs array.
     *
     * @param array $settings_tabs Array of Property Hive setting tabs & their labels, excluding the Subscription tab.
     * @return array $settings_tabs Array of Property Hive setting tabs & their labels, including the Subscription tab.
     */
    public function add_settings_tab( $settings_tabs ) {
        $settings_tabs['demo_data'] = __( 'Demo Data', 'propertyhive' );
        return $settings_tabs;
    }

    /**
     * Uses the Property Hive admin fields API to output settings.
     */
    public function output() {

        global $current_section, $hide_save_button;

        $hide_save_button = true;

        if ( $current_section )
        {
            switch ($current_section)
            {
                case "delete":
                {
                    ?>
                        <h3>Delete Demo Data</h3>
                        <p>Clicking the button below will delete any demo data previously generated using this add on. Data added manually or via another source will remain untouched.
                            <br><br>
                        <strong>NOTE: This action is irreversible and any records deleted will be permanently trashed.</strong></p>

                        <p class="submit">
                            <input id="delete-demo-data" class="button-primary" type="button" value="Delete Data" />
                        </p>
                        <div id="delete_demo_data_results"></div>
                    <?php
                    $sections_to_delete = array('contact', 'property', 'appraisal', 'viewing', 'offer', 'sale', 'tenancy', 'enquiry');
                    $sections_to_delete = apply_filters( 'propertyhive_demo_data_sections_to_delete', $sections_to_delete );

                    foreach ( $sections_to_delete as $section )
                    {
                        echo '<input type="hidden" name="sections_to_delete[]" value="' . $section . '">';
                    }
                    break;
                }
                default: { die("Unknown setting section"); }
            }
        }
        else
        {
            ?>
                <h3>Generate Demo Data</h3>
                <p>Clicking the button below will generate <?php echo $this->get_num_demo_data_items(); ?> pieces of randomly-generated demo data in each section for you to use within the system.<br><br>When you are finished with the data, it can be deleted by using the Delete Data option above.</p>

                <p class="submit">
                    <input id="generate-demo-data" class="button-primary" type="button" value="Generate Demo Data" />
                </p>
                <div id="demo_data_property_results"></div>
                <div id="demo_data_applicant_results"></div>
                <div id="demo_data_other_results"></div>
            <?php

                $demo_data_sub_sections = array('appraisal', 'viewing', 'offer', 'sale', 'tenancy', 'enquiry');
                $demo_data_sub_sections = apply_filters( 'propertyhive_demo_data_sub_sections', $demo_data_sub_sections );

                foreach ( $demo_data_sub_sections as $sub_section )
                {
                    echo '<input type="hidden" name="sub_sections[]" value="' . $sub_section . '">';
                }
        }
    }
}

endif;

/**
 * Returns the main instance of PH_Demo_Data to prevent the need to use globals.
 *
 * @since  1.0.0
 * @return PH_Demo_Data
 */
function PHDD() {
    return PH_Demo_Data::instance();
}

PHDD();

if( is_admin() && file_exists(  dirname( __FILE__ ) . '/propertyhive-demo-data-update.php' ) )
{
    include_once( dirname( __FILE__ ) . '/propertyhive-demo-data-update.php' );
}