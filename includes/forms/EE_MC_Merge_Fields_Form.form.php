<?php

/**
 * Class EE_MC_Merge_Fields_Form.
 * MailChimp Interest Categories section.
 *
 * @package         Event Espresso
 * @subpackage      ee4-mailchimp
 * @since           2.4.0.rc.000
 */
class EE_MC_Merge_Fields_Form extends EE_Form_Section_Proper
{
    /**
     * @var object $_mc_controller
     */
    protected $_mc_controller = null;

    /**
     * @var string $_list_id
     */
    protected $_list_id = '';

    /**
     * @var string $_event_id
     */
    protected $_event_id = '';


    /**
     * Class constructor
     *
     * @param EE_MCI_Controller $mc_controller
     * @param string            $event_id
     * @param string            $list_id
     * @throws EE_Error|ReflectionException
     */
    public function __construct(EE_MCI_Controller $mc_controller, string $event_id, string $list_id)
    {
        $this->_mc_controller = $mc_controller;
        $this->_event_id      = $event_id;
        $this->_list_id       = $list_id;
        $options              = $this->_template_setup();
        parent::__construct($options);
    }


    /**
     * Prepare the form options.
     *
     * @return array  section options.
     * @throws EE_Error
     * @throws ReflectionException
     */
    protected function _template_setup(): array
    {
        $options = [
            'html_id'         => 'espresso-mci-list-merge-fields',
            'html_class'      => 'espresso_mci_merge_fields_tb',
            'layout_strategy' => new EE_Two_Column_Layout(),
        ];
        if ($this->_list_id && $this->_list_id !== '-1') {
            // Get MC list fields.
            $list_fields     = $this->_mc_controller->mci_get_list_merge_vars($this->_list_id);
            $selected_fields = $this->_mc_controller->mci_event_list_question_fields((int) $this->_event_id);
            $evt_questions   = $this->_mc_controller->mci_get_event_all_questions($this->_event_id);
            $m_fields = [];
            if (! empty($list_fields)) {
                $m_fields = $this->_merge_fields($list_fields, $selected_fields, $evt_questions);
            } elseif ($_GET['action'] === 'create_new') {
                // This is new event so no data.
                $m_fields['no_data'] = new EE_Form_Section_HTML('');
            } else {
                $m_fields['no_lists'] = new EE_Form_Section_HTML(
                    EEH_HTML::p(
                        esc_html__('Sorry, no merge fields found!', 'event_espresso'),
                        'no-lists-found-notice',
                        'important-notice'
                    )
                );
            }
            $options['subsections'] = $m_fields;
        } else {
            // No list - no merge-fields data.
            $options['subsections']['no_data'] = new EE_Form_Section_HTML('');
        }
        return $options;
    }


    /**
     * List all merge fields.
     *
     * @param array $list_fields
     * @param array $selected_fields
     * @param array $evt_questions
     * @return array  List of merge fields pairs.
     */
    protected function _merge_fields(array $list_fields, array $selected_fields, array $evt_questions)
    {
        $subs_actions = [];
        // Add Table heading.
        $subs_actions['mc_ql_tbl'] = new EE_Form_Section_HTML(
            EEH_HTML::no_row(EEH_HTML::br()) .
            EEH_HTML::tr(
                EEH_HTML::th(esc_html__('MailChimp Fields', 'event_espresso')) .
                EEH_HTML::th(esc_html__('Event Espresso Questions', 'event_espresso'))
            )
        );
        foreach ($list_fields as $l_field) {
            $selected    = '-1';
            $fields_list = [];
            foreach ($evt_questions as $q_field) {
                $fields_list[ $q_field['QST_ID'] ] = $q_field['QST_name'];
                // Default to main fields if exist.
                if (
                    (isset($l_field->tag, $selected_fields[ $l_field->tag ])
                     && ($selected_fields[ $l_field->tag ] == $q_field['QST_ID']
                         || $selected_fields[ $l_field->tag ] == $q_field['QST_system']))
                    || (($q_field['QST_system'] == 'email' || $q_field['QST_ID'] == 3) && $l_field->tag == 'EMAIL'
                        && ! array_key_exists(
                            'EMAIL',
                            $selected_fields
                        ))
                    || (($q_field['QST_system'] == 'lname' || $q_field['QST_ID'] == 2) && $l_field->tag == 'LNAME'
                        && ! array_key_exists(
                            'LNAME',
                            $selected_fields
                        ))
                    || (($q_field['QST_system'] == 'fname' || $q_field['QST_ID'] == 1) && $l_field->tag == 'FNAME'
                        && ! array_key_exists(
                            'FNAME',
                            $selected_fields
                        ))
                ) {
                    $selected = $q_field['QST_ID'];
                }
            }
            // Add a default value.
            $fields_list['-1']             = esc_html__('none', 'event_espresso');
            $subs_actions[ $l_field->tag ] = new EE_Select_Input(
                $fields_list,
                [
                    'default'         => $selected,
                    'html_label_text' => $l_field->required ? $l_field->name . '*' : $l_field->name,
                    'html_id'         => 'event-question-' . base64_encode($l_field->name),
                    'html_name'       => base64_encode($l_field->tag),
                    'html_class'      => 'ee_event_fields_selects',
                ]
            );
            // Need to pass all fields that are available.
            $subs_actions[ 'hdn-qf-' . $l_field->tag ] = new EE_Hidden_Input(
                [
                    'html_name' => 'ee_mailchimp_qfields[]',
                    'html_id'   => 'ee-mc-qf-' . $l_field->tag,
                    'default'   => $l_field->tag,
                ]
            );
        }
        return $subs_actions;
    }
}
