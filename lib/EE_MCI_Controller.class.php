<?php

use EventEspresso\core\exceptions\InvalidDataTypeException;
use EventEspresso\core\exceptions\InvalidInterfaceException;
use MailchimpMarketing\ApiClient as MailchimpAPI;

/**
 * Class  EE_MCI_Controller
 * Event Espresso MailChimp logic implementing. Intermediary between this integration and the MailChimp API.
 *
 * @package         Event Espresso
 * @subpackage      ee4-mailchimp
 */
class EE_MCI_Controller
{
    /**
     * Key of the extra meta row that stores whether or not the event has been verified to work with MC API v3.
     *
     * @const string
     */
    const UPDATED_TO_API_V3 = 'updated_to_v3_of_mc_api_extra_meta_key';

    /**
     * @var EE_Mailchimp_Config $_config
     */
    private $_config;

    /**
     * @var string $_api_key
     */
    private $_api_key = '';

    /**
     * Error details.
     *
     * @var array $mcapi_error
     */
    private $mcapi_error = [];

    /**
     * MailChimp API Object.
     *
     * @var MailchimpAPI $MailChimp
     */
    private $MailChimp;

    /**
     * The selected List ID.
     *
     * @var string $list_id
     */
    private $list_id = '';


    /**
     * Class constructor
     *
     * @param string $api_key
     * @throws EE_Error|ReflectionException
     */
    public function __construct(string $api_key = '')
    {
        do_action('AHEE__EE_MCI_Controller__class_constructor__init_controller');
        $this->_config = EED_Mailchimp::instance()->config();
        // Verify API key.
        $api_key = ! empty($api_key) ? $api_key : $this->_config->api_settings->api_key;
        if ($this->mci_is_api_key_valid($api_key)) {
            $this->_api_key = $api_key;
        }
    }


    /**
     * Validate the MailChimp API key. If key not provided then the one that is saved in the settings will be checked.
     *
     * @param string $api_key MailChimp API Key.
     * @return bool           Is Key valid or not
     * @throws EE_Error|ReflectionException
     */
    public function mci_is_api_key_valid(string $api_key = ''): bool
    {
        do_action('AHEE__EE_MCI_Controller__mci_is_api_key_valid__start');
        // Make sure API key only has one '-'
        $exp_key = explode('-', $api_key);
        if (! is_array($exp_key) || count($exp_key) != 2) {
            do_action('AHEE__EE_MCI_Controller__mci_is_api_key_valid__api_key_error');
            $this->set_error(false);
            return false;
        }
        // Check if key is live/acceptable by API.
        try {
            $this->MailChimp = new MailchimpAPI();
            $this->MailChimp->setConfig([
                'apiKey' => $api_key,
                'server' => $exp_key[1],
            ]);
            $reply = $this->MailChimp->ping->get();
        } catch (Exception $e) {
            do_action('AHEE__EE_MCI_Controller__mci_is_api_key_valid__api_key_error');
            $this->set_error($e);
            $this->MailChimp = null;
            return false;
        }
        // If a reply is present, then let's process that.
        if (empty($reply->health_status) || ! str_contains($reply->health_status, 'Chimpy')) {
            do_action('AHEE__EE_MCI_Controller__mci_is_api_key_valid__api_key_error');
            $this->set_error($reply);
            $this->MailChimp = null;
            return false;
        }
        do_action('AHEE__EE_MCI_Controller__mci_is_api_key_valid__api_key_ok');
        return true;
    }


    /**
     * Retrieve EE_Registration and EE_Transaction objects from incoming data.
     *
     * @param mixed $spco_obj | EE_SPCO_Reg_Step_Attendee_Information $spco_obj
     * @return array ( EE_Transaction, EE_Registration[] )
     * @throws EE_Error
     * @throws ReflectionException
     */
    protected function _mci_get_registrations($spco_obj): array
    {
        $spco_registrations = [];
        $spco_transaction   = false;
        // what kind of SPCO object did we receive?
        if ($spco_obj instanceof EE_SPCO_Reg_Step_Attendee_Information) {
            // for EE versions > 4.6
            if (
                $spco_obj->checkout instanceof EE_Checkout
                && $spco_obj->checkout->transaction
                   instanceof
                   EE_Transaction
            ) {
                $spco_registrations = $spco_obj->checkout->transaction->registrations(
                    $spco_obj->checkout->reg_cache_where_params,
                    true
                );
                $spco_transaction   = $spco_obj->checkout->transaction;
            }
        } elseif ($spco_obj instanceof EED_Single_Page_Checkout) {
            $transaction = EE_Registry::instance()->SSN->get_session_data('transaction');
            // for EE versions < 4.6
            if ($transaction instanceof EE_Transaction) {
                $spco_registrations = $transaction->registrations([], true);
                $spco_transaction   = $transaction;
            }
        } elseif ($spco_obj instanceof EE_Checkout) {
            $spco_registrations = $spco_obj->transaction->registrations($spco_obj->reg_cache_where_params, true);
            $spco_transaction   = $spco_obj;
        }
        return ['registrations' => $spco_registrations, 'transaction' => $spco_transaction];
    }


