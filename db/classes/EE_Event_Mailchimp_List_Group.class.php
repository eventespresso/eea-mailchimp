<?php

/**
 * Class  EE_Event_Mailchimp_List_Group
 *
 * @package         Event Espresso
 * @subpackage      ee4-mailchimp
 */
class EE_Event_Mailchimp_List_Group extends EE_Base_Class
{
    /**
     *
     * @param array $props_n_values
     * @return EE_Event_Mailchimp_List_Group
     * @throws EE_Error
     * @throws ReflectionException
     */
    public static function new_instance($props_n_values = [])
    {
        $classname  = __CLASS__;
        $has_object = parent::_check_for_object($props_n_values, $classname);
        return $has_object ? $has_object : new self($props_n_values);
    }


    /**
     * @param array $props_n_values
     * @return EE_Event_Mailchimp_List_Group
     * @throws EE_Error
     * @throws ReflectionException
     */
    public static function new_instance_from_db($props_n_values = [])
    {
        return new self($props_n_values, true);
    }


    /**
     * Get the MailChimp Group ID.
     *
     * @return int
     * @throws EE_Error
     * @throws ReflectionException
     */
    public function mc_group()
    {
        return $this->get('AMC_mailchimp_group_id');
    }


    /**
     * Get the MailChimp List ID.
     *
     * @return string
     * @throws EE_Error
     * @throws ReflectionException
     */
    public function mc_list()
    {
        return (string) $this->get('AMC_mailchimp_list_id');
    }
}
