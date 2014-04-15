<?php
/**
* A class that will setup and run the MC Integration.
*
**/

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

      // Add the MC Integration setting page to the EE settings list
      add_filter( 'FHEE__EE_Admin_Page_Loader___get_installed_pages__installed_refs', array($this, 'ee_mci_add_to_settlist'), 5, 1 );
      // Admin and View related
      add_action( 'AHEE__EE_Admin_Page___initialize_admin_page__before_initialization', array($this, 'ee_mci_admin_page_settings'), 15 );
      // Link the scripts and styles
      add_action( 'admin_enqueue_scripts', array($this, 'ee_mci_link_scripts_styles') );
      // Hook into the EE _process_attendee_information
      add_action( 'AHEE__EE_Single_Page_Checkout__process_attendee_information__end', array($this, 'espresso_mailchimp_submit_to_mc'), 10, 2 );
      // Add 'Settings' link
      add_filter('plugin_action_links', array($this, 'filter_espresso_mailchimp_plugin_settings'), 10, 2);

      // Ajax for MailChimp groups refresh
      add_action('wp_ajax_espresso_mailchimp_upgate_groups', array($this, 'espresso_mailchimp_upgate_groups'));
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
      wp_enqueue_style('espresso_mailchimp_gen_styles', ESPRESSO_MAILCHIMP_URL . "assets/css/ee_mailchimp_styles.css", false);
      wp_enqueue_script('espresso_mailchimp_base_scripts', ESPRESSO_MAILCHIMP_URL . 'assets/js/ee-mailchimp-base-scripts.js', false);
      do_action('AHEE__EE_MCI_Setup__ee_mci_link_scripts_styles__end');
   }

   /**
    * Add the MC Integration setting page to the EE4 settings list
    *
    * @param array $installed_refs  EE settings pages list
    * @return array  list of EE admin pages
    */
   function ee_mci_add_to_settlist( $installed_refs ) {
      require_once( ESPRESSO_MAILCHIMP_DIR . 'includes/espresso_mailchimp_settings/Espresso_Mailchimp_Settings_Admin_Page_Init.class.php' );
      $installed_refs[] = 'espresso_mailchimp_settings'; 
      return $installed_refs;
   }

   /**
    * Select the settings source files
    * 
    * @access private
    * @return void
    */
   function ee_mci_admin_page_settings() {
      require_once( ESPRESSO_MAILCHIMP_DIR . 'includes/EE_MCI_Settings.class.php' );
      $settingsObjName = 'EE_MCI_Settings';
      $settingsObject = new $settingsObjName($this);
      return $settingsObject;
   }

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

      // MailChimp / Event Relationship Table
      $mailchimp_event_rel = "CREATE TABLE IF NOT EXISTS {$wpdb->ee_mci_mailchimp_event_rel} (
         id int(11) NOT NULL AUTO_INCREMENT,
         event_id INT(11) DEFAULT NULL,
         mailchimp_list_id VARCHAR(75) DEFAULT NULL,
         mailchimp_group_id VARCHAR(255) DEFAULT NULL,
         PRIMARY KEY (id)
      ) $charset_collate;";
      $mailchimp_event_rel = apply_filters('FHEE__EE_MCI_Setup__ee_mci_db_setup__mailchimp_event_rel', $mailchimp_event_rel);
      dbDelta($mailchimp_event_rel);

      // Event Merge fields to the Questions Table
      $mailchimp_qfields_rel = "CREATE TABLE IF NOT EXISTS {$wpdb->ee_mci_mailchimp_question_field_rel} (
         id int(11) NOT NULL AUTO_INCREMENT,
         event_id INT(11) DEFAULT NULL,
         field_question_rel VARCHAR(255) DEFAULT NULL,
         PRIMARY KEY (id)
      ) $charset_collate;";
      $mailchimp_qfields_rel = apply_filters('FHEE__EE_MCI_Setup__ee_mci_db_setup__mailchimp_question_field_rel', $mailchimp_qfields_rel);
      dbDelta($mailchimp_qfields_rel);
      do_action('AHEE__EE_MCI_Setup__ee_mci_db_setup__end');
   }

}

?>