    /**
     * Subscribe new attendee to MailChimp list selected for current event.
     * Depending by what hook this was called (process_attendee_information__end or
     * update_txn_based_on_payment__successful) the:
     * $spc_obj could be an instanceof: EE_SPCO_Reg_Step_Attendee_Information, EED_Single_Page_Checkout or EE_Checkout.
     *
     * @param $spc_obj
     * @return void
     * @throws EE_Error
     * @throws ReflectionException
     */
    public function mci_submit_to_mailchimp($spc_obj): void
    {
        // Do not submit if the key is not valid or there is no valid submit data.
        if ($this->MailChimp instanceof MailchimpAPI && ! empty($spc_obj)) {
            $spco_data        = $this->_mci_get_registrations($spc_obj);
            $registrations    = $spco_data['registrations'];
            $spco_transaction = $spco_data['transaction'];
            do_action('AHEE__EE_MCI_Controller__mci_submit_to_mailchimp__start', $spco_transaction, $registrations);
            // now loop through registrations to get the related attendee objects
            if (! empty($registrations)) {
                $registered_attendees = [];
                foreach ($registrations as $registration) {
                    if ($registration instanceof EE_Registration) {
                        $EVT_ID     = $registration->event_ID();
                        $event_list = $this->mci_event_list($EVT_ID);
                        // If no list selected for this event then skip the subscription.
                        if (empty($event_list) || intval($event_list) === -1) {
                            continue;
                        }
                        // Pull the EE_Attendee object for the registration
                        $attendee = $registration->attendee();
                        // If no EE_Attendee object, skip the subscribe call to MailChimp.
                        if (! $attendee instanceof EE_Attendee) {
                            continue;
                        }
                        // Check if the DB data can be safely used with current API.
                        $this->is_db_data_api_compatible($EVT_ID, $event_list);
                        $need_reg_status = $reg_approved = false;
                        $mc_config       = EED_Mailchimp::get_config();
                        if ($mc_config->api_settings->submit_to_mc_when === 'reg-step-approved') {
                            $need_reg_status = true;
                            $reg_status      = $registration->status_ID();
                            if ($reg_status === EEM_Registration::status_id_approved) {
                                $reg_approved = true;
                            }
                        }
                        if (! in_array($attendee->email(), $registered_attendees)
                            && (! $need_reg_status || $reg_approved)
                        ) {
                            $this->formRequestAndSubmit($registration, $attendee, $EVT_ID, $event_list);
                            $registered_attendees[] = $attendee->email();
                        }
                    }
                }
            }
        }
    }


    /**
     * Gather all the required request data and submit to MC.
     *
     * @param EE_Registration $registration
     * @param                 $attendee
     * @param int             $EVT_ID
     * @param                 $event_list
     * @return void
     * @throws EE_Error
     * @throws ReflectionException
     */
    protected function formRequestAndSubmit(EE_Registration $registration, $attendee, int $EVT_ID, $event_list): void
    {
        $att_email      = $attendee->email();
        $opt_in         = $this->_config->api_settings->skip_double_optin ?? true;
        $emails_type    = $this->_config->api_settings->emails_type ?? 'text';
        $subscribe_args = [
            'email_address' => $att_email,
        ];
        // Group vars
        $subscribe_args = $this->_add_event_group_vars_to_subscribe_args($EVT_ID, $subscribe_args);
        // Question fields
        $subscribe_args = $this->_add_registration_question_answers_to_subscribe_args(
            $registration,
            $EVT_ID,
            $subscribe_args
        );
        // For backwards compatibility reasons only (for this next filter below)
        $subscribe_args['merge_vars'] = $subscribe_args['merge_fields'];
        unset($subscribe_args['merge_fields']);
        // filter it
        $subscribe_args = apply_filters(
            'FHEE__EE_MCI_Controller__mci_submit_to_mailchimp__subscribe_args',
            $subscribe_args,
            $registration,
            $EVT_ID
        );
        // Old version used 'merge_vars' but API v3 calls them 'merge_fields'
        // Remove any merge_vars with a null value as the API rejects them.
        $subscribe_args['merge_fields'] = array_filter($subscribe_args['merge_vars'], function ($merge_var) {
            return ! is_null($merge_var);
        });
        unset($subscribe_args['merge_vars']);
        // Verify merge_fields and interests aren't empty, and if they are they need to be stdClasses so that they
        // become JSON objects still
        if (empty($subscribe_args['merge_fields'])) {
            $subscribe_args['merge_fields'] = new stdClass();
        }
        if (empty($subscribe_args['interests'])) {
            $subscribe_args['interests'] = new stdClass();
        }
        $member_subscribed = false;
        try {
            // Get member info if member exists.
            $members_list = $this->MailChimp->searchMembers->search(
                $att_email,
                null,
                null,
                $event_list
            );
            if (! empty($members_list->exact_matches) && ! empty($members_list->exact_matches->members)) {
                $member = $members_list->exact_matches->members[0];
                if (! empty($member->email_address)
                    && ! empty($member->status)
                    && ! preg_match(
                        '/^(4|5)\d{2}$/',
                        $member->status
                    )
                ) {
                    $subscribe_args['status'] = $member->status;
                    $member_subscribed = true;
                }
            }
            // What type of emails we want to send ?
            $subscribe_args['email_type'] = $emails_type;
        } catch (Exception $e) {
            $this->set_error($e);
            return;
        }
        if (empty($subscribe_args['status'])) {
            // Send opt-in emails ?
            if ($opt_in) {
                $subscribe_args['status'] = 'pending';
            } else {
                $subscribe_args['status'] = 'subscribed';
            }
        }
        try {
            // Add/update member.
            if ($member_subscribed) {
                $put_member = $this->MailChimp->lists->updateListMember(
                    $event_list,
                    md5(strtolower($att_email)),
                    $subscribe_args
                );
            } else {
                $put_member = $this->MailChimp->lists->addListMember(
                    $event_list,
                    $subscribe_args
                );
            }
            if (! is_object($put_member) || ! empty($put_member->errors)) {
                $this->updateListMemberError($put_member, $registration, $event_list);
                return;
            }
            // All good.
            do_action(
                'FHEE__EE_MCI_Controller__mci_submit_to_mailchimp__success',
                $registration,
                $att_email,
                $this->MailChimp,
                $subscribe_args
            );
        } catch (Exception $e) {
            $this->set_error($e);
        }
    }


