<?php if ( ! defined( 'EVENT_ESPRESSO_VERSION' )) { exit('NO direct script access allowed'); }
/*
 * Event Espresso
 *
 * Event Registration and Management Plugin for WordPress
 *
 * @ package        Event Espresso
 * @ author         Event Espresso
 * @ copyright (c)  2008-2014 Event Espresso  All Rights Reserved.
 * @ license        http://eventespresso.com/support/terms-conditions/   * see Plugin Licensing *
 * @ link           http://www.eventespresso.com
 * @ version        EE4
 *
 * ------------------------------------------------------------------------
 */
/**
 * Class  EE_MailChimp_Integration
 *
 * @package         Event Espresso
 * @subpackage      ee4-mailchimp-integration
 *
 * ------------------------------------------------------------------------
 */

class EE_MailChimp_Integration extends EE_Addon {

    /**
     *  For registering the activation hook.
     */
    const activation_indicator_option_name = 'ee4_mailchimp_integration_activation';

    /**
     * Class constructor
     * 
     * @access public
     * @return void
     */
    public function __construct() {
        // Register the activation/deactivation hooks.
        register_activation_hook( __FILE__, array($this, 'set_activation_mci_options') );
        register_deactivation_hook( __FILE__, array($this, 'reset_mci_options') );
    }

    /**
     * Register our add-on in EE.
     * 
     * @access public
     * @return void
     */
    public static function register_addon() {
        // Define some plugin info/constants
        define( 'ESPRESSO_MAILCHIMP_DIR', plugin_dir_path(__FILE__) );
        define( 'ESPRESSO_MAILCHIMP_URL', plugin_dir_url(__FILE__) );
        define( 'ESPRESSO_MAILCHIMP_ADMIN_URL', get_admin_url() );
        define( 'ESPRESSO_MAILCHIMP_ADMIN_DIR', ESPRESSO_MAILCHIMP_DIR . 'admin' . DS );
        define( 'ESPRESSO_MAILCHIMP_SETTINGS_PAGE_SLUG', 'mailchimp_integration' );
        define( 'ESPRESSO_MAILCHIMP_INTEGRATION_ACTIVE_OPTION', 'ee_mailchimp_integration_active' );
        define( 'ESPRESSO_MAILCHIMP_API_OPTIONS', 'ee_mailchimp_integration_user_settings' );
        // Load MailChimp API:
        require_once( ESPRESSO_MAILCHIMP_DIR . 'includes/MailChimp.class.php' );
        require_once( ESPRESSO_MAILCHIMP_ADMIN_DIR . 'EE_MCI_Setup.class.php' );

        // Register our add-on via Plugin API.
        EE_Register_Addon::register('MailChimp Integration', array(
            'addon_name' => 'MailChimp Integration',
            'version' => ESPRESSO_MAILCHIMP_VERION,
            'min_core_version' => '4.2.0',
            'base_path' => ESPRESSO_MAILCHIMP_DIR,
            'admin_path' => ESPRESSO_MAILCHIMP_ADMIN_DIR . 'mailchimp_integration' . DS,
            'admin_callback' => 'additional_mailchimp_admin_hooks',
            'config_class' => 'EE_MC_Integration_Config',
            'autoloader_paths' => array(
                'EE_MCI_Controller' => ESPRESSO_MAILCHIMP_DIR . 'includes/EE_MCI_Controller.class.php',
                'Mailchimp_Integration_Admin_Page' => ESPRESSO_MAILCHIMP_ADMIN_DIR . 'mailchimp_integration' . DS . 'Mailchimp_Integration_Admin_Page.core.php',
                'Mailchimp_Integration_Admin_Page_Init' => ESPRESSO_MAILCHIMP_ADMIN_DIR . 'mailchimp_integration' . DS . 'Mailchimp_Integration_Admin_Page_Init.core.php',
            ),
            'dms_paths' => array( ESPRESSO_MAILCHIMP_DMS_PATH ),
            'module_paths' => array( ESPRESSO_MAILCHIMP_DIR . 'EED_MailChimp_Integration.module.php' ),
            'shortcode_paths' => array( ESPRESSO_MAILCHIMP_DIR . 'EES_MailChimp_Integration.shortcode.php' ),
            'widget_paths' => array( ESPRESSO_MAILCHIMP_DIR . 'EEW_MailChimp_Integration.widget.php' ),
        ));

        // Run the integration 'by hand' while it currently does not Yet facilitate adding models.
        $mci_setup = new EE_MCI_Setup();
        self::load_pue_update();
    }

    /**
     * get_db_update_option_name.
     *
     * @access public
     * @return string
     */
    public function get_db_update_option_name() {
        return EE_MailChimp_Integration::activation_indicator_option_name;
    }

