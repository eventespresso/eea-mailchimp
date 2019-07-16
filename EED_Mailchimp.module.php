<?php
/**
 * Class  EED_Mailchimp
 *
 * @package         Event Espresso
 * @subpackage      ee4-mailchimp
 */
class EED_Mailchimp extends EED_Module
{



    /**
     * Constant used in EEM_Change_Log for the value of LOG_type for mailchimp logs
     */
    const log_type = 'mailchimp';

    /**
     * @return EED_Mailchimp
     */
    public static function instance()
    {
        return parent::get_instance(__CLASS__);
    }

    /**
     * For hooking into EE Core, other modules, etc.
     *
     * @access public
     * @return void
     */
    public static function set_hooks()
    {
        EED_Mailchimp::set_eemc_hooks();
    }

    /**
     * For hooking into EE Admin Core and other modules, etc.
     *
     * @access public
     * @return void
     */
    public static function set_hooks_admin()
    {
        EED_Mailchimp::set_eemc_hooks();
        add_action('admin_enqueue_scripts', array( 'EED_Mailchimp', 'mailchimp_link_scripts_styles' ));
        // 'MailChimp List' option
        add_action('add_meta_boxes', array('EED_Mailchimp', 'espresso_mailchimp_list_metabox'));
        add_action('save_post', array('EED_Mailchimp', 'espresso_mailchimp_save_event'));
        add_action('AHEE__Extend_Events_Admin_Page___duplicate_event__after', array('EED_Mailchimp', 'espresso_mailchimp_duplicate_event'), 10, 2);
        // Ajax for MailChimp groups refresh
        add_action('wp_ajax_espresso_mailchimp_update_groups', array('EED_Mailchimp', 'espresso_mailchimp_update_groups'));
        // Ajax for MailChimp list fields refresh
        add_action('wp_ajax_espresso_mailchimp_update_list_fields', array('EED_Mailchimp', 'espresso_mailchimp_update_list_fields'));
    }


    public static function set_eemc_hooks()
    {
        // Set defaults.
        $mc_config = EED_Mailchimp::setup_mc_defaults();
        // Hook into the EE _process_attendee_information.
        if ($mc_config->api_settings->submit_to_mc_when == 'attendee-information-end') {
            add_action('AHEE__EE_Single_Page_Checkout__process_attendee_information__end', array('EED_Mailchimp', 'espresso_mailchimp_submit_to_mc'), 10, 2);
            remove_action('AHEE__EE_SPCO_Reg_Step_Finalize_Registration__process_reg_step__completed', array('EED_Mailchimp', 'espresso_mailchimp_submit_to_mc'));
        } elseif ($mc_config->api_settings->submit_to_mc_when == 'reg-step-completed' || $mc_config->api_settings->submit_to_mc_when == 'reg-step-approved') {
            add_action('AHEE__EE_SPCO_Reg_Step_Finalize_Registration__process_reg_step__completed', array('EED_Mailchimp', 'espresso_mailchimp_submit_to_mc'), 10, 2);
            remove_action('AHEE__EE_Single_Page_Checkout__process_attendee_information__end', array('EED_Mailchimp', 'espresso_mailchimp_submit_to_mc'));
        }
        // add a different log type
        add_filter('FHEE__EE_Enum_Text_Field___allowed_enum_options', array( 'EED_Mailchimp', 'add_log_type' ), 10, 2);

        // Add Opt-in question. 
        if ($mc_config->api_settings->subscribe_att_choice === 'mc_att_choice_subscribe' ) { 
            add_filter('FHEE__EEM_Question__system_questions_allowed_in_system_question_group__return', array('EED_Mailchimp', 'allow_mc_extra_in_system'), 10, 2 ); 
            add_filter('FHEE__EE_SPCO_Reg_Step_Attendee_Information___save_registration_form_input', array('EED_Mailchimp', 'save_registration_mc_optin_form_input'), 10, 5);
            EED_Mailchimp::add_mc_extra_question(); 
        }
    }


