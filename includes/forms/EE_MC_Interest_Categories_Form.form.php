<?php
/**
 * Class EE_MC_Interest_Categories_Form.
 *
 * MailChimp Interest Categories section.
 *
 * @package         Event Espresso
 * @subpackage      ee4-mailchimp
 * @since           2.4.0.rc.000
 *
 * ------------------------------------------------------------------------
 */
class EE_MC_Interest_Categories_Form extends EE_Form_Section_Proper
{

    /**
     * @access protected
     * @var array $_all_interests
     */
    protected $_all_interests = array();

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
     * Class constructor
     *
     * @param EE_MCI_Controller $mc_controller
     * @param string $event_id
     * @param string $list_id
     * @return EE_Form_Section_Proper
     */
    public function __construct(EE_MCI_Controller $mc_controller, $event_id, $list_id)
    {
        $this->_mc_controller = $mc_controller;
        $this->_event_id = $event_id;
        $this->_list_id = $list_id;

        $options = $this->_template_setup();

        parent::__construct($options);
    }


    /**
     * Prepare the form options.
     *
     * @access public
     * @return array  List of section options.
     */
    protected function _template_setup()
    {
        $options = array(
            'html_id' => 'ee-mailchimp-groups-list',
            'html_class' => 'eea_mailchimp_groups_list',
            'layout_strategy' => new EE_Div_Per_Section_Spaced_Layout()
        );
        if ($this->_list_id && $this->_list_id !== '-1') {
            // Get saved group for this event (if there's one)
            $event_list_group = $this->_mc_controller->mci_event_selected_interests($this->_event_id);
            $user_groups = $this->_mc_controller->mci_get_users_groups($this->_list_id);

            if (! empty($user_groups)) {
                $categories = $this->_list_categories($event_list_group, $user_groups, $this->_list_id);
            } else {
                $categories['no_interests'] = new EE_Form_Section_HTML(EEH_HTML::p(esc_html__('No groups found for this List.', 'event_espresso'), 'no-groups-found-notice', 'important-notice'));
            }
            $options['subsections'] = $categories;
        } else {
            // No list - no interests data.
            $options['subsections']['no_data'] = new EE_Form_Section_HTML('');
        }

        return $options;
    }


    /**
     * List all interests in their categories.
     *
     * @access public
     * @param array   $event_list_group List of selected/saved interests.
     * @param array   $user_groups
     * @return array  List of interests in categories (form subsections).
     */
    protected function _list_categories($event_list_group, $user_groups)
    {
        $subsactions = array();
        foreach ($user_groups as $category) {
            $interests = $selected_intr = array();
            $category_interests = $this->_mc_controller->mci_get_interests($this->_list_id, $category['id']);
            $type = $category['type'];
            // Do not display if set as hidden.
            if ($type === 'hidden') {
                continue;
            }
            
            foreach ($category_interests as $interest) {
                $this->_all_interests[] = $interest_id = $interest['id'] . '-' . $interest['category_id'] . '-' . base64_encode($interest['name']);
                $interests[ $interest_id ] = $interest['name'];
                if (in_array($interest_id, $event_list_group)) {
                    $selected_intr[] = $interest_id;
                }
            }

            $section_params = array(
                'html_label_text' => $category['title'],
                'html_name'       => 'ee_mailchimp_groups[]',
                'html_class'      => 'spco-payment-method',
                'default'         => $selected_intr
            );
            // List the interests by their type.
            switch ($type) {
                case 'checkboxes':
                    $subsactions[ $interest['id'].'_'.$interest['category_id'] ] = new EE_Checkbox_Multi_Input(
                        $interests,
                        $section_params
                    );
                    break;
                case 'radio':
                    $section_params['html_name'] = 'ee_mailchimp_groups['.$category['id'].']';
                    $section_params['default'] = ( isset($selected_intr[0]) ) ? $selected_intr[0] : '';
                    $subsactions[ $interest['id'].'_'.$interest['category_id'] ] = new EE_Radio_Button_Input(
                        $interests,
                        $section_params
                    );
                    break;
                case 'dropdown':
                    $section_params['default'] = ( isset($selected_intr[0]) ) ? $selected_intr[0] : '';
                    $subsactions[ $interest['id'].'_'.$interest['category_id'] ] = new EE_Select_Input(
                        $interests,
                        $section_params
                    );
                    break;
                default:
                    $subsactions[ $interest['id'].'_'.$interest['category_id'] ] = new EE_Checkbox_Multi_Input(
                        $interests,
                        $section_params
                    );
                    break;
            }
        }

        // Need to pass all interests that are available.
        foreach ($this->_all_interests as $key => $intr) {
            $subsactions[] = new EE_Hidden_Input(array(
                'html_name' => 'ee_mc_list_all_interests[]',
                'html_id'   => 'ee-mc-intr-list-' . $key,
                'default'   => $intr
            ));
        }
        return $subsactions;
    }
}
