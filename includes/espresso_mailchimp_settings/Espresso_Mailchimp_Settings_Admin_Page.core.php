<?php
/**
* This contains the logic for setting up the Custom MailChimp Settings Page.
*
**/

class Espresso_Mailchimp_Settings_Admin_Page extends EE_Admin_Page {

   public function __construct( $routing = TRUE ) {
      parent::__construct( $routing );
   }

   protected function _init_page_props() {
      $this->page_slug = ESPRESSO_MAILCHIMP_SETTINGS_PAGE_SLUG;
      $this->page_label = EE_MAILCHIMP_SETT_LABEL;
   }

   protected function _ajax_hooks() {
      //todo: all hooks for ajax goes here.
   }

   protected function _define_page_props() {
      $this->_admin_base_url = EE_MAILCHIMP_SETT_ADMIN_URL;
      $this->_admin_page_title = $this->page_label;
      $this->_labels = array();
   }

   protected function _set_page_routes() {
      $this->_page_routes = array(
         'default' => '_mailchimp_page_init',
         );
   }

   protected function _set_page_config() {
      $this->_page_config = array(
         'default' => array(
            'nav' => array(
               'label' => __('Main Settings', 'event_espresso'),
               'order' => 10
               ),
            'metaboxes' => array('_espresso_news_post_box', '_espresso_links_post_box', '_espresso_sponsors_post_box'),
            )
         );
   }

   protected function _mailchimp_page_init() {
      $mci_controller = new EE_MCI_Controller();
      $mcapi_settings = $template_data = array();
      $template_data['mc_api_key_ok'] = $template_data['mc_api_key_error'] = 'none';
      $mcapi_settings['api_key'] = $template_data['mailchimp_key_error'] = '';
      if ( get_option(ESPRESSO_MAILCHIMP_API_OPTIONS) ) {
         $mcapi_settings = get_option(ESPRESSO_MAILCHIMP_API_OPTIONS);
      }
      if ( isset($_POST['save_key_button']) ) {
         $mcapi_settings['api_key'] = ( isset($_POST['mailchimp_api_key']) ) ? $_POST['mailchimp_api_key'] : '';
         update_option(ESPRESSO_MAILCHIMP_API_OPTIONS, $mcapi_settings);
      }
      $mcapi_settings = get_option(ESPRESSO_MAILCHIMP_API_OPTIONS);
      // Validate the MailChimp API Key
      if ( $mcapi_settings && strlen($mcapi_settings['api_key']) > 1 ) {
         $key_valid = $mci_controller->mci_is_api_key_valid();
         if ( $key_valid ) {
            $template_data['mc_api_key_ok'] = 'inline';
            update_option(ESPRESSO_MAILCHIMP_INTEGRATION_ACTIVE_OPTION, 'true');
         } else {
            $template_data['mc_api_key_error'] = 'inline';
            $mcapi_error = $mci_controller->mci_get_response_error();
            $template_data['mailchimp_key_error'] = ' - ' . $mcapi_error['msg'];
            update_option(ESPRESSO_MAILCHIMP_INTEGRATION_ACTIVE_OPTION, 'false');
         }
      }

      $template_data['mailchimp_api_key'] = $mcapi_settings['api_key'];
      $template_path = EE_MAILCHIMP_SETT_TEMPLATE_PATH . 'mailchimp_settings.template.php';
      $this->_template_args['admin_page_content'] = EEH_Template::display_template( $template_path, $template_data, TRUE );
      $this->display_admin_page_with_sidebar();
   }

   //none of the below group are currently used for this page
   protected function _add_screen_options() {}
   protected function _add_feature_pointers() {}
   public function admin_init() {}
   public function admin_notices() {}
   public function admin_footer_scripts() {}
   public function load_scripts_styles() {}
}

?>