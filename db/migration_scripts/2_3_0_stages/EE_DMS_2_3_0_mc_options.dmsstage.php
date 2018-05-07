<?php

/**
 * Converts MailChimp 2.1.0 options to MC 2.3.0.
 */
class EE_DMS_2_3_0_mc_options extends EE_Data_Migration_Script_Stage
{

    protected $_mc_options_to_migrate = array(
        'Mailchimp',
    );

    public function __construct()
    {
        $this->_pretty_name = __("MailChimp Options", "event_espresso");
        $this->_mc_options_to_migrate = apply_filters('FHEE__EE_DMS_4_1_0_mailchimp_options__mc_options_to_migrate', $this->_mc_options_to_migrate);
        parent::__construct();
    }

    public function _migration_step($num_items = 1)
    {
        $items_actually_migrated = 0;
        // Get mailchimp's config.
        $config = EE_Config::instance()->get_config('addons', 'EE_Mailchimp', 'EE_Mailchimp_Config');
        if (! isset($config) || ! ($config instanceof EE_Mailchimp_Config)) {
            $config = new EE_Mailchimp_Config();
            EE_Config::instance()->addons->EE_Mailchimp = $config;
        }
                $success = EE_Config::instance()->set_config('addons', 'Mailchimp', 'EE_Mailchimp_Config', $config);
        if (! $success) {
            $this->add_error(EE_Error::get_notices());
        }
        $items_actually_migrated++;

        // Activate MC if the API Key is valid.
        if (isset($config->api_settings->api_key) && $config->api_settings->api_key != '') {
            $mci_controller = new EE_MCI_Controller();
            $key_ok = $mci_controller->mci_is_api_key_valid($config->api_settings->api_key);
            if ($key_ok) {
                $config->api_settings->mc_active = true;
                EE_Config::instance()->update_config('addons', 'Mailchimp', $config);
            }
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
}