    /**
     * Handle error on Update List Member.
     *
     * @param mixed           $put_member
     * @param EE_Registration $registration
     * @param                 $event_list
     * @return void
     * @throws EE_Error
     * @throws ReflectionException
     */
    protected function updateListMemberError($put_member, EE_Registration $registration, $event_list): void
    {
        $this->set_error($put_member);
        $errors = '';
        if (! empty($put_member->errors) && is_array($put_member->errors)) {
            $errs = [];
            foreach ($put_member->errors as $err) {
                $err_msg = isset($err['field'])
                    ? sprintf(
                        esc_html__(
                            'MailChimp field tagged %1$s had the error: ',
                            'event_espresso'
                        ),
                        $err['field']
                    )
                    : '';
                $err_msg .= $err['message'] ?? esc_html__('No error mentioned', 'event_espresso');
                $errs[]  = $err_msg;
            }
            $errors = implode(', ', $errs);
        }
        $evt_obj       = $registration->event();
        $evt_permalink = ($evt_obj instanceof EE_Event) ? $evt_obj->get_permalink() : '#';
        $notice_msg    = sprintf(
            esc_html__(
                'This registration could not be subscribed to a MailChimp List with ID: %1$s. There were errors regarding the following: %2$s. 
                Any mandatory or multi-choice fields that are in this MailChimp list require to be paired with Event questions (in this case %3$s event) that correspond by type and possibly by having the same answer values. 
                If you have further problems please contact support.',
                'event_espresso'
            ),
            $event_list,
            $errors,
            '<a href="' . $evt_permalink . '">' . $registration->event_name() . '</a>'
        );
        // Notify the admin if there was a problem with the subscription.
        EEM_Change_Log::instance()->log(
            EED_Mailchimp::log_type,
            $notice_msg,
            $registration
        );
    }


    /**
     * _add_event_group_vars_to_subscribe_args
     *
     * @param int   $EVT_ID
     * @param array $subscribe_args
     * @return array
     * @throws EE_Error
     * @throws ReflectionException
     */
    protected function _add_event_group_vars_to_subscribe_args(int $EVT_ID = 0, array $subscribe_args = []): array
    {
        $event_groups = $this->mci_event_list_group($EVT_ID);
        if (! empty($event_groups)) {
            foreach ($event_groups as $event_group) {
                $subscribe_args = $this->_process_event_group_subscribe_args($event_group, $subscribe_args);
            }
        }
        return $subscribe_args;
    }


    /**
     * _process_event_group_subscribe_args
     *
     * @param string $event_group
     * @param array  $subscribe_args
     * @return array  List of MailChimp lists.
     */
    protected function _process_event_group_subscribe_args(string $event_group = '', array $subscribe_args = []): array
    {
        // Initialise the interests array if it hasn't been already.
        if (empty($subscribe_args['interests'])) {
            $subscribe_args['interests'] = [];
        }
        $grouping = explode('-', $event_group);
        // Add selected interests to the subscribe_args.
        if (isset($grouping[3]) && $grouping[3] === 'true') {
            $subscribe_args['interests'][ $grouping[0] ] = true;
        }
        return $subscribe_args;
    }


    /**
     * _add_registration_question_answers_to_subscribe_args
     *
     * @param EE_Registration $registration
     * @param int             $EVT_ID
     * @param array           $subscribe_args
     * @return array
     * @throws InvalidInterfaceException
     * @throws InvalidDataTypeException
     * @throws EE_Error
     * @throws InvalidArgumentException
     * @throws ReflectionException
     */
    protected function _add_registration_question_answers_to_subscribe_args(
        EE_Registration $registration,
        int             $EVT_ID = 0,
        array $subscribe_args = []
    ): array {
        if (! is_array($subscribe_args)) {
            throw new EE_Error(__('The MailChimp Subscriber arguments array is malformed!', 'event_espresso'));
        }
        if (! isset($subscribe_args['merge_fields'])) {
            $subscribe_args['merge_fields'] = [];
        }

        $mc_field_to_ee_q_map = $this->mci_event_list_question_fields($EVT_ID);
        foreach ($mc_field_to_ee_q_map as $mc_field_code => $qst_id) {
            $value                                            =
                EEM_Answer::instance()->get_answer_value_to_question($registration, $qst_id, true);
            $subscribe_args['merge_fields'][ $mc_field_code ] = $value;
        }
        return $subscribe_args;
    }


