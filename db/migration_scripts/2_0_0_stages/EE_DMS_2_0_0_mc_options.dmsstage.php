<?php

/**
 * Converts EE3 MailChimp add-on options to a EE4's mailchimp config
 */
class EE_DMS_2_0_0_mc_options extends EE_Data_Migration_Script_Stage
{

    protected $_mc_options_to_migrate = array(
        'apikey',
    );

    public function __construct()
    {
        $this->_pretty_name = __("MailChimp Options", "event_espresso");
        $this->_mc_options_to_migrate = apply_filters('FHEE__EE_DMS_4_1_0_mailchimp_options__mc_options_to_migrate', $this->_mc_options_to_migrate);
        parent::__construct();
    }

    public function _migration_step($num_items = 1)
    {
        // Get mailchimp's config.
        if (isset(EE_Config::instance()->addons->EE_Mailchimp) && EE_Config::instance()->addons->EE_Mailchimp instanceof EE_Mailchimp_Config) {
            $config = EE_Config::instance()->addons->EE_Mailchimp;
        } else {
            $config = new EE_Mailchimp_Config();
            EE_Config::instance()->addons->EE_Mailchimp = $config;
        }

        $items_actually_migrated = 0;
        $old_mc_options = get_option('event_mailchimp_settings');
        foreach ($this->_mc_options_to_migrate as $option_name) {
            // Only if there's a setting to migrate.
            if (isset($old_mc_options[ $option_name ])) {
                $this->_handle_mc_option($option_name, $old_mc_options[ $option_name ], $config);
            }
            $items_actually_migrated++;
        }
        $success = EE_Config::instance()->update_config('addons', 'EE_Mailchimp', $config);
        if (! $success) {
            $this->add_error(EE_Error::get_notices());
        }
        // Activate MC if the API Key is valid.
        $mci_controller = new EE_MCI_Controller();
        $key_ok = $mci_controller->mci_is_api_key_valid($config->api_settings->api_key);
        if ($key_ok) {
            $config->api_settings->mc_active = true;
            EE_Config::instance()->update_config('addons', 'EE_Mailchimp', $config);
        }
        if ($this->count_records_migrated() + $items_actually_migrated >= $this->_count_records_to_migrate()) {
            $this->set_completed();
        }
        return $items_actually_migrated;
    }

    public function _count_records_to_migrate()
    {
        $count_of_options_to_migrate = count($this->_mc_options_to_migrate);
        return $count_of_options_to_migrate;
    }

    /**
     *
     * @param type $option_name
     * @param type $value
     * @param EE_Mailchimp_Config $config
     */
    private function _handle_mc_option($option_name, $value, $config)
    {
        switch ($option_name) {
            case 'apikey':
                $config->api_settings->api_key = $value;
                break;
            default:
                do_action('AHEE__EE_DMS_4_1_0__handle_org_option', $option_name, $value);
        }
    }
}
