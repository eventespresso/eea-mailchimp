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
 * Class  EED_MailChimp_Integration
 *
 * @package         Event Espresso
 * @subpackage      ee4-mailchimp-integration
 *
 * ------------------------------------------------------------------------
 */

class EED_MailChimp_Integration extends EED_Module {

    /**
     * For hooking into EE Core, other modules, etc.
     *
     * @access public
     * @return void
     */
    public static function set_hooks() {
        EE_Config::register_route( 'mailchimp', 'EED_MailChimp_Integration', 'run' );

        // Hook into the EE _process_attendee_information
        add_action( 'AHEE__EE_Single_Page_Checkout__process_attendee_information__end', array('EED_MailChimp_Integration', 'espresso_mailchimp_submit_to_mc'), 10, 2 );
        // 'MailChimp List Integration' option
        add_action( 'add_meta_boxes', array('EED_MailChimp_Integration', 'espresso_mailchimp_list_integration_metabox') );
        add_action( 'save_post', array('EED_MailChimp_Integration', 'espresso_mailchimp_save_event') );
    }

    /**
     * For hooking into EE Admin Core and other modules, etc.
     *
     * @access public
     * @return void
     */
    public static function set_hooks_admin() {
        // Ajax for MailChimp groups refresh
        add_action( 'wp_ajax_espresso_mailchimp_upgate_groups', array('EED_MailChimp_Integration', 'espresso_mailchimp_upgate_groups') );
        // Ajax for MailChimp list fields refresh
        add_action( 'wp_ajax_espresso_mailchimp_upgate_list_fields', array('EED_MailChimp_Integration', 'espresso_mailchimp_upgate_list_fields') );
    }

    /**
     * Set config.
     *
     * @access protected
     * @return EE_Calendar_Config
     */
    protected static function _set_config(){
        return EED_MailChimp_Integration::instance()->set_config( 'addons', 'EED_MailChimp_Integration', 'EE_MailChimp_Config' );
    }

    /**
     * Get config.
     *
     * @access protected
     * @return EE_Calendar_Config
     */
    protected static function _get_config(){
        $config = EED_MailChimp_Integration::instance()->get_config( 'addons', 'EED_MailChimp_Integration', 'EE_MailChimp_Config' );
        return $config instanceof EE_MailChimp_Config ? $config : EED_MailChimp_Integration::_set_config();
    }

    /**
     * Run initial module setup.
     *
     * @access public
     * @param WP  $WP
     * @return void
     */
    public function run( $WP ) {
        EED_MailChimp_Integration::_set_config();
        add_action( 'wp_enqueue_scripts', array( $this, 'mailchimp_link_scripts_styles' ));
    }

    /**
     * Load the MCI scripts and styles.
     *
     * @access public
     * @return void
     */
    public function mailchimp_link_scripts_styles() {
        $mci_ver = ESPRESSO_MAILCHIMP_VERION;
        wp_enqueue_style('espresso_mailchimp_gen_styles', ESPRESSO_MAILCHIMP_URL . "assets/css/ee_mailchimp_styles.css", false, $mci_ver);
        wp_enqueue_script('espresso_mailchimp_base_scripts', ESPRESSO_MAILCHIMP_URL . 'assets/js/ee-mailchimp-base-scripts.js', false, $mci_ver);
        do_action('AHEE__EED_MailChimp_Integration__mailchimp_link_scripts_styles__end');
    }

    /**
     * An ajax to refresh the list of groups of the MailChimp List selected in the event.
     * 
     * @return void
     */
    public static function espresso_mailchimp_upgate_groups() {
        $mci_controller = new EE_MCI_Controller();
        ob_start();
        $mci_data = $_POST['mci_data'];
        $mci_controller->mci_list_mailchimp_groups($mci_data['event_id'], $mci_data['list_id']);
        $response = ob_get_contents();
        ob_end_clean();
        echo $response;
        exit;
    }

