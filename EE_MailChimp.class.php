<?php if ( ! defined( 'EVENT_ESPRESSO_VERSION' ) ) { exit('NO direct script access allowed'); }

define( 'ESPRESSO_MAILCHIMP_URL', plugin_dir_url( ESPRESSO_MAILCHIMP_MAIN_FILE ) );
define( 'ESPRESSO_MAILCHIMP_ADMIN_URL', get_admin_url() );
define( 'ESPRESSO_MAILCHIMP_ADMIN_DIR', ESPRESSO_MAILCHIMP_DIR . 'admin' . DS );
define( 'ESPRESSO_MAILCHIMP_DB_DIR', ESPRESSO_MAILCHIMP_DIR . 'db' . DS );
define( 'ESPRESSO_MAILCHIMP_DMS_PATH', ESPRESSO_MAILCHIMP_DB_DIR . 'migration_scripts' . DS );
define( 'ESPRESSO_MAILCHIMP_MODELS_PATH', ESPRESSO_MAILCHIMP_DB_DIR . 'models' . DS );
define( 'ESPRESSO_MAILCHIMP_CLASSES_PATH', ESPRESSO_MAILCHIMP_DB_DIR . 'classes' . DS );
define( 'ESPRESSO_MAILCHIMP_SETTINGS_PAGE_SLUG', 'mailchimp' );
define( 'ESPRESSO_MAILCHIMP_ACTIVE_OPTION', 'ee_mailchimp_active' );
define( 'ESPRESSO_MAILCHIMP_API_OPTIONS', 'ee_mailchimp_user_settings' );

/**
 * Class  EE_MailChimp
 *
 * @package         Event Espresso
 * @subpackage      ee4-mailchimp
 */
class EE_MailChimp extends EE_Addon {

    /**
     *  For registering the activation hook.
     */
    const activation_indicator_option_name = 'ee4_mailchimp_activation';

	/**
	 * Class constructor
	 *
	 * @access public
	 * @return \EE_MailChimp
	 */
    public function __construct() {
        
    }

    /**
     * Register our add-on in EE.
     *
     * @access public
     * @return void
     */
    public static function register_addon() {

        // Load MailChimp API:
        require_once( ESPRESSO_MAILCHIMP_DIR . 'includes/MailChimp.class.php' );

        // Register our add-on via Plugin API.
        EE_Register_Addon::register(
			'MailChimp',
			array(
				'version' => ESPRESSO_MAILCHIMP_VERSION,
				'class_name' => 'EE_MailChimp',
				'min_core_version' => '4.3.0',
				'main_file_path' => ESPRESSO_MAILCHIMP_MAIN_FILE,
				'admin_path' => ESPRESSO_MAILCHIMP_ADMIN_DIR . 'mailchimp' . DS,
				'admin_callback' => 'additional_mailchimp_admin_hooks',
				'config_class' => 'EE_Mailchimp_Config',
				'autoloader_paths' => array(
					'EE_MCI_Controller' => ESPRESSO_MAILCHIMP_DIR . 'includes/EE_MCI_Controller.class.php',
					'Mailchimp_Admin_Page' => ESPRESSO_MAILCHIMP_ADMIN_DIR . 'mailchimp' . DS . 'Mailchimp_Admin_Page.core.php',
					'Mailchimp_Admin_Page_Init' => ESPRESSO_MAILCHIMP_ADMIN_DIR . 'mailchimp' . DS . 'Mailchimp_Admin_Page_Init.core.php',
                    'EE_Mailchimp_Config' => ESPRESSO_MAILCHIMP_DIR . 'EE_Mailchimp_Config.php',
				),
				'dms_paths' => array( ESPRESSO_MAILCHIMP_DMS_PATH ),
				'module_paths' => array(
					ESPRESSO_MAILCHIMP_DIR . 'EED_Mailchimp.module.php'
				),
				// if plugin update engine is being used for auto-updates. not needed if PUE is not being used.
				'pue_options'			=> array(
					'pue_plugin_slug' => 'ee4-mailchimp',
					'checkPeriod' => '24',
					'use_wp_update' => FALSE
				)
			)
		);

        // Register db models.
		if( ! did_action( 'activate_plugin' ) ){
			EE_Register_Model::register('MailChimp', array( 'model_paths' => array(ESPRESSO_MAILCHIMP_MODELS_PATH), 'class_paths' => array(ESPRESSO_MAILCHIMP_CLASSES_PATH) ));
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
            add_filter( 'plugin_action_links', array('EE_MailChimp', 'espresso_mailchimp_plugin_settings'), 10, 2 );
        }
    }

    /**
     * Add a settings link to the Plugins page.
     *
     * @param array $links  List of existing links.
     * @param string $file  Main plugins file name.
     * @return array  Updated Links list
     */
    public static function espresso_mailchimp_plugin_settings( $links, $file ) {
 		if ( $file == ESPRESSO_MAILCHIMP_BASE_NAME ) {
			// before other links
			array_unshift( $links, '<a href="admin.php?page='. ESPRESSO_MAILCHIMP_SETTINGS_PAGE_SLUG .'">' . __('Settings', 'event-espresso') . '</a>' );
		}
        return $links;
	}

	/**
	 * Overrides parent so we return a name that integrates properly
	 * with the data migration scripts
	 * @return string
	 */
	public function name(){
		return 'MailChimp';
	}



}