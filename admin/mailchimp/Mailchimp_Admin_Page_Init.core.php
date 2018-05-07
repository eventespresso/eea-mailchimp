<?php
/**
* A child class for initialising the MailChimp Settings Page in the list of EE settings pages
*
**/

// Different methods for EE4.3 and EE4.4.
if ('4.4.0' > EVENT_ESPRESSO_VERSION) {

    class Mailchimp_Admin_Page_Init extends EE_Admin_Page_Init
    {

        public function __construct()
        {
            do_action('AHEE_log', __FILE__, __FUNCTION__, '');
            define('EE_MAILCHIMP_LABEL', __('Mailchimp', 'event_espresso'));
            define('EE_MAILCHIMP_ADMIN_URL', admin_url('admin.php?page=' . ESPRESSO_MAILCHIMP_SETTINGS_PAGE_SLUG));
            define('EE_MAILCHIMP_TEMPLATE_PATH', ESPRESSO_MAILCHIMP_ADMIN_DIR . 'mailchimp/templates/');
            parent::__construct();
            $this->_folder_path = ESPRESSO_MAILCHIMP_ADMIN_DIR . 'mailchimp' . DS;
        }

        protected function _set_init_properties()
        {
            $this->label = EE_MAILCHIMP_LABEL;
            $this->menu_label = EE_MAILCHIMP_LABEL;
            $this->menu_slug = ESPRESSO_MAILCHIMP_SETTINGS_PAGE_SLUG;
            $this->capability = 'manage_options';
        }

        public function get_menu_map()
        {
            $map = array(
            'group' => 'settings',
            'menu_order' => 40,
            'show_on_menu' => true,
            'parent_slug' => 'espresso_events'
            );
            return $map;
        }
    }

} else {

    class Mailchimp_Admin_Page_Init extends EE_Admin_Page_Init
    {

        public function __construct()
        {
            do_action('AHEE_log', __FILE__, __FUNCTION__, '');
            define('EE_MAILCHIMP_LABEL', __('Mailchimp', 'event_espresso'));
            define('EE_MAILCHIMP_ADMIN_URL', admin_url('admin.php?page=' . ESPRESSO_MAILCHIMP_SETTINGS_PAGE_SLUG));
            define('EE_MAILCHIMP_TEMPLATE_PATH', ESPRESSO_MAILCHIMP_ADMIN_DIR . 'mailchimp/templates/');
            parent::__construct();
            $this->_folder_path = ESPRESSO_MAILCHIMP_ADMIN_DIR . 'mailchimp' . DS;
        }

        protected function _set_init_properties()
        {
            $this->label = EE_MAILCHIMP_LABEL;
        }

        protected function _set_menu_map()
        {
            $this->_menu_map = new EE_Admin_Page_Sub_Menu(array(
            'menu_group' => 'addons',
            'menu_order' => 10,
            'show_on_menu' => true,
            'parent_slug' => 'espresso_events',
            'menu_slug' => ESPRESSO_MAILCHIMP_SETTINGS_PAGE_SLUG,
            'menu_label' => EE_MAILCHIMP_LABEL,
            'capability' => 'manage_options',
            'admin_init_page' => $this
            ));
        }
    }

}
