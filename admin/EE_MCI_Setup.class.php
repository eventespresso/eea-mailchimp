<?php
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
 * Class  EE_MCI_Setup - class that will setup and run the MC Integration.
 *
 * @package         Event Espresso
 * @subpackage      ee4-mailchimp-integration
 *
 * ------------------------------------------------------------------------
 */


class EE_MCI_Setup {

   /**
    * Was the setup called by the plugin activation
    */
   private static $_is_activation = false;

   /**
    * Instance of the EE_MCI_Setup object
    */
   private static $_instance = NULL;

   /**
    * @singleton method
    * @access public
    * @return EE_MCI_Setup
    */
   public static function instance( $is_activation = false ) {
      self::$_is_activation = $is_activation;
      if ( self::$_instance === NULL || ! is_object( self::$_instance ) || ! ( self::$_instance instanceof EE_MCI_Setup ) ) {
         self::$_instance = new self();
      }
      return self::$_instance;
   }

   /**
    * Class constructor
    *
    * @access private
    * @return void
    */
   public function __construct() {
      if ( self::$_is_activation ) {
         $this->ee_mci_db_setup();
      }

      // Link the scripts and styles
      add_action( 'admin_enqueue_scripts', array($this, 'ee_mci_link_scripts_styles') );
      // Hook into the EE _process_attendee_information
      add_action( 'AHEE__EE_Single_Page_Checkout__process_attendee_information__end', array($this, 'espresso_mailchimp_submit_to_mc'), 10, 2 );
      // Add 'Settings' link
      add_filter( 'plugin_action_links', array($this, 'filter_espresso_mailchimp_plugin_settings'), 10, 2 );
      // Ajax for MailChimp groups refresh
      add_action( 'wp_ajax_espresso_mailchimp_upgate_groups', array($this, 'espresso_mailchimp_upgate_groups') );
      // Ajax for MailChimp list fields refresh
      add_action( 'wp_ajax_espresso_mailchimp_upgate_list_fields', array($this, 'espresso_mailchimp_upgate_list_fields') );

      // 'MailChimp List Integration' option
      add_action( 'add_meta_boxes', array($this, 'ee_mci_list_integration_metabox') );
      add_action( 'save_post', array($this, 'espresso_mailchimp_save_event') );
   }

