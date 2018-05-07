<?php
/**
 * Class EE_MC_Metabox_Form.
 *
 * MailChimp Meta-box contents.
 *
 * @package         Event Espresso
 * @subpackage      ee4-mailchimp
 * @since           2.4.0.rc.000
 *
 * ------------------------------------------------------------------------
 */
class EE_MC_Metabox_Form extends EE_Form_Section_Proper
{

    /**
     * @access protected
     * @var object $_mc_controller
     */
    protected $_mc_controller = null;

    /**
     * @access protected
     * @var string $_event_id
     */
    protected $_event_id = null;

    /**
     * @access protected
     * @var string $_list_id
     */
    protected $_list_id = null;

    /**
     * @access protected
     * @var string $_category_id
     */
    protected $_category_id = null;


    /**
     * Class constructor.
     *
     * @param EE_MCI_Controller $mc_controller
     * @param WP_Post $event  The post object.
     * @return EE_Form_Section_Proper
     */
    public function __construct(EE_MCI_Controller $mc_controller, $event_id, $list_id, $category_id)
    {
        $this->_mc_controller = $mc_controller;
        $this->_event_id = $event_id;
        $this->_list_id = $list_id;
        $this->_category_id = $category_id;

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
        $sections = $this->_metabox_contents();

        $options = array(
            'html_id' => 'ee-mc-lists-metabox',
            'html_class' => 'espresso_mailchimp_integration_metabox',
            'layout_strategy' => new EE_Div_Per_Section_Layout(),
            'subsections' => $sections
        );
        return $options;
    }


    /**
     * List meta-box sections.
     *
     * @access public
     * @return array  List of merge fields pairs.
     */
    protected function _metabox_contents()
    {
        $subsactions = $hide_fields = array();

        // Form the meta-box.
        $subsactions['mc_meta_box'] = new EE_Form_Section_HTML(
            EEH_HTML::div($this->_mc_controller->mci_list_mailchimp_lists($this->_list_id), 'espresso-mci-lists', 'espresso-mci-lists-class') .
            EEH_HTML::div($this->_mc_controller->mci_list_mailchimp_groups($this->_event_id, $this->_list_id), 'espresso-mci-groups-list', 'espresso-mci-groups-list-class') .
            EEH_HTML::div(
                EEH_HTML::span('', 'ee_spinner_groups', 'ee-spinner ee-spin') .
                EEH_HTML::span(esc_html__('loading...', 'event_espresso'), 'ee_spinner_groups_id', 'ee-loading-txt small-text'),
                'ee-mailchimp-ajax-loading-groups',
                'ee-mailchimp-ajax-loading',
                'display:none;'
            ) .
            EEH_HTML::div($this->_mc_controller->mci_list_mailchimp_fields($this->_event_id, $this->_list_id), 'espresso-mci-list-fields', 'espresso_mci_list_fields') .
            EEH_HTML::div(
                EEH_HTML::span('', 'ee_spinner_fields', 'ee-spinner ee-spin') .
                EEH_HTML::span(esc_html__('loading...', 'event_espresso'), 'ee_spinner_fields_id', 'ee-loading-txt small-text'),
                'ee-mailchimp-ajax-loading-fields',
                'ee-mailchimp-ajax-loading',
                'display:none;'
            )
        );

        return $subsactions;
    }
}
