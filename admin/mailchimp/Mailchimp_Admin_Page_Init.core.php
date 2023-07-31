<?php

/**
 * A child class for initialising the MailChimp Settings Page in the list of EE settings pages
 *
 **/

class Mailchimp_Admin_Page_Init extends EE_Admin_Page_Init
{
    public function __construct()
    {
        define('EE_MAILCHIMP_LABEL', esc_html__('MailChimp', 'event_espresso'));
        define('EE_MAILCHIMP_ADMIN_URL', admin_url('admin.php?page=' . ESPRESSO_MAILCHIMP_SETTINGS_PAGE_SLUG));
        define('EE_MAILCHIMP_TEMPLATE_PATH', ESPRESSO_MAILCHIMP_ADMIN_DIR . 'templates/');
        parent::__construct();
        $this->_folder_path = ESPRESSO_MAILCHIMP_ADMIN_DIR;
    }


    protected function _set_init_properties()
    {
        $this->label = EE_MAILCHIMP_LABEL;
    }


    /**
     * @return array|void
     * @throws EE_Error
     */
    protected function _set_menu_map()
    {
        $this->_menu_map = new EE_Admin_Page_Sub_Menu(
            [
                'menu_group'      => 'addons',
                'menu_order'      => 10,
                'show_on_menu'    => true,
                'parent_slug'     => 'espresso_events',
                'menu_slug'       => ESPRESSO_MAILCHIMP_SETTINGS_PAGE_SLUG,
                'menu_label'      => EE_MAILCHIMP_LABEL,
                'capability'      => 'manage_options',
                'admin_init_page' => $this,
            ]
        );
    }
}
