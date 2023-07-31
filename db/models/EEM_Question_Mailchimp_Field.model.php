<?php

/**
 * Class  EEM_Question_Mailchimp_Field
 *
 * @package         Event Espresso
 * @subpackage      ee4-mailchimp
 * ------------------------------------------------------------------------
 */


class EEM_Question_Mailchimp_Field extends EEM_Base
{
    /**
     * @var $_instance EEM_Question_Mailchimp_Field
     */
    protected static $_instance = null;


    /**
     * This function is a singleton method used to instantiate the EEM_Question_Mailchimp_Field object
     *
     * @param string|null $timezone
     * @return EEM_Question_Mailchimp_Field instance
     * @throws EE_Error
     */
    public static function instance(?string $timezone = '')
    {
        // Check if instance of EEM_Question_Mailchimp_Field already exists.
        if (self::$_instance === null) {
            // Instantiate Espresso_model.
            self::$_instance = new self($timezone);
        }
        self::$_instance->set_timezone($timezone);
        // EEM_Question_Mailchimp_Field object
        return self::$_instance;
    }


    /**
     * EEM_Question_Mailchimp_Field constructor.
     *
     * @param string|null $timezone
     * @throws EE_Error
     */
    protected function __construct(?string $timezone = '')
    {
        $this->singular_item    = esc_html__('MailChimp List Group', 'event_espresso');
        $this->plural_item      = esc_html__('MailChimp List Groups', 'event_espresso');
        $this->_tables          = [
            'Event_Mailchimp_List_Group' => new EE_Primary_Table('esp_event_question_mailchimp_field', 'QMC_ID'),
        ];
        $this->_fields          = [
            'Event_Mailchimp_List_Group' => [
                'QMC_ID'                 => new EE_Primary_Key_Int_Field(
                    'QMC_ID',
                    esc_html__('MailChimp Question ID', 'event_espresso')
                ),
                'EVT_ID'                 => new EE_Foreign_Key_Int_Field(
                    'EVT_ID',
                    esc_html__('Event to MailChimp List Group Link ID', 'event_espresso'),
                    false,
                    0,
                    'Event'
                ),
                'QST_ID'                 => new EE_Plain_Text_Field(
                    'QST_ID',
                    esc_html__('Question ID', 'event_espresso'),
                    false
                ),
                'QMC_mailchimp_field_id' => new EE_Plain_Text_Field(
                    'QMC_mailchimp_field_id',
                    esc_html__('MailChimp Field ID', 'event_espresso'),
                    false
                ),
            ],
        ];
        $this->_model_relations = [
            'Event' => new EE_Belongs_To_Relation(),
        ];
        parent::__construct($timezone);
    }


    /**
     * resets the model and returns it
     *
     * @param string|null $timezone
     * @return EEM_Question_Mailchimp_Field
     * @throws EE_Error
     */
    public static function reset(?string $timezone = '')
    {
        self::$_instance = null;
        return self::instance($timezone);
    }
}
