<?php
/**
 * Installation related functions and actions.
 *
 * @author 		PropertyHive
 * @category 	Admin
 * @package 	PropertyHive/Classes
 * @version     1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( ! class_exists( 'PH_Demo_Data_Install' ) ) :

/**
 * PH_Demo_Data_Install Class
 */
class PH_Demo_Data_Install {

    /**
     * Hook in tabs.
     */
    public function __construct() {
        register_activation_hook( PH_DEMO_DATA_PLUGIN_FILE, array( $this, 'install' ) );
        register_deactivation_hook( PH_DEMO_DATA_PLUGIN_FILE, array( $this, 'deactivate' ) );
        register_uninstall_hook( PH_DEMO_DATA_PLUGIN_FILE, array( 'PH_Demo_Data_Install', 'uninstall' ) );

        add_action( 'admin_init', array( $this, 'install_actions' ) );
        add_action( 'admin_init', array( $this, 'check_version' ), 5 );
    }

    /**
     * check_version function.
     *
     * @access public
     * @return void
     */
    public function check_version() {
        if (
            ! defined( 'IFRAME_REQUEST' ) &&
            ( get_option( 'propertyhive_demo_data_version' ) != PHDD()->version || get_option( 'propertyhive_demo_data_db_version' ) != PHDD()->version )
        ) {
            $this->install();
        }
    }

    /**
     * Install actions
     */
    public function install_actions() {

    }

    /**
     * Install Property Hive Demo Data Add-On
     */
    public function install() {

        $this->create_options();
        $this->create_cron();

        $current_version = get_option( 'propertyhive_demo_data_version', null );
        $current_db_version = get_option( 'propertyhive_demo_data_db_version', null );

        update_option( 'propertyhive_demo_data_db_version', PHDD()->version );

        // Update version
        update_option( 'propertyhive_demo_data_version', PHDD()->version );
    }

    /**
     * Deactivate Property Hive Demo Data Add-On
     */
    public function deactivate() {

    }

    /**
     * Uninstall Property Hive Demo Data Add-On
     */
    public function uninstall() {

    }

    /**
     * Default options
     *
     * Sets up the default options used on the settings page
     *
     * @access public
     */
    public function create_options() {

    }

    /**
     * Creates the scheduled event to run hourly
     *
     * @access public
     */
    public function create_cron() {

    }
}

endif;

return new PH_Demo_Data_Install();