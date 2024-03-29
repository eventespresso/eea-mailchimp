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
     * @return EED_Mailchimp|EED_Module
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
     * @throws EE_Error
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
     * @throws EE_Error
     */
    public static function set_hooks_admin()
    {
        EED_Mailchimp::set_eemc_hooks();
        add_action('admin_enqueue_scripts', ['EED_Mailchimp', 'mailchimp_link_scripts_styles']);
        // 'MailChimp List' option
        add_action('add_meta_boxes', ['EED_Mailchimp', 'espresso_mailchimp_list_metabox']);
        add_action('save_post', ['EED_Mailchimp', 'espresso_mailchimp_save_event']);
        add_action(
            'AHEE__Extend_Events_Admin_Page___duplicate_event__after',
            ['EED_Mailchimp', 'espresso_mailchimp_duplicate_event'],
            10,
            2
        );
        // Ajax for MailChimp groups refresh
        add_action('wp_ajax_espresso_mailchimp_update_groups', ['EED_Mailchimp', 'espresso_mailchimp_update_groups']);
        // Ajax for MailChimp list fields refresh
        add_action(
            'wp_ajax_espresso_mailchimp_update_list_fields',
            ['EED_Mailchimp', 'espresso_mailchimp_update_list_fields']
        );
    }


    /**
     * @throws EE_Error
     */
    public static function set_eemc_hooks()
    {
        // Set defaults.
        $mc_config = EED_Mailchimp::setup_mc_defaults();
        // Hook into the EE _process_attendee_information.
        if ($mc_config->api_settings->submit_to_mc_when == 'attendee-information-end') {
            add_action(
                'AHEE__EE_Single_Page_Checkout__process_attendee_information__end',
                ['EED_Mailchimp', 'espresso_mailchimp_submit_to_mc'],
                10,
                2
            );
            remove_action(
                'AHEE__EE_SPCO_Reg_Step_Finalize_Registration__process_reg_step__completed',
                ['EED_Mailchimp', 'espresso_mailchimp_submit_to_mc']
            );
        } elseif (
            $mc_config->api_settings->submit_to_mc_when == 'reg-step-completed'
                  || $mc_config->api_settings->submit_to_mc_when == 'reg-step-approved'
        ) {
            add_action(
                'AHEE__EE_SPCO_Reg_Step_Finalize_Registration__process_reg_step__completed',
                ['EED_Mailchimp', 'espresso_mailchimp_submit_to_mc'],
                10,
                2
            );
            remove_action(
                'AHEE__EE_Single_Page_Checkout__process_attendee_information__end',
                ['EED_Mailchimp', 'espresso_mailchimp_submit_to_mc']
            );
        }
        // add a different log type
        add_filter('FHEE__EE_Enum_Text_Field___allowed_enum_options', ['EED_Mailchimp', 'add_log_type'], 10, 2);
    }


    /**
     *    Setup defaults.
     *
     * @return EE_Mailchimp_Config
     * @throws EE_Error
     */
    public static function setup_mc_defaults()
    {
        $mc_config = EED_Mailchimp::get_config();
        if (
            ! isset($mc_config->api_settings->submit_to_mc_when)
            || empty($mc_config->api_settings->submit_to_mc_when)
        ) {
            $mc_config->api_settings->submit_to_mc_when = 'reg-step-approved';
            EED_Mailchimp::update_config($mc_config);
        }
        return $mc_config;
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

    public static function set_config()
    {
        EED_Mailchimp::instance()->set_config_section('addons');
        EED_Mailchimp::instance()->set_config_name(EE_MailChimp::CONFIG_NAME);
        EED_Mailchimp::instance()->set_config_class(EE_MailChimp::CONFIG_CLASS);
    }


    /**
     * update_config
     *
     * @param EE_Mailchimp_Config $config
     * @return EE_Mailchimp_Config
     * @throws EE_Error
     */
    public static function update_config(EE_Mailchimp_Config $config)
    {
        return EED_Mailchimp::instance()->_update_config($config);
    }


    /**
     *    config
     *
     * @return EE_Config_Base|EE_Mailchimp_Config
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
     * @param WP $WP
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
    public static function mailchimp_link_scripts_styles(): void
    {
        wp_enqueue_style(
            'espresso_mailchimp_gen_styles',
            ESPRESSO_MAILCHIMP_URL . "assets/css/ee_mailchimp_styles.css",
            false,
            ESPRESSO_MAILCHIMP_VERSION
        );
        wp_enqueue_script(
            'espresso_mailchimp_base_scripts',
            ESPRESSO_MAILCHIMP_URL . 'assets/js/ee-mailchimp-base-scripts.js',
            false,
            ESPRESSO_MAILCHIMP_VERSION
        );
        do_action('AHEE__EED_Mailchimp__mailchimp_link_scripts_styles__end');
    }


    /**
     * An ajax to refresh the list of groups of the MailChimp List selected in the event.
     *
     * @return void
     * @throws EE_Error
     */
    public static function espresso_mailchimp_update_groups(): void
    {
        $mci_controller = new EE_MCI_Controller();
        $mci_data       = $_POST['mci_data'];
        echo $mci_controller->mci_list_mailchimp_groups($mci_data['event_id'], $mci_data['list_id']);
        exit;
    }


    /**
     * An ajax to refresh the  selected MailChimp List's merge fields.
     *
     * @return void
     * @throws EE_Error
     */
    public static function espresso_mailchimp_update_list_fields(): void
    {
        $mci_controller = new EE_MCI_Controller();
        $mci_data       = $_POST['mci_data'];
        echo $mci_controller->mci_list_mailchimp_fields($mci_data['event_id'], $mci_data['list_id']);
        exit;
    }


    /**
     * Submit new attendee information to MailChimp (if any MailChimp list to submit to selected for the current event).
     *
     * @param EED_Single_Page_Checkout $spc_obj Single Page Checkout (SPCO) Object.
     * @param                          $spc_data
     * @return void
     * @throws EE_Error
     * @throws ReflectionException
     */
    public static function espresso_mailchimp_submit_to_mc($spc_obj, $spc_data): void
    {
        $mc_config = EED_Mailchimp::instance()->config();
        if ($mc_config->api_settings->mc_active) {
            $mci_controller = new EE_MCI_Controller();
            $mci_controller->mci_submit_to_mailchimp($spc_obj);
        }
    }


    /**
     * Save the meta when the post is saved.
     *
     * @param int $EVT_ID The ID of the event being saved.
     * @return int
     * @throws EE_Error
     * @throws ReflectionException
     */
    public static function espresso_mailchimp_save_event(int $EVT_ID): int
    {
        // Nonce checks.
        $is_ok =
            EED_Mailchimp::espresso_mailchimp_authorization_checks(
                'espresso_mailchimp_list_box',
                'espresso_mailchimp_list_box_nonce'
            );
        // Auto-save? ...do nothing.
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return $EVT_ID;
        }
        if (! $is_ok) {
            return $EVT_ID;
        }
        // Got here so let's save the data.
        $mci_controller = new EE_MCI_Controller();
        $mci_controller->mci_save_metabox_contents($EVT_ID);
        return $EVT_ID;
    }


    /**
     * Duplicate the meta when the post is duplicated.
     *
     * @param EE_Event $new_event  The new EE Event object.
     * @param EE_Event $orig_event The original EE Event object.
     * @return void
     * @throws EE_Error
     * @throws ReflectionException
     */
    public static function espresso_mailchimp_duplicate_event($new_event, $orig_event): void
    {
        // Pull the original event's MailChimp relationships
        $mci_controller          = new EE_MCI_Controller();
        $mci_event_subscriptions = $mci_controller->mci_event_subscriptions($orig_event->ID());
        // Check if the original event is linked to a list
        if (! empty($mci_event_subscriptions['list'])) {
            if (! empty($mci_event_subscriptions['groups'])) {
                // Event is linked to a list, duplicate the mailchimp selected groups.
                foreach ($mci_event_subscriptions['groups'] as $group) {
                    $dupe_list_interest = EE_Event_Mailchimp_List_Group::new_instance(
                        [
                            'EVT_ID'                 => $new_event->ID(),
                            'AMC_mailchimp_list_id'  => $mci_event_subscriptions['list'],
                            'AMC_mailchimp_group_id' => $group,
                        ]
                    );
                    $dupe_list_interest->save();
                }
            } else {
                $dupe_list_group = EE_Event_Mailchimp_List_Group::new_instance(
                    [
                        'EVT_ID'                 => $new_event->ID(),
                        'AMC_mailchimp_list_id'  => $mci_event_subscriptions['list'],
                        'AMC_mailchimp_group_id' => -1,
                    ]
                );
                $dupe_list_group->save();
            }
            // Duplicate the mailchimp and event question relationships.
            foreach ($mci_event_subscriptions['qfields'] as $mailchimp_question => $event_question_id) {
                $dupe_qfield = EE_Question_Mailchimp_Field::new_instance(
                    [
                        'EVT_ID'                 => $new_event->ID(),
                        'QST_ID'                 => $event_question_id,
                        'QMC_mailchimp_field_id' => $mailchimp_question,
                    ]
                );
                $dupe_qfield->save();
            }
        }
    }


    /**
     * Add 'MailChimp List' option (metabox) to events admin (add/edit) page (if the API Key is valid).
     *
     * @access public
     * @param string $post_type Type of the post.
     * @return void
     */
    public static function espresso_mailchimp_list_metabox($post_type): void
    {
        $mc_config = EED_Mailchimp::instance()->config();
        // Is MC integration active and is espresso event page.
        if ($post_type == 'espresso_events' && $mc_config->api_settings->mc_active) {
            add_meta_box(
                'espresso_mailchimp_list',
                __('MailChimp List', 'event_espresso'),
                ['EED_Mailchimp', 'espresso_mailchimp_render_box_content'],
                $post_type,
                'side'
            );
        }
    }


    /**
     * Get 'MailChimp List' metabox contents.
     *
     * @access public
     * @param WP_Post $event The post object.
     * @return void
     * @throws EE_Error
     * @throws ReflectionException
     */
    public static function espresso_mailchimp_render_box_content($event): void
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
     * @param string $nonce_action Nonce action.
     * @param string $nonce_name   Nonce to verify.
     * @return bool  Is authorization OK.
     */
    public static function espresso_mailchimp_authorization_checks($nonce_action, $nonce_name): bool
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


    /**
     * @param                     $enum_options
     * @param EE_Model_Field_Base $field_obj
     * @return mixed
     * @throws EE_Error
     */
    public static function add_log_type($enum_options, EE_Model_Field_Base $field_obj)
    {
        if ($field_obj instanceof EE_Enum_Text_Field && $field_obj->get_name() === 'LOG_type') {
            $enum_options[ EED_Mailchimp::log_type ] = esc_html__('MailChimp', 'event_espresso');
        }
        return $enum_options;
    }


    /**
     *  Override other methods
     *
     * @param $a
     * @param $b
     * @return false
     */
    public function __set($a, $b)
    {
        return false;
    }


    /**
     * @param $a
     * @return false
     */
    public function __get($a)
    {
        return false;
    }


    /**
     * @param $a
     * @return false
     */
    public function __isset($a)
    {
        return false;
    }


    /**
     * @param $a
     * @return false
     */
    public function __unset($a)
    {
        return false;
    }


    /**
     * @return false
     */
    public function __clone()
    {
        return false;
    }


    /**
     * @return false
     */
    public function __wakeup()
    {
        return false;
    }


    /**
     * @return false
     */
    public function __destruct()
    {
        return false;
    }
}