    /**
     * Retrieve all of the lists defined for your user account.
     *
     * @return array  List of MailChimp lists.
     * @throws EE_Error|ReflectionException
     */
    public function mci_get_users_lists(): array
    {
        do_action('AHEE__EE_MCI_Controller__mci_get_users_lists__start');
        $parameters = apply_filters(
            'FHEE__EE_MCI_Controller__mci_get_users_lists__list_params',
            ['fields' => ['lists.id', 'lists.name'], 'count' => 50],
            $this
        );
        try {
            $reply = $this->MailChimp->lists->getAllLists($parameters['fields'], null, $parameters['count']);
        } catch (Exception $e) {
            $this->set_error($e);
            return [];
        }
        // The list of requested items might just be empty or there might be an error response.
        if (empty($reply->lists)) {
            $this->set_error($reply);
            return [];
        }
        return (array) $reply->lists;
    }


    /**
     * Get the list of Interest Categories for a given list.
     *
     * @param string $list_id The ID of the List.
     * @return array  List of MailChimp groups of selected List.
     * @throws EE_Error|ReflectionException
     */
    public function mci_get_users_groups(string $list_id): array
    {
        do_action('AHEE__EE_MCI_Controller__mci_get_users_groups__start');
        $parameters = apply_filters(
            'AHEE__EE_MCI_Controller__mci_get_users_groups__parameters',
            ['exclude_fields' => ['_links', 'categories._links'], 'count' => 50]
        );
        if (empty($list_id)) {
            $list_id = $this->list_id;
        }
        try {
            $reply = $this->MailChimp->lists->getListInterestCategories(
                $list_id,
                null,
                $parameters['exclude_fields'],
                $parameters['count']
            );
        } catch (Exception $e) {
            $this->set_error($e);
            return [];
        }
        if (empty($reply->categories)) {
            $this->set_error($reply);
            return [];
        }
        return (array) $reply->categories;
    }


    /**
     * Get the list of interests for a specific MailChimp list.
     *
     * @param string $list_id     The ID of the List.
     * @param string $category_id The ID of the interest category.
     * @return array  List of MailChimp interests of selected List.
     * @throws EE_Error|ReflectionException
     */
    public function mci_get_interests(string $list_id, string $category_id): array
    {
        do_action('AHEE__EE_MCI_Controller__mci_get_interests__start');
        $parameters = apply_filters(
            'AHEE__EE_MCI_Controller__mci_get_interests__parameters',
            ['fields' => ['interests'], 'exclude_fields' => ['interests._links'], 'count' => 100]
        );
        if (empty($list_id)) {
            $list_id = $this->list_id;
        }
        try {
            $reply = $this->MailChimp->lists->listInterestCategoryInterests(
                $list_id,
                $category_id,
                $parameters['fields'],
                $parameters['exclude_fields'],
                $parameters['count']
            );
        } catch (Exception $e) {
            $this->set_error($e);
            return [];
        }
        if (empty($reply->interests)) {
            $this->set_error($reply);
            return [];
        }
        return (array) $reply->interests;
    }


    /**
     * Get the list of merge tags for a given list.
     *
     * @param string $list_id The ID of the List.
     * @return array   MailChimp List of merge tags.
     * @throws EE_Error
     */
    public function mci_get_list_merge_vars(string $list_id): array
    {
        do_action('AHEE__EE_MCI_Controller__mci_get_list_merge_vars__start');
        $parameters = apply_filters(
            'AHEE__EE_MCI_Controller__mci_get_list_merge_vars__parameters',
            ['fields' => ['merge_fields'], 'exclude_fields' => ['_links', 'merge_fields._links'], 'count' => 50]
        );
        if (empty($list_id)) {
            $list_id = $this->list_id;
        }
        try {
            $reply = $this->MailChimp->lists->getListMergeFields(
                $list_id,
                $parameters['fields'],
                $parameters['exclude_fields'],
                $parameters['count']
            );
        } catch (Exception $e) {
            $this->set_error($e);
            return [];
        }
        if (empty($reply->merge_fields)) {
            $this->set_error($reply);
            return [];
        }
        return (array) $reply->merge_fields;
    }


    /**
     * Set up 'MailChimp List Integration' meta-box section contents.
     *
     * @param WP_Post $event The post object.
     * @return string
     * @throws EE_Error
     * @throws ReflectionException
     */
    public function mci_set_metabox_contents(WP_Post $event): string
    {
        // Verify the API key.
        if ($this->MailChimp instanceof MailchimpAPI) {
            // Check if the DB data can be safely used with current API.
            $this->is_db_data_api_compatible($event->ID);
            // Get saved list for this event (if there's one)
            $this->list_id = $this->mci_event_list($event->ID);
            $metabox_obj   = new EE_MC_Metabox_Form($this, $event->ID, $this->list_id);
            return $metabox_obj->get_html_and_js();
        } else {
            $error_section = new EE_Form_Section_HTML(
                EEH_HTML::div(
                    EEH_HTML::span(
                        esc_html__('Invalid MailChimp API.', 'event_espresso'),
                        'important_mc_notice',
                        'important-notice'
                    ) .
                    EEH_HTML::br() .
                    esc_html__('Please visit the ', 'event_espresso') .
                    EEH_HTML::link(
                        admin_url('admin.php?page=mailchimp'),
                        esc_html__('MailChimp Admin Page ', 'event_espresso')
                    ) .
                    esc_html__('to correct the issue.', 'event_espresso'),
                    'no-lists-found-notice',
                    'espresso_mailchimp_integration_metabox'
                )
            );
            return $error_section->get_html_and_js();
        }
    }