    /**
     * Some activation options setup.
     *
     * @access public
     */
    public function set_activation_mci_options() {
        update_option( EE_MailChimp_Integration::activation_indicator_option_name, TRUE );
        add_option(ESPRESSO_MAILCHIMP_INTEGRATION_ACTIVE_OPTION, 'false', '', 'yes');
        update_option(ESPRESSO_MAILCHIMP_INTEGRATION_ACTIVE_OPTION, 'false');
        do_action('AHEE__EE_MailChimp_Integration__set_activation_mci_options__post_activation');
    }

    /**
     * Reset some options on deactivation.
     *
     * @access public
     */
    public function reset_mci_options() {
        delete_option(ESPRESSO_MAILCHIMP_API_OPTIONS);
        update_option(ESPRESSO_MAILCHIMP_INTEGRATION_ACTIVE_OPTION, 'flase');
        do_action('AHEE__EE_MailChimp_Integration__reset_mci_options__post_deactivation');
    }

    /**
     * Check for migration scripts on new install.
     *
     * @access public
     * @return mixed
     */
    public function new_install() {
        // If core is also active, then get core to check for migration scripts
        // ..and set maintenance mode is necessary
        if ( get_option(EE_MailChimp_Integration::activation_indicator_option_name) ) {
            EE_Maintenance_Mode::instance()->set_maintenance_mode_if_db_old();
            delete_option( EE_MailChimp_Integration::activation_indicator_option_name );
        }
    }

    /**
     * Check for migration scripts on upgrade.
     *
     * @access public
     * @return mixed
     */
    public function upgrade() {
        // If core is also active, then get core to check for migration scripts
        // ..and set maintenance mode is necessary
        if ( get_option(EE_MailChimp_Integration::activation_indicator_option_name) ) {
            EE_Maintenance_Mode::instance()->set_maintenance_mode_if_db_old();
            delete_option( EE_MailChimp_Integration::activation_indicator_option_name );
        }
    }

    /**
     *  Additional admin hooks.
     *
     * @access public
     * @return void
     */
    public static function additional_mailchimp_admin_hooks() {
        // Is admin and not in M-Mode ?
        if ( is_admin() && ! EE_Maintenance_Mode::instance()->level() ) {
            add_filter( 'plugin_action_links', array($this, 'espresso_mailchimp_plugin_settings'), 10, 2 );
        }
    }

    /**
     *  PUE Update notifications.
     *
     * @access public
     * @return void
     */
    public static function load_pue_update() {
        if ( ! defined('EE_THIRD_PARTY') ) {
            return;
        }
        if ( is_readable(EE_THIRD_PARTY . 'pue/pue-client.php') ) {
            // Include the file
            require_once(EE_THIRD_PARTY . 'pue/pue-client.php');
            // EE Settings requirements
            require_once(EE_CORE . 'EE_Config.core.php');
            require_once(EE_CORE . 'EE_Network_Config.core.php');

            $settings = EE_Network_Config::instance()->get_config();
            $api_key = isset($settings->core->site_license_key) ? $settings->core->site_license_key : '';
            $host_server_url = 'http://eventespresso.com';
            $plugin_slug = array(
                'premium' => array('p' => 'ee4-mailchimp-integration'),
                'prerelease' => array('b' => 'ee4-mailchimp-integration-pr')
            );
            $options = array(
                'apikey' => $api_key,
                'lang_domain' => 'event_espresso',
                'checkPeriod' => '24',
                'option_key' => 'site_license_key',
                'options_page_slug' => 'event_espresso',
                'plugin_basename' => ESPRESSO_MAILCHIMP_BASE_NAME,
                'use_wp_update' => FALSE, //if TRUE then you want FREE versions of the plugin to be updated from WP
            );
            do_action('AHEE__EE_MailChimp_Integration__load_pue_update__pre_update_check');
            $check_for_updates = new PluginUpdateEngineChecker($host_server_url, $plugin_slug, $options); //initiate the class and start the plugin update engine!
        }
    }

    /**
     * Add a settings link to the Plugins page.
     *
     * @param array $links  List of existing links.
     * @param string $file  Main plugins file name.
     * @return array  Updated Links list
     */
    public function espresso_mailchimp_plugin_settings( $links, $file ) {
        static $this_plugin;
        if ( ! $this_plugin ) {
            $this_plugin = ESPRESSO_MAILCHIMP_BASE_NAME;
        }
        if ( $file == $this_plugin ) {
            $mci_settings = '<a href="' . ESPRESSO_MAILCHIMP_ADMIN_URL . 'admin.php?page='. ESPRESSO_MAILCHIMP_SETTINGS_PAGE_SLUG .'">Settings</a>';
            array_unshift($links, $mci_settings);
        }
        return $links;
    }

} 