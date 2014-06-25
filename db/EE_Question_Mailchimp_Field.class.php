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
 * Class  EE_Question_Mailchimp_Field
 *
 * @package         Event Espresso
 * @subpackage      ee4-mailchimp
 *
 * ------------------------------------------------------------------------
 */

require_once ( EE_CLASSES . 'EE_Base_Class.class.php' );
class EE_Question_Mailchimp_Field extends EE_Base_Class {

    /**
     * 
     * @param array  $props_n_values
     * @return EE_Question_Mailchimp_Field
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
     * Get the MailChimp Field ID.
     *
     * @return int
     */
    public function mc_field() {
        return $this->get('QMC_mailchimp_field_id');
    }

    /**
     * Get the Event Question ID.
     *
     * @return int
     */
    public function event_question() {
        return $this->get('QST_ID');
    }

}

?>