    /**
     * Save the contents of 'MailChimp List Integration' meta-box.
     *
     * @param int $EVT_ID An ID on the Event.
     * @return void
     * @throws EE_Error
     * @throws ReflectionException
     */
    public function mci_save_metabox_contents(int $EVT_ID): void
    {
        // Clear MailChimp data on the current event and then save the new data.
        $lg_exists = EEM_Event_Mailchimp_List_Group::instance()->get_all([['EVT_ID' => $EVT_ID]]);
        if (! empty($lg_exists)) {
            foreach ($lg_exists as $list_group) {
                $list_group->delete();
            }
        }
        $this->saveListGroups($EVT_ID);
        $this->saveQuestionFields($EVT_ID);
    }


    /**
     * Save list groups.
     *
     * @param int $EVT_ID
     * @return void (HTML)
     * @throws EE_Error
     * @throws ReflectionException
     */
    public function saveListGroups(int $EVT_ID): void
    {
        $list_id          = sanitize_text_field($_POST['ee_mailchimp_lists']);
        $mailchimp_groups = array_key_exists('ee_mailchimp_groups', $_POST)
            ? $_POST['ee_mailchimp_groups']
            : [];
        $all_interests    = array_key_exists('ee_mc_list_all_interests', $_POST)
            ? $_POST['ee_mc_list_all_interests']
            : [];
        if (! empty($mailchimp_groups) && ! empty($all_interests)) {
            $group_ids = [];
            // Multidimensional array ? Straighten it up.
            foreach ($mailchimp_groups as $g_id) {
                if (is_array($g_id)) {
                    foreach ($g_id as $l2g_id) {
                        $group_ids[] = sanitize_text_field($l2g_id);
                    }
                } else {
                    $group_ids[] = sanitize_text_field($g_id);
                }
            }
            // We need to save the list of all interests for the current MC List.
            foreach ($all_interests as $interest) {
                $interest = sanitize_text_field($interest);
                // Mark what lists were selected and not.
                if (in_array($interest, $group_ids)) {
                    $interest .= '-true';
                } else {
                    $interest .= '-false';
                }
                $new_list_group = EE_Event_Mailchimp_List_Group::new_instance(
                    [
                        'EVT_ID'                 => $EVT_ID,
                        'AMC_mailchimp_list_id'  => $list_id,
                        'AMC_mailchimp_group_id' => $interest,
                    ]
                );
                $new_list_group->save();
            }
            // This info was saved in a new format so we set a flag for this event.
            $event = EEM_Event::instance()->get_one_by_ID($EVT_ID);
            if ($event instanceof EE_Event) {
                $event->update_extra_meta(EE_MCI_Controller::UPDATED_TO_API_V3, true);
            }
        } else {
            $new_list_group = EE_Event_Mailchimp_List_Group::new_instance(
                [
                    'EVT_ID'                 => $EVT_ID,
                    'AMC_mailchimp_list_id'  => $list_id,
                    'AMC_mailchimp_group_id' => -1,
                ]
            );
            $new_list_group->save();
        }
    }


    /**
     * Save question fields.
     *
     * @param int $EVT_ID
     * @return void (HTML)
     * @throws EE_Error
     * @throws ReflectionException
     */
    public function saveQuestionFields(int $EVT_ID): void
    {
        $qf_exists = EEM_Question_Mailchimp_Field::instance()->get_all([['EVT_ID' => $EVT_ID]]);
        $question_fields_list = array_key_exists('ee_mailchimp_qfields', $_POST)
            ? $_POST['ee_mailchimp_qfields']
            : [];
        // Question Fields
        if (is_array($question_fields_list) && ! empty($question_fields_list)) {
            foreach ($question_fields_list as $mc_question_id) {
                $encoded = base64_encode($mc_question_id);
                if (isset($_POST[ $encoded ]) && $_POST[ $encoded ] != '-1') {
                    $ev_question = $_POST[ $encoded ];
                    $q_found     = false;
                    // Update already present Q fields.
                    foreach ($qf_exists as $question_field) {
                        $mc_field = $question_field instanceof EE_Question_Mailchimp_Field
                            ? $question_field->mc_field()
                            : '';
                        if ($mc_field == $mc_question_id) {
                            EEM_Question_Mailchimp_Field::instance()->update(
                                ['QST_ID' => $ev_question],
                                [
                                    [
                                        'EVT_ID'                 => $EVT_ID,
                                        'QMC_mailchimp_field_id' => $mc_question_id,
                                    ],
                                ]
                            );
                            $q_found = true;
                        }
                    }
                    // Add Q field if was not present.
                    if (! $q_found) {
                        $new_question_field = EE_Question_Mailchimp_Field::new_instance(
                            [
                                'EVT_ID'                 => $EVT_ID,
                                'QST_ID'                 => $ev_question,
                                'QMC_mailchimp_field_id' => $mc_question_id,
                            ]
                        );
                        $new_question_field->save();
                    }
                } else {
                    $mc_question = EEM_Question_Mailchimp_Field::instance()->get_one(
                        [
                            [
                                'EVT_ID'                 => $EVT_ID,
                                'QMC_mailchimp_field_id' => $mc_question_id,
                            ],
                        ]
                    );
                    if ($mc_question != null) {
                        $mc_question->delete();
                    }
                }
            }
        }
    }


