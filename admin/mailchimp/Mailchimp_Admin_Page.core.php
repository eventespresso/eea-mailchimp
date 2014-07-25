<?php
/**
* This contains the logic for setting up the Custom MailChimp Settings Page.
*
**/

class Mailchimp_Admin_Page extends EE_Admin_Page {

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
      $config = EE_Config::instance()->get_config( 'addons', 'EE_Mailchimp', 'EE_Mailchimp_Config' );
      $mci_controller = new EE_MCI_Controller();
      $template_data = array();
      $template_data['mc_api_key_ok'] = $template_data['mc_api_key_error'] = 'none';
      $template_data['mailchimp_key_error'] = '';
      if ( isset($_POST['save_key_button']) ) {
         $config->api_settings->api_key = ( isset($_POST['mailchimp_api_key']) ) ? $_POST['mailchimp_api_key'] : '';
         $config->api_settings->skip_double_optin = ( isset($_POST['mailchimp_double_opt']) ) ? false : true;
         EE_Config::instance()->update_config( 'addons', 'EE_Mailchimp', $config );
      }
      // Validate the MailChimp API Key
      if ( isset($config) && strlen($config->api_settings->api_key) > 1 ) {
         $key_valid = $mci_controller->mci_is_api_key_valid();
         if ( $key_valid ) {
            $template_data['mc_api_key_ok'] = 'inline';
            $config->api_settings->mc_active = 'true';
            EE_Config::instance()->update_config( 'addons', 'EE_Mailchimp', $config );
         } else {
            $template_data['mc_api_key_error'] = 'inline';
            $mcapi_error = $mci_controller->mci_get_response_error();
            $template_data['mailchimp_key_error'] = ' - ' . $mcapi_error['msg'];
            $config->api_settings->mc_active = 'false';
            EE_Config::instance()->update_config( 'addons', 'EE_Mailchimp', $config );
         }
      } elseif ( strlen($config->api_settings->api_key) < 1 ) {
         $config->api_settings->mc_active = 'false';
         EE_Config::instance()->update_config( 'addons', 'EE_Mailchimp', $config );
      }
      $template_data['mailchimp_double_opt_check'] = '';
      if ( isset($config) && isset($config->api_settings->skip_double_optin) && ($config->api_settings->skip_double_optin === false) ) {
         $template_data['mailchimp_double_opt_check'] = 'checked';
      }
      $template_data['mailchimp_api_key'] = $config->api_settings->api_key;
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