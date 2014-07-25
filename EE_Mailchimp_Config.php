<?php

/*
 * Settings for the MailChimp
 */
class EE_Mailchimp_Config extends EE_Config_Base {

    /**
     *
     * @var EE_Mailchimp_Config_Api_Settings
     */
    public $api_settings;

    public function __construct() {
        $this->api_settings = new EE_Mailchimp_Config_Api_Settings();
    }
    
    /**
     * 
     * @return array one dimensional. All nested config classes properties are 
     * 'flatened'. Eg, $this->tooltip->show becomes array key 'tooltip_show' in the newly
     * formed array
     */
    public function to_flat_array() {
        $flattened_vars = array();
        $properties = get_object_vars($this);
        foreach ( $properties as $name => $property ) {
            if ( $property instanceof EE_Config_Base ) {
                $sub_config_properties = get_object_vars($property);
                foreach ( $sub_config_properties as $sub_config_property_name => $sub_config_property ) {
                    $flattened_vars[$name."_".$sub_config_property_name] = $sub_config_property;
                }
            } else {
                $flattened_vars[$name] = $property;
            }
        }
        return $flattened_vars;
    }
}

class EE_Mailchimp_Config_Api_Settings extends EE_Config_Base {
    public $api_key;
    public $skip_double_optin;
    public $mc_active;

    public function __construct() {
        $this->api_key = '';
        $this->skip_double_optin = false;
        $this->mc_active = false;
    }
}