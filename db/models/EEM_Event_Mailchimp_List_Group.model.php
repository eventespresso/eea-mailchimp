<?php
/**
 * Class  EEM_Event_Mailchimp_List_Group
 *
 * @package         Event Espresso
 * @subpackage      ee4-mailchimp
 *
 * ------------------------------------------------------------------------
 */

class EEM_Event_Mailchimp_List_Group extends EEM_Base
{

    /**
     * Instance of the EEM_Event_Mailchimp_List_Group object
     */
    protected static $_instance = null;



    /**
     * This function is a singleton method used to instantiate the EEM_Event_Mailchimp_List_Group object
     *
     * @access public
     * @param string $timezone
     * @return EEM_Event_Mailchimp_List_Group
     */
    public static function instance($timezone = null)
    {
        // Check if instance of EEM_Event_Mailchimp_List_Group already exists.
        if (self::$_instance === null) {
            // Instantiate Espresso_model.
            self::$_instance = new self($timezone);
        }
        self::$_instance->set_timezone($timezone);
        // EEM_Event_Mailchimp_List_Group object
        return self::$_instance;
    }



    /**
     * @throws \EE_Error
     * @return EEM_Event_Mailchimp_List_Group
     */
    protected function __construct($timezone = null)
    {
        $this->singular_item = __('Mailchimp List Group', 'event_espresso');
        $this->plural_item = __('Mailchimp List Groups', 'event_espresso');
        $this->_tables = array(
            'Event_Mailchimp_List_Group' => new EE_Primary_Table('esp_event_mailchimp_list_group', 'EMC_ID')
        );
        $this->_fields = array(
            'Event_Mailchimp_List_Group' => array(
                'EMC_ID' => new EE_Primary_Key_Int_Field('EMC_ID', __('Field ID', 'event_espresso')),
                'EVT_ID' => new EE_Foreign_Key_Int_Field('EVT_ID', __('Event to Mailchimp List Group Link ID', 'event_espresso'), false, 0, 'Event'),
                'AMC_mailchimp_list_id' => new EE_Plain_Text_Field('AMC_mailchimp_list_id', __('MailChimp List ID', 'event_espresso'), false, ''),
                'AMC_mailchimp_group_id' => new EE_Plain_Text_Field('AMC_mailchimp_group_id', __('MailChimp Group ID', 'event_espresso'), false, '')
            )
        );
        $this->_model_relations = array(
            'Event' => new EE_Belongs_To_Relation()
        );
        parent::__construct($timezone);
    }


    /**
     * resets the model and returns it
     * @param string $timezone
     * @return EEM_Event_Mailchimp_List_Group
     */
    public static function reset($timezone = null)
    {
        self::$_instance = null;
        return self::instance($timezone);
    }
}
