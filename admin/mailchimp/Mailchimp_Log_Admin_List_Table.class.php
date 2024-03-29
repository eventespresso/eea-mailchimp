<?php

/**
 * Mailchimp_Log_Admin_List_Table
 *
 * Class for preparing the list table to show the payment log
 *
 * note: anywhere there are no php docs it is because the docs are available in the parent class.
 *
 * @package     Registration_Form_Questions_Admin_List_Table
 * @subpackage  includes/core/admin/events/Registration_Form_Questions_Admin_List_Table.class.php
 * @author      Darren Ethier
 */
class Mailchimp_Log_Admin_List_Table extends EE_Admin_List_Table
{
    /**
     * @param EE_Admin_Page $admin_page
     * @return Mailchimp_Log_Admin_List_Table
     */
    public function __construct(EE_Admin_Page $admin_page)
    {
        parent::__construct($admin_page);
    }


    /**
     * _setup_data
     *
     * @return void
     */
    protected function _setup_data()
    {
        $this->_data           = $this->_admin_page->get_logs($this->_per_page, $this->_current_page);
        $this->_all_data_count = $this->_admin_page->get_logs($this->_per_page, $this->_current_page, true);
    }


    protected function _get_table_filters()
    {
        // TODO: Implement _get_table_filters() method.
    }


    /**
     * _set_properties
     *
     * @return void
     */
    protected function _set_properties()
    {
        $this->_wp_list_args = [
            'singular' => esc_html__('MailChimp Log', 'event_espresso'),
            'plural'   => esc_html__('MailChimp Logs', 'event_espresso'),
            'ajax'     => true, // for now,
            'screen'   => $this->_admin_page->get_current_screen()->id,
        ];

        $this->_columns = [
            'cb'          => '<input type="checkbox" />',
            'id'          => esc_html__('ID', 'event_espresso'),
            'LOG_time'    => esc_html__('Time', 'event_espresso'),
            'REG_ID'      => esc_html__('Registration', 'event_espresso'),
            'LOG_message' => esc_html__('Log Message', 'event_espresso'),
        ];

        $this->_sortable_columns = [
            'LOG_time' => ['LOG_time' => true],
        ];

        $this->_hidden_columns = [
        ];
        $this->_bottom_buttons = [
            'clear_logs' => [
                'route' => 'clear_logs',
            ],
        ];
    }


    /**
     * _add_view_counts
     *
     * @return void
     */
    protected function _add_view_counts()
    {
        $this->_views['all']['count'] = $this->_admin_page->get_logs($this->_per_page, $this->_current_page, true);
    }


    /**
     * column_cb
     *
     * @param EE_Change_Log $item
     * @return string
     */
    public function column_cb($item)
    {
        return '';
    }


    /**
     * column_id
     *
     * @param EE_Change_Log $item
     * @return string
     * @throws EE_Error
     * @throws ReflectionException
     */
    public function column_id(EE_Change_Log $item)
    {
        return $item->ID();
    }


    /**
     * column_LOG_time
     *
     * @param EE_Change_Log $item
     * @return string
     * @throws EE_Error
     * @throws ReflectionException
     */
    public function column_LOG_time(EE_Change_Log $item)
    {
        return $item->get_datetime('LOG_time');
    }


    /**
     * column_PMD_ID
     *
     * @param EE_Change_Log $item
     * @return string
     * @throws EE_Error
     */
    public function column_REG_ID(EE_Change_Log $item)
    {
        $reg = $item->object();
        if ($reg instanceof EE_Registration) {
            $att = $reg->attendee();
            if ($att instanceof EE_Attendee) {
                $name = $att->full_name();
            } else {
                $name = esc_html__('Unknown', 'event_espresso');
            }
            return '<a href="' . $reg->get_admin_details_link() . '">' . $name . '</a>';
        } else {
            return esc_html__("No longer exists", 'event_espresso');
        }
    }


    /**
     * column_TXN_ID
     *
     * @param EE_Change_Log $item
     * @return string
     * @throws EE_Error
     * @throws ReflectionException
     */
    public function column_LOG_message(EE_Change_Log $item)
    {
        return $item->get_pretty('LOG_message', 'as_table');
    }
}
