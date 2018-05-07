<?php
/**
* This contains the logic for setting up the Custom MailChimp Settings Page.
*
**/

class Mailchimp_Admin_Page extends EE_Admin_Page
{

    public function __construct($routing = true)
    {
        parent::__construct($routing);
    }

    protected function _init_page_props()
    {
        $this->page_slug = ESPRESSO_MAILCHIMP_SETTINGS_PAGE_SLUG;
        $this->page_label = EE_MAILCHIMP_LABEL;
    }

    protected function _ajax_hooks()
    {
        // todo: all hooks for ajax goes here.
    }

    protected function _define_page_props()
    {
        $this->_admin_base_url = EE_MAILCHIMP_ADMIN_URL;
        $this->_admin_page_title = $this->page_label;
        $this->_labels = array(
            'buttons' => array(
                'clear_logs' => esc_html__('Clear All MailChimp Logs', 'event_espresso')
            )
        );
    }

    protected function _set_page_routes()
    {
        $this->_page_routes = array(
            'default' => array(
                'func' => '_mailchimp_api_settings'
            ),
            'update_mailchimp'  => array(
                'func' => '_update_mailchimp',
                'noheader' => true
            ),
            'log'=> array(
                'func'=> '_log_overview_list_table',
            ),
            'clear_logs' => array(
                'func' => '_clear_logs',
                'noheader' => true
            )
        );
    }

    protected function _set_page_config()
    {
        $this->_page_config = array(
            'default' => array(
                'nav' => array(
                    'label' => __('Main Settings', 'event_espresso'),
                    'order' => 10
                ),
                'metaboxes' => array( '_publish_post_box', '_espresso_news_post_box', '_espresso_links_post_box', '_espresso_sponsors_post_box' , '_mailchimp_meta_boxes' )
            ),
            'log'=>array(
                'nav'=> array(
                    'label' => __("Logs", 'event_espresso'),
                    'order'=>30,
                ),
                'list_table'=>'Mailchimp_Log_Admin_List_Table',
                'metaboxes' => $this->_default_espresso_metaboxes,
                'require_nonce'=> false
            )
        );
    }



    /**
     *  load_scripts_styles
     */
    public function load_scripts_styles()
    {
        wp_enqueue_script('ee_admin_js');
    }



    /**
     *      declare price details page metaboxes
     *      @access protected
     *      @return void
     */
    protected function _mailchimp_meta_boxes()
    {
        add_meta_box('mailchimp-instructions-mbox', __('MailChimp Instructions', 'event_espresso'), array( $this, '_mailchimp_instructions_meta_box' ), $this->wp_page_slug, 'normal', 'high');
        add_meta_box('mailchimp-details-mbox', __('API Settings', 'event_espresso'), array( $this, '_mailchimp_api_settings_meta_box' ), $this->wp_page_slug, 'normal', 'high');
    }



    /**
     *      _mailchimp_api_settings_meta_box
     *      @access public
     *      @return void
     */
    public function _mailchimp_api_settings_meta_box()
    {
        echo EEH_Template::display_template(EE_MAILCHIMP_TEMPLATE_PATH . 'mailchimp_api_settings.template.php', $this->_template_args, true);
    }



    /**
     *      _edit_price_details_meta_box
     *      @access public
     *      @return void
     */
    public function _mailchimp_instructions_meta_box()
    {
        echo EEH_Template::display_template(EE_MAILCHIMP_TEMPLATE_PATH . 'mailchimp_instructions.template.php', $this->_template_args, true);
    }



    /**
     *      _mailchimp_api_settings
     *      @access protected
     *      @return void
     */
    protected function _mailchimp_api_settings()
    {

        $config = EED_Mailchimp::get_config();
        // d( $config );
        $this->_template_args['mailchimp_double_opt_check'] =  isset($config->api_settings->skip_double_optin) && $config->api_settings->skip_double_optin === false ? 'checked="checked"' : '';
        // When do we want to submit the registrant to the MC.
        $this->_template_args['submit_to_mc_end'] = $this->_template_args['submit_to_mc_complete'] = $this->_template_args['submit_to_mc_approved'] = '';
        switch ($config->api_settings->submit_to_mc_when) {
            case 'attendee-information-end':
                $this->_template_args['submit_to_mc_end'] = 'selected';
                break;
            case 'reg-step-completed':
                $this->_template_args['submit_to_mc_complete'] = 'selected';
                break;
            case 'reg-step-approved':
                $this->_template_args['submit_to_mc_approved'] = 'selected';
                break;
            default:
                $this->_template_args['submit_to_mc_approved'] = 'selected';
                break;
        }
        // What type of emails do we want to send to the subscriber.
        $this->_template_args['mailchimp_html_emails'] = $this->_template_args['mailchimp_text_emails'] = '';
        switch ($config->api_settings->emails_type) {
            case 'html':
                $this->_template_args['mailchimp_html_emails'] = 'selected';
                break;
            case 'text':
                $this->_template_args['mailchimp_text_emails'] = 'selected';
                break;
            default:
                $this->_template_args['mailchimp_html_emails'] = 'selected';
                break;
        }

        $this->_template_args['mailchimp_api_key'] = isset($config->api_settings, $config->api_settings->api_key) ? $config->api_settings->api_key : '';
        if (isset($this->_req_data['mcapi_error']) && ! empty($this->_req_data['mcapi_error'])) {
            $this->_template_args['mailchimp_key_error'] = '<span class="important-notice">' . $this->_req_data['mcapi_error'] . '</span>';
            $this->_template_args['mailchimp_api_key_class'] = 'error';
            $this->_template_args['mailchimp_api_key_img'] = '<span class="dashicons dashicons-no pink-icon ee-icon-size-24"></span>';
        } elseif (! empty($this->_template_args['mailchimp_api_key'])) {
            $this->_template_args['mailchimp_key_error'] = null;
            $this->_template_args['mailchimp_api_key_class'] = '';
            $this->_template_args['mailchimp_api_key_img'] = '<span class="dashicons dashicons-yes green-icon ee-icon-size-24"></span>';
        } else {
            $this->_template_args['mailchimp_key_error'] = null;
            $this->_template_args['mailchimp_api_key_class'] = '';
            $this->_template_args['mailchimp_api_key_img'] = '';
        }

        $this->_set_publish_post_box_vars('id', 1, false, null, false);
        $this->_set_add_edit_form_tags('update_mailchimp');
        // the details template wrapper
        $this->display_admin_page_with_sidebar();
    }



