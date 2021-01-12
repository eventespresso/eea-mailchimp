<?php

/**
 * Class EE_Mailchimp_Config_Api_Settings
 *
 * @author Nazar Kolivoshka
 * @since 1.0
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
     * EE_Mailchimp_Config_Api_Settings constructor.
     */
    public function __construct()
    {
        $this->api_key           = '';
        $this->skip_double_optin = true;
        $this->mc_active         = false;
        $this->submit_to_mc_when = 'reg-step-approved';
        $this->emails_type       = 'html';
    }
}
