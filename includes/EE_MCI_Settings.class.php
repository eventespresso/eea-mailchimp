<?php
/**
* Admin/Backend Setup.
*
**/

class EE_MCI_Settings {

   /**
    * Class constructor.
    * 
    * @access public
    * @return void
    */
   public function __construct($callerObject) {
      $this->init_mci_settings_page();
   }

   private function init_mci_settings_page() {
      do_action( 'AHEE__EE_MCI_Settings__init_mci_settings_page__start' );
      add_filter( 'FHEE__EE_Admin_Page_Init___initialize_admin_page__path_to_file__espresso_mailchimp_settings_Espresso_Mailchimp_Settings_Admin_Page', array($this, 'espresso_mailchimp_settings_admin_page_url') );

      // 'MailChimp List Integration' option
      add_action( 'add_meta_boxes', array($this, 'ee_mci_list_integration_metabox') );
      add_action( 'save_post', array($this, 'espresso_mailchimp_save_event') );
      do_action( 'AHEE__EE_MCI_Settings__init_mci_settings_page__end' );
   }

   /**
    * Set path to the EE MailChimp settings page logic (core) class.
    * 
    * @access public
    * @return string  path to the EE MailChimp settings page logic
    */
   public function espresso_mailchimp_settings_admin_page_url() {
      return ESPRESSO_MAILCHIMP_DIR . 'includes/espresso_mailchimp_settings/Espresso_Mailchimp_Settings_Admin_Page.core.php';
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
    * Save the meta when the post is saved.
    *
    * @param int $event_id The ID of the event being saved.
    * @return void
    */
   function espresso_mailchimp_save_event( $event_id ) {
      // Nonce checks.
      $is_ok = EE_MCI_Settings::espresso_mailchimp_authorization_checks('espresso_mailchimp_list_integration_box', 'espresso_mailchimp_list_integration_box_nonce');
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
}

?>