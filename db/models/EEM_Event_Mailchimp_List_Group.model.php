<?php if ( ! defined( 'EVENT_ESPRESSO_VERSION' ) ) { exit('No direct script access allowed'); }
/**
 * Event Espresso
 *
 * Event Registration and Management Plugin for WordPress
 *
 * @ package        Event Espresso
 * @ author         Event Espresso
 * @ copyright (c)  2008-2014 Event Espresso  All Rights Reserved.
 * @ license        http://eventespresso.com/support/terms-conditions/   * see Plugin Licensing *
 * @ link           http://www.eventespresso.com
 * @ version        EE4
 *
 * ------------------------------------------------------------------------
 *
 * Class  EEM_Event_Mailchimp_List_Group
 *
 * @package         Event Espresso
 * @subpackage      ee4-mailchimp
 *
 * ------------------------------------------------------------------------
 */

class EEM_Event_Mailchimp_List_Group extends EEM_Base {

    /**
     * Instance of the Attendee object
     * @access private
     */
    private static $_instance = NULL;

    /**
     * This funtion is a singleton method used to instantiate the EEM_Event_Mailchimp_List_Group object
     *
     * @access public
     * @return EEM_Event_Mailchimp_List_Group instance
     */
    public static function instance( $timezone = NULL ) {
        // Check if instance of EEM_Event_Mailchimp_List_Group already exists.
        if ( self::$_instance === NULL ) {
            // Instantiate Espresso_model.
            self::$_instance = new self();
        }
        // EEM_Event_Mailchimp_List_Group object
        return self::$_instance;
    }

    protected function __construct( $timezone = NULL ) {
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
        parent::__construct();
    }


    /**
     * resets the model and returns it
     * @return EEM_Event_Mailchimp_List_Group
     */
    public static function reset( $timezone = NULL ){
        self::$_instance = NULL;
        return self::instance();
    }

}

?>
