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
 * Class  EE_Event_Mailchimp_List_Group
 *
 * @package         Event Espresso
 * @subpackage      ee4-mailchimp-integration
 *
 * ------------------------------------------------------------------------
 */

require_once ( EE_CLASSES . 'EE_Base_Class.class.php' );
class EE_Event_Mailchimp_List_Group extends EE_Base_Class {
    
    /**
     * 
     * @param array  $props_n_values
     * @return EE_Event_Mailchimp_List_Group
     */
    public static function new_instance( $props_n_values = array() ) {
        $classname = __CLASS__;
        $has_object = parent::_check_for_object( $props_n_values, $classname );
        return $has_object ? $has_object : new self( $props_n_values );
    }

    public static function new_instance_from_db ( $props_n_values = array() ) {
        return new self( $props_n_values, TRUE );
    }

    /**
     * Get the MailChimp Group ID.
     *
     * @return int
     */
    public function group() {
        return $this->get('AMC_mailchimp_group_id');
    }

    /**
     * Get the MailChimp List ID.
     *
     * @return int
     */
    public function list() {
        return $this->get('AMC_mailchimp_list_id');
    }

}

?>