   /**
    * Add Settings link in the plugins overview page.
    *
    * @param array $links  List of existing links.
    * @param string $file  Main plugins file name.
    * @return array  Updated Links list
    */
   function filter_espresso_mailchimp_plugin_settings( $links, $file ) {
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

   /**
    * Submit new attendee information to MailChimp (if any MailChimp list to submit to selected for the current event).
    *
    * @param EED_Single_Page_Checkout $spc_obj  Single Page Checkout (SPCO) Object.
    * @param array $valid_data  Valid data from the Attendee Information Registration form.
    * @return void
    */
   function espresso_mailchimp_submit_to_mc( $spc_obj, $valid_data ) {
      if ( get_option(ESPRESSO_MAILCHIMP_INTEGRATION_ACTIVE_OPTION) == 'true' ) {
         $mci_controller = new EE_MCI_Controller();
         $mci_controller->mci_submit_to_mailchimp( $spc_obj, $valid_data );
      }
   }

   /**
    * Link the scripts and styles
    *
    * @return void
    */
   function ee_mci_link_scripts_styles() {
      $mci_ver = ESPRESSO_MAILCHIMP_VERSION;
      wp_enqueue_style('espresso_mailchimp_gen_styles', ESPRESSO_MAILCHIMP_URL . "assets/css/ee_mailchimp_styles.css", false, $mci_ver);
      wp_enqueue_script('espresso_mailchimp_base_scripts', ESPRESSO_MAILCHIMP_URL . 'assets/js/ee-mailchimp-base-scripts.js', false, $mci_ver);
      do_action('AHEE__EE_MCI_Setup__ee_mci_link_scripts_styles__end');
   }

   /**
    * Save the meta when the post is saved.
    *
    * @param int $event_id The ID of the event being saved.
    * @return void
    */
   function espresso_mailchimp_save_event( $event_id ) {
      // Nonce checks.
      $is_ok = EE_MCI_Setup::espresso_mailchimp_authorization_checks('espresso_mailchimp_list_integration_box', 'espresso_mailchimp_list_integration_box_nonce');
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
   public function ee_mci_list_integration_metabox( $post_type ) {
      // Is integration active and is espresso event page.
      if ( ($post_type == 'espresso_events') && (get_option(ESPRESSO_MAILCHIMP_INTEGRATION_ACTIVE_OPTION) == 'true') ) {
         add_meta_box( 'espresso_mailchimp_list_integration', __( 'MailChimp List Integration', 'event_espresso' ), array( $this, 'espresso_mailchimp_render_box_content' ), $post_type, 'side', 'default' );
      }
   }

   /**
    * Get 'MailChimp List Integration' metabox contents.
    *
    * @access public
    * @param WP_Post $event  The post object.
    * @return void
    */
   function espresso_mailchimp_render_box_content( $event ) {
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
    * An ajax to refresh the list of groups of the MailChimp List selected in the event.
    *
    * @return void
    */
   function espresso_mailchimp_upgate_groups() {
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
   function espresso_mailchimp_upgate_list_fields() {
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
    * Set Up the tables in database
    *
    * @access private
    * @return void
    */
   private function ee_mci_db_setup() {
      global $wpdb, $charset_collate;
      require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
      // Setup the db
      // MailChimp / Attendee relationship table
      $mailchimp_attendee_rel = "CREATE TABLE IF NOT EXISTS {$wpdb->ee_mci_mailchimp_attendee_rel} (
         id int(11) NOT NULL AUTO_INCREMENT,
         event_id INT(11) DEFAULT NULL,
         attendee_id INT(11) DEFAULT NULL,
         mailchimp_list_id VARCHAR(75) DEFAULT NULL,
         PRIMARY KEY (id)
      ) $charset_collate;";
      $mailchimp_attendee_rel = apply_filters('FHEE__EE_MCI_Setup__ee_mci_db_setup__mailchimp_attendee_rel', $mailchimp_attendee_rel);
      dbDelta($mailchimp_attendee_rel);

      if ( $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->ee_mci_mailchimp_event_rel}'" ) == $wpdb->ee_mci_mailchimp_event_rel ) {
        $wpdb->query("ALTER TABLE {$wpdb->ee_mci_mailchimp_event_rel} MODIFY mailchimp_group_id TEXT;");
      }
      // MailChimp / Event Relationship Table
      $mailchimp_event_rel = "CREATE TABLE IF NOT EXISTS {$wpdb->ee_mci_mailchimp_event_rel} (
         id int(11) NOT NULL AUTO_INCREMENT,
         event_id INT(11) DEFAULT NULL,
         mailchimp_list_id VARCHAR(75) DEFAULT NULL,
         mailchimp_group_id TEXT DEFAULT NULL,
         PRIMARY KEY (id)
      ) $charset_collate;";
      $mailchimp_event_rel = apply_filters('FHEE__EE_MCI_Setup__ee_mci_db_setup__mailchimp_event_rel', $mailchimp_event_rel);
      dbDelta($mailchimp_event_rel);

      // Event Merge fields to the Questions Table
      $mailchimp_qfields_rel = "CREATE TABLE IF NOT EXISTS {$wpdb->ee_mci_mailchimp_question_field_rel} (
         id int(11) NOT NULL AUTO_INCREMENT,
         event_id INT(11) DEFAULT NULL,
         field_question_rel TEXT DEFAULT NULL,
         PRIMARY KEY (id)
      ) $charset_collate;";
      $mailchimp_qfields_rel = apply_filters('FHEE__EE_MCI_Setup__ee_mci_db_setup__mailchimp_question_field_rel', $mailchimp_qfields_rel);
      dbDelta($mailchimp_qfields_rel);
      do_action('AHEE__EE_MCI_Setup__ee_mci_db_setup__end');
   }

}

?>