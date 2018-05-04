<?php

/**
 * Class EE_Mailchimp_Config
 *
 * Settings for the MailChimp
 *
 * @package               Event Espresso
 * @subpackage            eea-mailchimp
 * @author                Nazar Kolivoshka
 * @since                 1.0
 *
 */
class EE_Mailchimp_Config extends EE_Config_Base
{

    /**
     * @var EE_Mailchimp_Config_Api_Settings
     */
    public $api_settings;


    /**
     * @return EE_Mailchimp_Config
     */
    public function __construct()
    {
        $this->api_settings = new EE_Mailchimp_Config_Api_Settings();
    }

    /**
     *
     * @return array one dimensional. All nested config classes properties are
     * 'flattened'. Eg, $this->tooltip->show becomes array key 'tooltip_show' in the newly
     * formed array
     */
    public function to_flat_array()
    {
        $flattened_vars = array();
        $properties = get_object_vars($this);
        foreach ($properties as $name => $property) {
            if ($property instanceof EE_Config_Base) {
                $sub_config_properties = get_object_vars($property);
                foreach ($sub_config_properties as $sub_config_property_name => $sub_config_property) {
                    $flattened_vars[ $name . "_" . $sub_config_property_name ] = $sub_config_property;
                }
            } else {
                $flattened_vars[ $name ] = $property;
            }
        }
        return $flattened_vars;
    }
}


/**
 * Class EE_Mailchimp_Config_Api_Settings
 *
 * Description
 *
 * @package               Event Espresso
 * @subpackage            eea-mailchimp
 * @author                Nazar Kolivoshka
 * @since                 1.0
 *
 */
class EE_Mailchimp_Config_Api_Settings extends EE_Config_Base
{

    /**
     * @var string $api_key
     */
    public $api_key;
    /**
     * @var bool $skip_double_optin
     */
    public $skip_double_optin;
    /**
     * @var bool $mc_active
     */
    public $mc_active;
    /**
     * @var string $submit_to_mc_when
     */
    public $submit_to_mc_when;
    /**
     * @var string $emails_type
     */
    public $emails_type;


    /**
     * @return EE_Mailchimp_Config_Api_Settings
     */
    public function __construct()
    {
        $this->api_key = '';
        $this->skip_double_optin = true;
        $this->mc_active = false;
        $this->submit_to_mc_when = 'reg-step-approved';
        $this->emails_type = 'html';
    }
}