    /**
     * An ajax to refresh the  selected MailChimp List's merge fields.
     * 
     * @return void
     */
    public static function espresso_mailchimp_upgate_list_fields() {
        $mci_controller = new EE_MCI_Controller();
        ob_start();
        $mci_data = $_POST['mci_data'];
        $mci_controller->mci_list_mailchimp_fields($mci_data['event_id'], $mci_data['list_id']);
        $response = ob_get_contents();
        ob_end_clean();
        echo $response;
        exit;
    }

    /**
     * Submit new attendee information to MailChimp (if any MailChimp list to submit to selected for the current event).
     *
     * @param EED_Single_Page_Checkout $spc_obj  Single Page Checkout (SPCO) Object.
     * @param array $valid_data  Valid data from the Attendee Information Registration form.
     * @return void
     */
    public static function espresso_mailchimp_submit_to_mc( $spc_obj, $valid_data ) {
        if ( get_option(ESPRESSO_MAILCHIMP_INTEGRATION_ACTIVE_OPTION) == 'true' ) {
            $mci_controller = new EE_MCI_Controller();
            $mci_controller->mci_submit_to_mailchimp( $spc_obj, $valid_data );
        }
    }

    /**
     * Save the meta when the post is saved.
     *
     * @param int $event_id The ID of the event being saved.
     * @return void
     */
    public static function espresso_mailchimp_save_event( $event_id ) {
        // Nonce checks.
        $is_ok = EED_MailChimp_Integration::espresso_mailchimp_authorization_checks('espresso_mailchimp_list_integration_box', 'espresso_mailchimp_list_integration_box_nonce');
        // Auto-save? ...do nothing.
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) 
            return $event_id;
        if ( ! $is_ok )
            return $event_id;
        // Got here so let's save the data.
        $mci_controller = new EE_MCI_Controller();
        $mci_controller->mci_save_metabox_contents($event_id);
    }
   
    /**
     * Add 'MailChimp List Integration' option (metabox) to events admin (add/edit) page (if the API Key is valid).
     * 
     * @access public
     * @param string $post_type  Type of the post.
     * @return void
     */
    public static function espresso_mailchimp_list_integration_metabox( $post_type ) {
        // Is integration active and is espresso event page.
        if ( ($post_type == 'espresso_events') && (get_option(ESPRESSO_MAILCHIMP_INTEGRATION_ACTIVE_OPTION) == 'true') ) {
            add_meta_box( 'espresso_mailchimp_list_integration', __( 'MailChimp List Integration', 'event_espresso' ), array( 'EED_MailChimp_Integration', 'espresso_mailchimp_render_box_content' ), $post_type, 'side', 'default' );
        }
    }

    /**
     * Get 'MailChimp List Integration' metabox contents.
     * 
     * @access public
     * @param WP_Post $event  The post object.
     * @return void
     */
    public static function espresso_mailchimp_render_box_content( $event ) {
        $mci_controller = new EE_MCI_Controller();
        // Add an nonce field.
        wp_nonce_field( 'espresso_mailchimp_list_integration_box', 'espresso_mailchimp_list_integration_box_nonce' );
        $mci_controller->mci_set_metabox_contents($event);
    }

    /**
    * Do authorization checks on save_post.
    *
    * @access public
    * @param string $nonce_action  Nonce action.
    * @param string $nonce_name  Nonce to verify.
    * @return bool  Is authorization OK.
    */
    public static function espresso_mailchimp_authorization_checks( $nonce_action, $nonce_name ) {
        // Check if our nonce is set.
        if ( ! isset( $_POST[$nonce_name] ) )
            return false;
        $nonce = $_POST[$nonce_name];
        // Verify that the nonce is valid.
        if ( ! wp_verify_nonce( $nonce, $nonce_action ) )
         return false;
        return true;
    }

    /**
     *  Override other methods
     */
    public function __set($a, $b) { return FALSE; }
    public function __get($a) { return FALSE; }
    public function __isset($a) { return FALSE; }
    public function __unset($a) { return FALSE; }
    public function __clone() { return FALSE; }
    public function __wakeup() { return FALSE; }
    public function __destruct() { return FALSE; }
}