    /**
     * Display the MailChimp user Lists for given event.
     *
     * @param string $list_id
     * @return string (HTML)
     * @throws EE_Error
     */
    public function mci_list_mailchimp_lists(string $list_id = ''): string
    {
        do_action('AHEE__EE_MCI_Controller__mci_list_mailchimp_lists__start');
        if ($this->MailChimp instanceof MailchimpAPI) {
            // Load the lists form.
            $lists_obj = new EE_MC_Lists_Form($this, $list_id);
            return $lists_obj->get_html_and_js();
        }
        // Something is wrong with the API, so we return nothing.
        return '';
    }


    /**
     * Display MailChimp interest Categories for the given event (depending on the selected List).
     *
     * @param string $event_id The ID of the Event.
     * @param string $list_id
     * @return string (HTML)
     * @throws EE_Error
     */
    public function mci_list_mailchimp_groups(string $event_id = '', string $list_id = ''): string
    {
        do_action('AHEE__EE_MCI_Controller__mci_list_mailchimp_groups__start');
        if ($this->MailChimp instanceof MailchimpAPI) {
            // Load the interests form.
            $interest_categories_obj = new EE_MC_Interest_Categories_Form($this, $event_id, $list_id);
            return $interest_categories_obj->get_html_and_js();
        }
        // Something is wrong with the API so we return nothing.
        return '';
    }


    /**
     * Display MailChimp merge Fields of the given event (depending on the selected list).
     *
     * @param string $event_id The ID of the Event.
     * @param string $list_id
     * @return string (HTML)
     * @throws EE_Error|ReflectionException
     */
    public function mci_list_mailchimp_fields(string $event_id = '', string $list_id = ''): string
    {
        do_action('AHEE__EE_MCI_Controller__mci_list_mailchimp_fields__start');
        $fields_obj = new EE_MC_Merge_Fields_Form($this, $event_id, $list_id);
        return $fields_obj->get_html_and_js();
    }


    /**
     * Get the list of question groups (EQG_primary) of the Event. If there are non (because it's a new event)
     * then at least return the main question group
     *
     * @param mixed $EVT_ID The ID of the Event.
     * @return array  List of all primary QGs fn the Event.
     * @throws EE_Error|ReflectionException
     */
    public function mci_get_event_question_groups($EVT_ID): array
    {
        $question_groups = [];
        // only bother querying for question groups related to the evnent if the event exists
        if ($EVT_ID) {
            $question_groups = EEM_Question_Group::instance()->get_all(
                [
                    [
                        'Event.EVT_ID'                     => $EVT_ID,
                        'Event_Question_Group.EQG_primary' => true,
                    ],
                    'order_by' => ['QSG_order' => 'ASC'],
                ]
            );
        }
        // if this is a new event, or somehow we didn't find any groups,
        // then at least grab the primary question group
        if (empty($question_groups)) {
            $question_groups = EEM_Question_Group::instance()->get_all(
                [
                    [
                        'QSG_system' => EEM_Question_Group::system_personal,
                    ],
                ]
            );
        }
        return $question_groups;
    }


    /**
     * Get all questions of all primary question groups of the Event.
     *
     * @param string $event_id The ID of the Event.
     * @return array  List of all primary Questions of the Event.
     * @throws EE_Error
     * @throws ReflectionException
     */
    public function mci_get_event_all_questions(string $event_id): array
    {
        $questions       = [];
        $question_groups = $this->mci_get_event_question_groups($event_id);
        if (! empty($question_groups) && is_array($question_groups)) {
            foreach ($question_groups as $QG_list) {
                if ($QG_list instanceof EE_Question_Group) {
                    foreach ($QG_list->questions() as $question) {
                        $qst = [
                            'QST_name'   => $question->get('QST_display_text'),
                            'QST_ID'     => $question->get('QST_ID'),
                            'QST_system' => $question->get('QST_system'),
                        ];
                        if (! in_array($qst, $questions)) {
                            $questions[ $question->ID() ] = $qst;
                        }
                    }
                }
            }
            return $questions;
        } else {
            return [];
        }
    }


    /**
     * Get MailChimp Event list
     *
     * @param int $EVT_ID The ID of the Event.
     * @return string
     * @throws EE_Error
     * @throws ReflectionException
     */
    public function mci_event_list(int $EVT_ID): string
    {
        EE_Registry::instance()->load_model('Event_Mailchimp_List_Group');
        $event_list = EEM_Event_Mailchimp_List_Group::instance()->get_all(
            [
                ['EVT_ID' => $EVT_ID],
                'limit' => 1,
            ]
        );
        $event_list = reset($event_list);
        return $event_list instanceof EE_Event_Mailchimp_List_Group ? $event_list->mc_list() : '';
    }