    /**
     *    Setup defaults.
     *
     * @return EE_Mailchimp_Config
     */
    public static function setup_mc_defaults()
    {
        $config_changed = false;
        $mc_config = EED_Mailchimp::get_config();
        // Set the default value for when subscribe the registrant to when the registrations has been approved. 
        if (! isset($mc_config->api_settings->submit_to_mc_when) || empty($mc_config->api_settings->submit_to_mc_when)) {
            $mc_config->api_settings->submit_to_mc_when = 'reg-step-approved';
            $config_changed = true;
        }
        // Set the default for the "subscribe me" MC option. 
        if (! isset($mc_config->api_settings->subscribe_att_choice) ) { 
            $mc_config->api_settings->subscribe_att_choice = 'mc_always_subscribe'; 
            $config_changed = true;
        }
        if ($config_changed) {
            EED_Mailchimp::update_config($mc_config);
        }
        return $mc_config;
    }

    /** 
     * Add MC opt-in question to EE question list. 
     * 
     * @return void 
     */ 
    public static function add_mc_extra_question() {
        // Check if there is already an mc-optin question in the system.
        $question = EEM_Question::instance()->get_one( 
            array( 
                array( 
                    'QST_system' => 'mc-optin' 
                ), 
               'default_where_conditions' => 'none'
            )
        );
        // If we already have a question then this option was set before and the question should be in trash.
        if ( $question instanceof EE_Question ) {
            $question->set_deleted(false); 
        } else { 
            // Create the MailChimp 'Opt-in' question.
            $question = EE_Question::new_instance( array( 
                'QST_display_text' => esc_html__('Subscribe to newsletter', 'event_espresso'), 
                'QST_system' => 'mc-optin', 
                'QST_admin_label' => esc_html__('Opt-in - System Question', 'event_espresso'), 
                'QST_type' => EEM_Question::QST_type_checkbox, 
                'QST_required' => false, 
                'QST_order' => 12 
            ));
            // Add an option to the question. 
            $qst_answer = EE_Question_Option::new_instance( 
                array( 'QSO_value' => esc_html__('Subscribe to newsletter', 'event_espresso'), 
                'QSO_system' => 'mc-optin-me-ok', 
                'QSO_deleted' => false ) 
            ); 
            $question->add_option( $qst_answer );
        }
        // A new question would have been created, or set_deleted would have been set to false so we need to save
        $question->save();
    }
 
 
    /** 
     * Remove MC opt-in question from EE question list. 
     * 
     * @return void 
     */ 
    public static function remove_mc_extra_question() {
        $question = EEM_Question::instance()->get_one(array(array('QST_system' => 'mc-optin')));
        if ($question instanceof EE_Question) {
            $question->set_deleted(true);
            $question->save();
        }
    }

 
    /** 
     * Allow MC opt-in question in the system QTS Group. 
     * 
     * @param $question_system_ids 
     * @param $system_question_group_id 
     * @return void 
     */ 
    public static function allow_mc_extra_in_system($question_system_ids, $system_question_group_id) { 
        $question_system_ids[] = 'mc-optin'; 
        return $question_system_ids; 
    } 


    /** 
     * Add a default option for Opt-in form question. 
     * 
     * @param $processed
     * @param $registration
     * @param $form_input
     * @param $input_value
     * @param $attendee_info_step
     * @return bool
     */ 
    public static function save_registration_mc_optin_form_input( $processed, $registration, $form_input, $input_value, $attendee_info_step) {
        //Only process the mc-optin form intput
        if($form_input == 'mc-optin') {
            $answers = $registration->answers();
            $answer_cahce_id = $form_input . '-' . $registration->reg_url_link();
            if(isset($answers[$answer_cahce_id])){
                $answer = $answers[$answer_cahce_id];
                $answer->set_value($input_value);
                $answer->save();
            }
            //Don't prevent registrations regardless of issues with this field.
            return true;
        }
        return $processed;
    }


    /**
     * get_config
     *
     * @return EE_Mailchimp_Config
     */
    public static function get_config()
    {
        if (EED_Mailchimp::instance()->config_name() === '') {
            EED_Mailchimp::set_config();
        }
        return EED_Mailchimp::instance()->config();
    }



