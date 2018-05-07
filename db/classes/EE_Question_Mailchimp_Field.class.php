<?php
/**
 * Class  EE_Question_Mailchimp_Field
 *
 * @package         Event Espresso
 * @subpackage      ee4-mailchimp
 *
 * ------------------------------------------------------------------------
 */
class EE_Question_Mailchimp_Field extends EE_Base_Class
{

    /**
     *
     * @param array  $props_n_values
     * @return EE_Question_Mailchimp_Field
     */
    public static function new_instance($props_n_values = array())
    {
        $classname = __CLASS__;
        $has_object = parent::_check_for_object($props_n_values, $classname);
        return $has_object ? $has_object : new self($props_n_values);
    }

    public static function new_instance_from_db($props_n_values = array())
    {
        return new self($props_n_values, true);
    }

    /**
     * Get the MailChimp Field ID.
     *
     * @return int
     */
    public function mc_field()
    {
        return $this->get('QMC_mailchimp_field_id');
    }

    /**
     * Get the Event Question ID.
     *
     * @return int
     */
    public function mc_event_question()
    {
        return $this->get('QST_ID');
    }
}