    /**
     * Get MailChimp Event list group
     *
     * @param int $EVT_ID The ID of the Event.
     * @return EE_Event_Mailchimp_List_Group[]
     * @throws EE_Error
     * @throws ReflectionException
     */
    public function mci_event_list_group(int $EVT_ID): array
    {
        EE_Registry::instance()->load_model('Event_Mailchimp_List_Group');
        $mc_list_groups    = EEM_Event_Mailchimp_List_Group::instance()->get_all([['EVT_ID' => $EVT_ID]]);
        $event_list_groups = [];
        foreach ($mc_list_groups as $mc_list_group) {
            if (
                $mc_list_group instanceof EE_Event_Mailchimp_List_Group
                && $mc_list_group->mc_group() !== '-1'
            ) {
                $event_list_groups[] = $mc_list_group->mc_group();
            }
        }
        return $event_list_groups;
    }


    /**
     * Get a list of selected Interests.
     *
     * @param int $EVT_ID The ID of the Event.
     * @return EE_Event_Mailchimp_List_Group[]
     * @throws EE_Error
     * @throws ReflectionException
     */
    public function mci_event_selected_interests(int $EVT_ID): array
    {
        $selected      = [];
        $all_interests = $this->mci_event_list_group($EVT_ID);
        foreach ($all_interests as $interest) {
            if (strpos($interest, '-true') !== false) {
                $selected[] = str_replace('-true', '', $interest);
            }
        }
        return $selected;
    }


    /**
     * Get MailChimp Event list question fields
     *
     * @param int $EVT_ID The ID of the Event.
     * @return array
     * @throws EE_Error
     * @throws ReflectionException
     */
    public function mci_event_list_question_fields(int $EVT_ID): array
    {
        EE_Registry::instance()->load_model('Question_Mailchimp_Field');
        $mc_question_fields         = EEM_Question_Mailchimp_Field::instance()->get_all([['EVT_ID' => $EVT_ID]]);
        $event_list_question_fields = [];
        foreach ($mc_question_fields as $mc_question_field) {
            if ($mc_question_field instanceof EE_Question_Mailchimp_Field) {
                $event_list_question_fields[ $mc_question_field->mc_field() ] = $mc_question_field->mc_event_question();
            }
        }
        return $event_list_question_fields;
    }


    /**
     * Get MailChimp list or groups or question fields relationships, set for given event.
     *
     * @param int $EVT_ID    The ID of the Event.
     * @return array/string  Id of a group/list or an array of 'List fields - Event questions' relationships, or all in
     *                       an array.
     * @throws EE_Error
     * @throws ReflectionException
     */
    public function mci_event_subscriptions(int $EVT_ID): array
    {
        return [
            'list'    => $this->mci_event_list($EVT_ID),
            'groups'  => $this->mci_event_list_group($EVT_ID),
            'qfields' => $this->mci_event_list_question_fields($EVT_ID),
        ];
    }