    /**
     *    _set_config
     *
     * @return EE_Mailchimp_Config
     */
    public static function set_config()
    {
        EED_Mailchimp::instance()->set_config_section('addons');
        EED_Mailchimp::instance()->set_config_name(EE_MailChimp::CONFIG_NAME);
        EED_Mailchimp::instance()->set_config_class(EE_MailChimp::CONFIG_CLASS);
    }



    /**
     * update_config
     *
     * @param \EE_Mailchimp_Config $config
     * @return \EE_Mailchimp_Config
     * @throws \EE_Error
     */
    public static function update_config(EE_Mailchimp_Config $config)
    {
        return EED_Mailchimp::instance()->_update_config($config);
    }



    /**
     *    config
     *
     * @return EE_Mailchimp_Config
     */
    public function config()
    {
        EED_Mailchimp::set_config();
        return parent::config();
    }



    /**
     * Run initial module setup.
     *
     * @access public
     * @param WP  $WP
     * @return void
     */
    public function run($WP)
    {
    }

    /**
     * Load the MCI scripts and styles.
     *
     * @access public
     * @return void
     */
    public static function mailchimp_link_scripts_styles()
    {
        wp_enqueue_style('espresso_mailchimp_gen_styles', ESPRESSO_MAILCHIMP_URL . "assets/css/ee_mailchimp_styles.css", false, ESPRESSO_MAILCHIMP_VERSION);
        wp_enqueue_script('espresso_mailchimp_base_scripts', ESPRESSO_MAILCHIMP_URL . 'assets/js/ee-mailchimp-base-scripts.js', false, ESPRESSO_MAILCHIMP_VERSION);
        do_action('AHEE__EED_Mailchimp__mailchimp_link_scripts_styles__end');
    }

    /**
     * An ajax to refresh the list of groups of the MailChimp List selected in the event.
     *
     * @return void
     */
    public static function espresso_mailchimp_update_groups()
    {
        $mci_controller = new EE_MCI_Controller();
        $mci_data = $_POST['mci_data'];
        echo $mci_controller->mci_list_mailchimp_groups($mci_data['event_id'], $mci_data['list_id']);
        exit;
    }

    /**
     * An ajax to refresh the  selected MailChimp List's merge fields.
     *
     * @return void
     */
    public static function espresso_mailchimp_update_list_fields()
    {
        $mci_controller = new EE_MCI_Controller();
        $mci_data = $_POST['mci_data'];
        echo $mci_controller->mci_list_mailchimp_fields($mci_data['event_id'], $mci_data['list_id']);
        exit;
    }

    /**
     * Submit new attendee information to MailChimp (if any MailChimp list to submit to selected for the current event).
     *
     * @param EED_Single_Page_Checkout $spc_obj  Single Page Checkout (SPCO) Object.
     * @param array/EE_Payment $spc_data  Valid data from the Attendee Information Registration form / EE_Payment.
     * @return void
     */
    public static function espresso_mailchimp_submit_to_mc($spc_obj, $spc_data)
    {
        $mc_config = EED_Mailchimp::instance()->config();
        if ($mc_config->api_settings->mc_active) {
            $mci_controller = new EE_MCI_Controller();
            $mci_controller->mci_submit_to_mailchimp($spc_obj, $spc_data);
        }
    }

