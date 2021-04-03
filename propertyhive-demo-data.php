<?php
/**
 * Plugin Name: Property Hive Demo Data Add On
 * Plugin Uri: http://wp-property-hive.com/addons/property-portal-demo-data/
 * Description: Add On for Property Hive allowing sets of test data to be automatically generated
 * Version: 1.1.27
 * Author: PropertyHive
 * Author URI: http://wp-property-hive.com
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( ! class_exists( 'PH_Demo_Data' ) ) :

final class PH_Demo_Data {

    const YES_OR_BLANK = array('', 'yes');
    const NUM_DEMO_DATA_ITEMS = 10;
    const NUM_FEATURES = 6;

    /**
     * @var string
     */
    public $version = '1.0.0';

    /**
     * @var Property Hive The single instance of the class
     */
    protected static $_instance = null;

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

        add_filter( 'propertyhive_settings_tabs_array', array( $this, 'add_settings_tab' ), 19 );
        add_action( 'propertyhive_settings_' . $this->id, array( $this, 'output' ) );

        add_action( 'wp_ajax_propertyhive_get_section_demo_data', array( $this, 'ajax_get_section_demo_data' ) );
        add_action( 'wp_ajax_propertyhive_create_demo_data_records', array( $this, 'ajax_create_demo_data_records' ) );

        add_filter( "plugin_action_links_" . plugin_basename( __FILE__ ), array( $this, 'plugin_add_settings_link' ) );
    }

    public function plugin_add_settings_link( $links )
    {
        $settings_link = '<a href="' . admin_url('admin.php?page=ph-settings&tab=demo_data') . '">' . __( 'Settings' ) . '</a>';
        array_push( $links, $settings_link );
        return $links;
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

            for ($x = 1; $x <= PH_Demo_Data::NUM_DEMO_DATA_ITEMS; $x++) {
                $data_items[] = $this->build_data_item($data_fields);
            }
        }

        header( 'Content-Type: application/json; charset=utf-8' );
        echo json_encode($data_items);
        die();
    }

    private function build_data_item($fields)
    {
        $data_item = array(
            'post' => $fields['post'],
            'meta_fields' => array(
                '_demo_data' => 'yes',
            ),
            'taxonomies' => array(),
            'related' => array(),
        );

        if ( isset( $data_item['post']['post_title'] ) && $data_item['post']['post_title'] == 'contact_name' )
        {
            $data_item['post']['post_title'] = $this->generate_contact_name();
        }

        if ( isset( $fields['meta'] ) )
        {
            foreach( $fields['meta'] as $meta_key => $options )
            {
                if (
                    !isset( $options['dependent_field'] )
                    ||
                    (
                        isset( $data_item['meta_fields'][$options['dependent_field']] )
                        &&
                        isset( $options['dependent_values'] )
                        &&
                        in_array( $data_item['meta_fields'][$options['dependent_field']], $options['dependent_values'] )
                    )
                )
                {
                    if ( isset( $options['field_value'] ) )
                    {
                        $data_item['meta_fields'][$meta_key] = $options['field_value'];
                    }
                    elseif ( isset( $options['possible_values'] ) )
                    {
                        $rand = rand(0, count($options['possible_values'])-1);
                        $data_item['meta_fields'][$meta_key] = $options['possible_values'][$rand];
                    }
                    elseif ( isset( $options['field_type'] ) )
                    {
                        switch ( $options['field_type'] )
                        {
                            case 'date':
                                $start_timestamp = strtotime('-2 month', time());
                                $end_timestamp = strtotime('+2 month', time());
                                $random_timestamp = rand($start_timestamp, $end_timestamp);
                                $data_item['meta_fields'][$meta_key] = date('Y-m-d', $random_timestamp);
                                break;
                            case 'integer':
                                if ( isset( $options['field_bounds'] ) )
                                {
                                    $min = isset( $options['field_bounds']['min'] ) ? $options['field_bounds']['min'] : 0;
                                    $max = isset( $options['field_bounds']['max'] ) ? $options['field_bounds']['max'] : getrandmax();
                                    $rand_int = rand($min, $max);

                                    if ( isset( $options['field_bounds']['round'] ) )
                                    {
                                        $rand_int = round($rand_int, $options['field_bounds']['round']);
                                    }

                                    $data_item['meta_fields'][$meta_key] = $rand_int;
                                }
                                else
                                {
                                    $data_item['meta_fields'][$meta_key] = rand();
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
                                            if ( $i > PH_Demo_Data::NUM_FEATURES ) { break; }

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
                                    $data_item['meta_fields']['_features'] = PH_Demo_Data::NUM_FEATURES;
                                    for ($i = 0; $i < PH_Demo_Data::NUM_FEATURES; ++$i)
                                    {
                                        $data_item['meta_fields']['_feature_' . $i] = 'Feature ' . ( $i + 1 );
                                    }
                                }
                                break;
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

                    $related_data_item = $this->build_data_item( $related_data_fields );

                    $data_item['related'][$related['meta_key']] = $related_data_item;
                }
            }
        }

        return $data_item;
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
                    'post_type' => $section,
                    'post_title' => 'property_address',
                    'post_excerpt' => '',
                    'post_content' 	 => '',
                    'post_status'    => 'publish',
                    'comment_status' => 'closed',
                );

                $data_fields['related'] = array(
                    array(
                        'section' => 'property_owner',
                        'meta_key' => '_owner_contact_id',
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

                $negotiators = array();
                $args = array(
                    'number' => 9999,
                    'role__not_in' => array('property_hive_contact') 
                );
                $user_query = new WP_User_Query( $args );

                if ( ! empty( $user_query->results ) ) 
                {
                    foreach ( $user_query->results as $user ) 
                    {
                        $negotiators[] = $user->ID;
                    }
                }

                $data_fields['meta']['_negotiator_id'] = array(
                    'possible_values' => $negotiators,
                );

                $args = array(
                    'fields' => 'ids',
                    'post_type' => 'office',
                    'nopaging' => true
                );
                $office_query = new WP_Query($args);

                $office_ids = $office_query->posts;

                $office_query->reset_postdata();

                $data_fields['meta']['_office_id'] = array(
                    'possible_values' => $office_ids,
                );

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

                $data_fields['meta']['_department'] = array(
                    'possible_values' => $departments,
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
                            'field_bounds' => array('min' => 500, 'max' => 10000, 'round' => -2),
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
                            'field_bounds' => array('min' => 50000, 'max' => 100000, 'round' => -4),
                            'dependent_field' => '_for_sale',
                            'dependent_values' => array('yes'),
                        ),
                        '_price_to' => array(
                            'field_type' => 'integer',
                            'field_bounds' => array('min' => 100000, 'max' => 2000000, 'round' => -4),
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
                            'field_bounds' => array('min' => 200, 'max' => 2000, 'round' => -2),
                            'dependent_field' => '_to_rent',
                            'dependent_values' => array('yes'),
                        ),
                        '_rent_to' => array(
                            'field_type' => 'integer',
                            'field_bounds' => array('min' => 20000, 'max' => 50000, 'round' => -3),
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
                            'field_bounds' => array('min' => 200, 'max' => 2000, 'round' => -2),
                            'dependent_field' => '_department',
                            'dependent_values' => array('commercial'),
                        ),
                        '_floor_area_to' => array(
                            'field_type' => 'integer',
                            'field_bounds' => array('min' => 2000, 'max' => 10000, 'round' => -2),
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

                $data_fields['post'] = array(
                    'post_type' => 'contact',
                    'post_title' => 'contact_name',
                    'post_content' 	 => '',
                    'post_status'    => 'publish',
                    'comment_status' => 'closed',
                    'ping_status'    => 'closed',
                );

                $data_fields['meta'] = array(
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
                        'field_type' => 'email_address'
                    ),
                    '_contact_types' => array(
                        'field_value' => array( 'owner' )
                    ),
                );

                break;
        }

        $data_fields = apply_filters( 'propertyhive_demo_data_' . $section . '_fields', $data_fields );

        return $data_fields;
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

        foreach( $data_item['meta_fields'] as $meta_key => $meta_value)
        {
            update_post_meta( $post_id, $meta_key, $meta_value );
        }

        foreach( $data_item['taxonomies'] as $taxonomy_name => $taxonomy_value)
        {
            wp_set_post_terms( $post_id, $taxonomy_value, $taxonomy_name );
        }

        foreach ( $data_item['related'] as $related_meta_key => $related_item)
        {
            $related_post_id = $this->create_demo_data_record($related_item);
            update_post_meta( $post_id, $related_meta_key, array( $related_post_id ) );
        }

        return $post_id;
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
     *
     * @uses propertyhive_admin_fields()
     * @uses self::get_settings()
     */
    public function output() {

        global $hide_save_button;

        $hide_save_button = true;

        propertyhive_admin_fields( self::get_demo_data_settings() );

        ?>
            <p class="submit">
                <input id="generate-demo-data" class="button-primary" type="button" value="Generate Data" />
            </p>
        <?php
    }

    /**
     * Get demo data settings
     *
     * @return array Array of settings
     */
    public function get_demo_data_settings() {

        $settings = array(

            array( 'title' => __( 'Demo Data Options', 'propertyhive' ), 'type' => 'title', 'desc' => '', 'id' => 'demo_data_settings' )

        );

        $settings[] = array(
            'title'    => __( 'Properties', 'propertyhive' ),
            'id'       => 'property',
            'class'    => 'demo_data_section',
            'type'     => 'checkbox',
            'value'    => 'yes',
            'desc'     => '<span id="property_status_span"></span>',
        );

        $settings[] = array( 'type' => 'sectionend', 'id' => 'demo_data_settings');

        return $settings;
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