    /**
     * Check the MC data in the DB to see if it can be used in the MC API v3 or needs to be updated.
     *
     * @param int    $EVT_ID  The ID of the Event.
     * @param string $list_id MC List ID the Event in "subscribed" for.
     * @return void
     * @throws EE_Error
     * @throws ReflectionException
     */
    public function is_db_data_api_compatible(int $EVT_ID, string $list_id = '')
    {
        $event = EEM_Event::instance()->get_one_by_ID($EVT_ID);
        // Make sure there is an event with this ID.
        if (! $event instanceof EE_Event) {
            return;
        }
        $event_checked = $event->get_extra_meta(EE_MCI_Controller::UPDATED_TO_API_V3, true, false);
        // This event already checked before ? no need to do this again then.
        if ($event_checked) {
            return;
        }
        if (empty($list_id)) {
            $list_id = $this->mci_event_list($EVT_ID);
        }
        // Also no need to migrate "Do not send to MC".
        if (empty($list_id) || $list_id === '-1') {
            // Although there is no MC data to migrate, let's at least remember we already checked
            $event->update_extra_meta(EE_MCI_Controller::UPDATED_TO_API_V3, true);
            return;
        }
        // Check the data structure.
        $event_groups  = $this->mci_event_list_group($EVT_ID);
        $old_structure = true;
        if (! empty($event_groups)) {
            if (is_array($event_groups)) {
                // Just get the first element and see if it has the right structure.
                $event_interest = $event_groups[0];
                $interest       = explode('-', $event_interest);
                if (isset($interest[3]) && ($interest[3] === 'true' || $interest[3] === 'false')) {
                    $old_structure = false;
                }
            }
        }
        if ($old_structure) {
            // If we are here the data was not updated yet.
            // Clear MailChimp data on the current event and then save the updated data.
            EEM_Event_Mailchimp_List_Group::instance()->delete([['EVT_ID' => $EVT_ID]]);
            $list_interests = [];
            // Fetch the lists/groups/interests for this event.
            $categories = $this->mci_get_users_groups($list_id);
            if (! empty($categories) && is_array($categories)) {
                // Now we try to get the interests themselves.
                foreach ($categories as $category) {
                    $interests = $this->mci_get_interests($list_id, $category->id);
                    // No interests ? Move on...
                    if (! empty($interests) && is_array($interests)) {
                        foreach ($interests as $interest) {
                            // ID's in API v2 and v3 are different so we need to go by the interest names.
                            // If there are same names we need to somehow id them so we put those with the same name in an array under that name.
                            $list_interests[ $interest->name ][] = $interest;
                        }
                    }
                }
                // Update the old rows.
                $saved_interests = [];
                foreach ($event_groups as $event_interest) {
                    $list_and_interest = explode('-', $event_interest);
                    // Double check just in case we do get one already updated field.
                    if (
                        isset($list_and_interest[3])
                        && ($list_and_interest[3] === 'true' || $list_and_interest[3] === 'false')
                    ) {
                        $new_list_interest = EE_Event_Mailchimp_List_Group::new_instance(
                            [
                                'EVT_ID'                 => $EVT_ID,
                                'AMC_mailchimp_list_id'  => $list_id,
                                'AMC_mailchimp_group_id' => $event_interest,
                            ]
                        );
                        $new_list_interest->save();
                        continue;
                    }
                    $interest_name = base64_decode($list_and_interest[2]);
                    $interest_data = array_shift($list_interests[ $interest_name ]);
                    if (! empty($interest_data) && is_array($interest_data)) {
                        // Update current row data.
                        $list_group_id     = $interest_data['id']
                                             . '-' . $interest_data['category_id']
                                             . '-' . base64_encode($interest_data['name'])
                                             . '-true';
                        $new_list_interest = EE_Event_Mailchimp_List_Group::new_instance(
                            [
                                'EVT_ID'                 => $EVT_ID,
                                'AMC_mailchimp_list_id'  => $list_id,
                                'AMC_mailchimp_group_id' => $list_group_id,
                            ]
                        );
                        $new_list_interest->save();
                        $saved_interests[] = $interest_data['id'];
                    }
                }
                // Need to add the not selected interests as API v3 uses those also.
                foreach ($list_interests as $all_interests) {
                    foreach ($all_interests as $mss_interest) {
                        // Already saved / updated interest ?
                        if (in_array($mss_interest['id'], $saved_interests)) {
                            continue;
                        }
                        // Add these as not selected.
                        $lg_id             = $mss_interest['id']
                                             . '-' . $mss_interest['category_id']
                                             . '-' . base64_encode($mss_interest['name'])
                                             . '-false';
                        $new_list_interest = EE_Event_Mailchimp_List_Group::new_instance(
                            [
                                'EVT_ID'                 => $EVT_ID,
                                'AMC_mailchimp_list_id'  => $list_id,
                                'AMC_mailchimp_group_id' => $lg_id,
                            ]
                        );
                        $new_list_interest->save();
                        $saved_interests[] = $mss_interest['id'];
                    }
                }
            } else {
                // Looks like there are no interests for this list.
                // So we should just add the Event List relation with no interests.
                $new_list_interest = EE_Event_Mailchimp_List_Group::new_instance(
                    [
                        'EVT_ID'                 => $EVT_ID,
                        'AMC_mailchimp_list_id'  => $list_id,
                        'AMC_mailchimp_group_id' => '-1',
                    ]
                );
                $new_list_interest->save();
            }
        }
        // Check the list question fields.
        // If the MC email (MERGE0) field was mapped to a different form input, remove that override.
        $mc_field_to_ee_q_map = $this->mci_event_list_question_fields($EVT_ID);
        if (array_key_exists('EMAIL', $mc_field_to_ee_q_map)) {
            EEM_Question_Mailchimp_Field::instance()->delete(
                [
                    [
                        'EVT_ID'                 => $EVT_ID,
                        'QMC_mailchimp_field_id' => 'EMAIL',
                    ],
                ]
            );
        }
        // Remember this event's MailChimp list data has been verified. No need to do it again
        $event->update_extra_meta(EE_MCI_Controller::UPDATED_TO_API_V3, true);
    }


    /**
     * Process the MailChimp error response.
     *
     * @access private
     * @param array | Exception | boolean $reply An error reply from MailChimp API.
     * @return void
     * @throws EE_Error|ReflectionException
     */
    private function set_error($reply): void
    {
        if ($reply instanceof Exception) {
            $error['code'] = $reply->getCode();
            $error['msg']  = $reply->getMessage();
            $error['body'] = $reply->getTraceAsString();
        } elseif (is_array($reply)) {
            $error['code'] = $reply['status'] ?? 0;
            $error['msg']  = $reply['title'] ?? esc_html__('Unknown MailChimp Error.', 'event_espresso');
            $error['body'] = $reply['detail'] ?? '';
        } elseif (is_object($reply)) {
            $error['code'] = $reply->status ?? 0;
            $error['msg']  = $reply->title ?? esc_html__('Unknown MailChimp Error.', 'event_espresso');
            $error['body'] = $reply->detail ?? '';
        } else {
            $error['code'] = 0;
            $error['msg']  = esc_html__('Invalid MailChimp API Key.', 'event_espresso');
            $error['body'] = '';
        }
        $this->mcapi_error = apply_filters('FHEE__EE_MCI_Controller__mci_throw_error__mcapi_error', $error);
        // Only save a log entry if not in maintenance mode
        if (EE_Maintenance_Mode::instance()->models_can_query()) {
            EEM_Change_Log::instance()->log(
                EED_Mailchimp::log_type,
                $error,
                null
            );
        }
    }


    /**
     * mci_get_api_key
     *
     * @return string/bool  MC API Key if present/valid
     */
    public function mci_get_api_key(): string
    {
        return $this->_api_key;
    }


    /**
     * mci_get_response_error
     *
     * @return array
     */
    public function mci_get_response_error(): array
    {
        return $this->mcapi_error;
    }
}