    /**
     *  _update_mailchimp
     *  validates and saves the MailChimp API key to the config
     */
    protected function _update_mailchimp()
    {
        $query_args = array( 'action' => 'default' );
        /** @type EE_Mailchimp_Config $config */
        $config = EED_Mailchimp::get_config();
        if (isset($_POST['mailchimp_api_key']) && ! empty($_POST['mailchimp_api_key'])) {
            $mailchimp_api_key = sanitize_text_field($_POST['mailchimp_api_key']);
            $mci_controller = new EE_MCI_Controller($mailchimp_api_key);
            // Validate the MailChimp API Key
            $key_valid = $mci_controller->mci_get_api_key();
            if ($key_valid) {
                $key_valid = true;
                $config->api_settings->mc_active = 1;
                $config->api_settings->api_key = $mailchimp_api_key;
                $config->api_settings->skip_double_optin = empty($_POST['mailchimp_double_opt']) ? true : false;
                $config->api_settings->emails_type = empty($_POST['emails_type']) ? 'html' : $_POST['emails_type'];
                $config->api_settings->submit_to_mc_when = empty($_POST['submit_to_mc_when']) ? 'reg-step-approved' : $_POST['submit_to_mc_when'];
            } else {
                $key_valid = false;
                $mcapi_error = $mci_controller->mci_get_response_error();
                $error_msg = isset($mcapi_error['msg']) ? $mcapi_error['msg'] : __('Unknown MailChimp API Error.', 'event_espresso');
                EE_Error::add_error($error_msg, __FILE__, __FUNCTION__, __LINE__);
                $query_args['mcapi_error'] = $error_msg;
                $config->api_settings->mc_active = false;
                $config->api_settings->api_key = '';
                $config->api_settings->emails_type = empty($_POST['emails_type']) ? 'html' : $_POST['emails_type'];
                $config->api_settings->submit_to_mc_when = empty($_POST['submit_to_mc_when']) ? 'reg-step-approved' : $_POST['submit_to_mc_when'];
            }
        } else {
            $key_valid = false;
            $error_msg = __('Please enter a MailChimp API key.', 'event_espresso');
            EE_Error::add_error($error_msg, __FILE__, __FUNCTION__, __LINE__);
            $query_args['mcapi_error'] = $error_msg;
            $config->api_settings->mc_active = false;
            $config->api_settings->api_key = '';
            $config->api_settings->emails_type = empty($_POST['emails_type']) ? 'html' : $_POST['emails_type'];
            $config->api_settings->submit_to_mc_when = empty($_POST['submit_to_mc_when']) ? 'reg-step-approved' : $_POST['submit_to_mc_when'];
        }
        EED_Mailchimp::update_config($config);
        if (isset($query_args['mcapi_error'])) {
            $query_args['mcapi_error'] = urlencode($query_args['mcapi_error']);
        }
        $this->_redirect_after_action($key_valid, 'Mailchimp API Key', 'updated', $query_args);
    }

    protected function _log_overview_list_table()
    {
//      $this->_search_btn_label = __('Payment Log', 'event_espresso');
        $this->display_admin_list_table_page_with_sidebar();
    }

    protected function _set_list_table_views_log()
    {
        $this->_views = array(
            'all' => array(
                'slug' => 'all',
                'label' => __('View All Logs', 'event_espresso'),
                'count' => 0,
            )
        );
    }

    public function get_logs($per_page = 50, $current_page = 0, $count = false)
    {
        $query_params = array(
            array(
                'LOG_type' => EED_Mailchimp::log_type
            ),
            'order_by' => array(
                'LOG_time' => 'DESC'
            )
        );
        if ($count) {
            return EEM_Change_Log::instance()->count($query_params);
        } else {
            $query_params['limit'] =  array( ( $current_page - 1 ) * $per_page, $per_page );
            return  EEM_Change_Log::instance()->get_all($query_params);
        }
    }

    protected function _clear_logs()
    {
        $deleted = EEM_Change_Log::instance()->delete(
            array(
                array(
                    'LOG_type' => EED_Mailchimp::log_type
                )
            )
        );
        $this->_redirect_after_action(
            $deleted,
            esc_html__('MailChimp Log Entries', 'event_espresso'),
            esc_html__('deleted', 'event_espresso'),
            array(
                'action' => 'log'
            )
        );
    }




    // none of the below group are currently used for this page
    protected function _add_screen_options()
    {
    }
    protected function _add_feature_pointers()
    {
    }
    public function admin_init()
    {
    }
    public function admin_notices()
    {
    }
    public function admin_footer_scripts()
    {
    }
}
