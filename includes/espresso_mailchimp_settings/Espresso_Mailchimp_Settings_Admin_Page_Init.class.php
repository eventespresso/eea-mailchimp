<?php
/**
* A child class for initialising the MailChimp Settings Page in the list of EE settings pages
*
**/

class Espresso_Mailchimp_Settings_Admin_Page_Init extends EE_Admin_Page_Init {

   public function __construct() {
      define( 'EE_MAILCHIMP_SETT_LABEL', __('MailChimp Settings', 'event_espresso') );
      define( 'EE_MAILCHIMP_SETT_ADMIN_URL', admin_url( 'admin.php?page=' . ESPRESSO_MAILCHIMP_SETTINGS_PAGE_SLUG ) );
      define( 'EE_MAILCHIMP_SETT_TEMPLATE_PATH', ESPRESSO_MAILCHIMP_DIR . 'templates/' );
      parent::__construct();
   }

   protected function _set_init_properties() {
      $this->label = __('EE MailChimp', 'event_espresso');
      $this->menu_label = __('MailChimp', 'event_espresso');
      $this->menu_slug = ESPRESSO_MAILCHIMP_SETTINGS_PAGE_SLUG;
      $this->capability = 'administrator';
   }

   public function get_menu_map() {
      $map = array(
         'group' => 'settings',
         'menu_order' => 50,
         'show_on_menu' => TRUE,
         'parent_slug' => 'espresso_events'
         );
      return $map;
   }
}

?>