    /**
     * Save the meta when the post is saved.
     *
     * @param int $event_id The ID of the event being saved.
     * @return int
     */
    public static function espresso_mailchimp_save_event($event_id)
    {
        // Nonce checks.
        $is_ok = EED_Mailchimp::espresso_mailchimp_authorization_checks('espresso_mailchimp_list_box', 'espresso_mailchimp_list_box_nonce');
// Auto-save? ...do nothing.
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return $event_id;
        }
        if (! $is_ok) {
            return $event_id;
        }
        // Got here so let's save the data.
        $mci_controller = new EE_MCI_Controller();
        $mci_controller->mci_save_metabox_contents($event_id);
        return $event_id;
    }

    /**
     * Duplicate the meta when the post is duplicated.
     *
     * @param EE_Event $new_event The new EE Event object.
     * @param EE_Event $orig_event The original EE Event object.
     * @return void
     */
    public static function espresso_mailchimp_duplicate_event($new_event, $orig_event)
    {
        // Pull the original event's MailChimp relationships
        $mci_controller = new EE_MCI_Controller();
        $mci_event_subscriptions = $mci_controller->mci_event_subscriptions($orig_event->ID());
        // Check if the original event is linked to a list
        if (!empty($mci_event_subscriptions['list'])) {
            if (!empty($mci_event_subscriptions['groups'])) {
                // Event is linked to a list, duplicate the mailchimp selected groups.
                foreach ($mci_event_subscriptions['groups'] as $group) {
                    $dupe_list_interest = EE_Event_Mailchimp_List_Group::new_instance(
                        array(
                            'EVT_ID'                 => $new_event->ID(),
                            'AMC_mailchimp_list_id'  => $mci_event_subscriptions['list'],
                            'AMC_mailchimp_group_id' => $group,
                        )
                    );
                    $dupe_list_interest->save();
                }
            } else {
                $dupe_list_group = EE_Event_Mailchimp_List_Group::new_instance(
                    array(
                        'EVT_ID'                 => $new_event->ID(),
                        'AMC_mailchimp_list_id'  => $mci_event_subscriptions['list'],
                        'AMC_mailchimp_group_id' => -1,
                    )
                );
                $dupe_list_group->save();
            }
            // Duplicate the mailchimp and event question relationships.
            foreach ($mci_event_subscriptions['qfields'] as $mailchimp_question => $event_question_id) {
                $dupe_qfield = EE_Question_Mailchimp_Field::new_instance(
                    array(
                        'EVT_ID'                 => $new_event->ID(),
                        'QST_ID'                 => $event_question_id,
                        'QMC_mailchimp_field_id' => $mailchimp_question,
                    )
                );
                $dupe_qfield->save();
            }
        }
    }

    /**
     * Add 'MailChimp List' option (metabox) to events admin (add/edit) page (if the API Key is valid).
     *
     * @access public
     * @param string $post_type  Type of the post.
     * @return void
     */
    public static function espresso_mailchimp_list_metabox($post_type)
    {
        $mc_config = EED_Mailchimp::instance()->config();
// Is MC integration active and is espresso event page.
        if ($post_type == 'espresso_events' && $mc_config->api_settings->mc_active) {
            add_meta_box('espresso_mailchimp_list', __('MailChimp List', 'event_espresso'), array( 'EED_Mailchimp', 'espresso_mailchimp_render_box_content' ), $post_type, 'side', 'default');
        }
    }

    /**
     * Get 'MailChimp List' metabox contents.
     *
     * @access public
     * @param WP_Post $event  The post object.
     * @return void
     */
    public static function espresso_mailchimp_render_box_content($event)
    {
        $mci_controller = new EE_MCI_Controller();
// Add an nonce field.
        wp_nonce_field('espresso_mailchimp_list_box', 'espresso_mailchimp_list_box_nonce');
        echo $mci_controller->mci_set_metabox_contents($event);
    }

    /**
     * Do authorization checks on save_post.
     *
     * @access public
     * @param string $nonce_action  Nonce action.
     * @param string $nonce_name  Nonce to verify.
     * @return bool  Is authorization OK.
     */
    public static function espresso_mailchimp_authorization_checks($nonce_action, $nonce_name)
    {
        // Check if our nonce is set.
        if (! isset($_POST[ $nonce_name ])) {
            return false;
        }
        $nonce = $_POST[ $nonce_name ];
// Verify that the nonce is valid.
        if (! wp_verify_nonce($nonce, $nonce_action)) {
            return false;
        }
        return true;
    }

    public static function add_log_type($enum_options, EE_Model_Field_Base $field_obj)
    {
        if ($field_obj instanceof EE_Enum_Text_Field
            && $field_obj->get_name() === 'LOG_type') {
            $enum_options[ EED_Mailchimp::log_type ] = esc_html__('MailChimp', 'event_espresso');
        }
        return $enum_options;
    }

    /**
     *  Override other methods
     */
    public function __set($a, $b)
    {
        return false;
    }
    public function __get($a)
    {
        return false;
    }
    public function __isset($a)
    {
        return false;
    }
    public function __unset($a)
    {
        return false;
    }
    public function __clone()
    {
        return false;
    }
    public function __wakeup()
    {
        return false;
    }
    public function __destruct()
    {
        return false;
    }
}
