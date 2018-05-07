<?php
/**
 * Class EE_MC_Lists_Form.
 *
 * MailChimp Lists section.
 *
 * @package         Event Espresso
 * @subpackage      ee4-mailchimp
 * @since           2.4.0.rc.000
 *
 * ------------------------------------------------------------------------
 */
class EE_MC_Lists_Form extends EE_Form_Section_Proper
{

    /**
     * @access protected
     * @var object $_mc_controller
     */
    protected $_mc_controller = null;

    /**
     * @access protected
     * @var string $_list_id
     */
    protected $_list_id = null;


    /**
     * Class constructor
     *
     * @param EE_MCI_Controller $mc_controller
     * @param string $list_id
     * @return EE_Form_Section_Proper
     */
    public function __construct(EE_MCI_Controller $mc_controller, $list_id)
    {
        $this->_mc_controller = $mc_controller;
        $this->_list_id = $list_id;

        $options = $this->_template_setup();

        parent::__construct($options);
    }


    /**
     * Prepare the form options.
     *
     * @access public
     * @return array  section options.
     */
    protected function _template_setup()
    {
        // Get MC lists.
        $mc_lists = $this->_mc_controller->mci_get_users_lists();

        $lists = array();
        if (! empty($mc_lists)) {
            $lists = $this->_mc_lists($mc_lists);
        } else {
            $lists['no_lists'] = new EE_Form_Section_HTML(EEH_HTML::p(esc_html__('No lists found! Please log into your MailChimp account and create at least one mailing list.', 'event_espresso'), 'no-lists-found-notice', 'important-notice'));
        }

        $options = array(
            'html_id' => 'ee-mailchimp-groups-list',
            'html_class' => 'eea_mailchimp_groups_list',
            'layout_strategy' => new EE_Div_Per_Section_Spaced_Layout(),
            'subsections' => $lists
        );
        return $options;
    }


    /**
     * List all MC Lists.
     *
     * @access public
     * @param array   $mc_lists Mailchimp Lists.
     * @return array  List of MC Lists.
     */
    protected function _mc_lists($mc_lists)
    {
        $selected_found = false;
        $subsactions = $l_list = array();
        $selected = '-1';
        // Add a default value.
        $l_list['-1'] = esc_html__('Do not send to MailChimp', 'event_espresso');
        foreach ($mc_lists as $list) {
            // Find selected.
            if ($this->_list_id === $list['id'] || ( ! $selected_found && $list['id'] === '-1')) {
                $selected = $list['id'];
                $selected_found = true;
            }
            $l_list[ $list['id'] ] = $list['name'];
        }

        $subsactions['mc_lists'] = new EE_Select_Input(
            $l_list,
            array(
                'html_label_text' => esc_html__('Please select a List:', 'event_espresso'),
                'html_id'         => 'ee-mailchimp-lists',
                'html_name'       => 'ee_mailchimp_lists',
                'html_class'      => 'ee_mailchimp_dropdowns',
                'default'         => $selected
            )
        );

        return $subsactions;
    }
}
