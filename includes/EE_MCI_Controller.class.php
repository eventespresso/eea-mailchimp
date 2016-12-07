<?php if ( ! defined( 'EVENT_ESPRESSO_VERSION' ) ) { exit('NO direct script access allowed'); }

/*
 * Event Espresso
 *
 * Event Registration and Management Plugin for WordPress
 *
 * @ package        Event Espresso
 * @ author         Event Espresso
 * @ copyright (c)  2008-2014 Event Espresso  All Rights Reserved.
 * @ license        http://eventespresso.com/support/terms-conditions/   * see Plugin Licensing *
 * @ link           http://www.eventespresso.com
 * @ version        EE4
 *
 * ------------------------------------------------------------------------
 */
use EEA_MC\MailChimp;

/**
 * Class  EE_MCI_Controller - Event Espresso MailChimp logic implementing. Intermediary between this integration and the MailChimp API.
 *
 * @package         Event Espresso
 * @subpackage      ee4-mailchimp
 *
 * ------------------------------------------------------------------------
 */
class EE_MCI_Controller {

	/**
     * @access private
	 * @var EE_Mailchimp_Config $_config
     */
	private $_config = NULL;

	/**
     * @access private
	 * @var string $_api_key
     */
	private $_api_key = NULL;

	/**
     * Error details.
     * @access private
	 * @var array $mcapi_error
     */
	private  $mcapi_error = array();

	/**
     * MailChimp API Object.
     * @access private
	 * @var \EEA_MC\MailChimp $MailChimp
     */
	private $MailChimp = NULL;

	/**
     * The selected List ID.
     * @access private
	 * @var int $list_id
	 */
	private $list_id = 0;

	/**
     * The selected interest group ID.
     * @access private
	 * @var int $category_id
	 */
	private $category_id = 0;



	/**
	 * Class constructor
	 *
	 * @param string $api_key
	 * @return EE_MCI_Controller
	 */
	function __construct( $api_key = '' ) {
		do_action( 'AHEE__EE_MCI_Controller__class_constructor__init_controller' );
		require_once( ESPRESSO_MAILCHIMP_DIR . 'includes' . DS . 'MailChimp.class.php' );

		$this->_config = EED_Mailchimp::instance()->config();

		// Verify API key.
		$api_key = ! empty( $api_key ) ? $api_key : $this->_config->api_settings->api_key;
		$this->_api_key = $this->mci_is_api_key_valid( $api_key );
	}



	/**
	 * Validate the MailChimp API key. If key not provided then the one that is saved in the settings will be checked.
	 *
	 * @access public
	 * @param string $api_key MailChimp API Key.
	 * @param array   $call_reply
	 * @return bool  Is Key valid or not
	 */
	public function mci_is_api_key_valid( $api_key = NULL, $call_reply = array() ) {
		do_action('AHEE__EE_MCI_Controller__mci_is_api_key_valid__start');
		// Make sure API key only has one '-'
		$exp_key = explode( '-', $api_key );
		if ( ! is_array( $exp_key ) || count( $exp_key ) != 2 ) {
			$this->set_error( FALSE );
			do_action( 'AHEE__EE_MCI_Controller__mci_is_api_key_valid__api_key_error' );
			return FALSE;
		}

		// Check if key is live/acceptable by API.
		try {
			$this->MailChimp = new MailChimp( $api_key );
			$parameters = apply_filters( 'AHEE__EE_MCI_Controller__mci_is_api_key_valid__parameters', array('fields' => 'account_id,account_name,email,username') );
			$reply = $this->MailChimp->get('', $parameters);
		} catch ( Exception $e ) {
			$this->set_error( $e );
			do_action( 'AHEE__EE_MCI_Controller__mci_is_api_key_valid__api_key_error' );
			unset( $this->MailChimp );
			return FALSE;
		}

		// If a reply is present, then let's process that.
		if ( ! $this->MailChimp->success() || ! isset($reply['account_id']) ) {
				$this->set_error( $reply );
				do_action( 'AHEE__EE_MCI_Controller__mci_is_api_key_valid__api_key_error' );
				unset( $this->MailChimp );
				return FALSE;
		}
		do_action( 'AHEE__EE_MCI_Controller__mci_is_api_key_valid__api_key_ok' );
		return $api_key;
	}



	/**
	 * Retrieve EE_Registration and EE_Transaction objects from incoming data.
	 *
	 * @access protected
	 * @param EED_Single_Page_Checkout || EE_SPCO_Reg_Step_Attendee_Information $spco_obj
	 * @return array ( EE_Transaction, EE_Registration[] )
	 */
	protected function _mci_get_registrations( $spco_obj ) {
		$spco_registrations = array();
		$spco_transaction = false;
		// what kind of SPCO object did we receive?
		if ( $spco_obj instanceof EE_SPCO_Reg_Step_Attendee_Information ) {
			// for EE versions > 4.6
			if ( $spco_obj->checkout instanceof EE_Checkout && $spco_obj->checkout->transaction instanceof EE_Transaction ) {
				$spco_registrations = $spco_obj->checkout->transaction->registrations( $spco_obj->checkout->reg_cache_where_params, TRUE );
				$spco_transaction = $spco_obj->checkout->transaction;
			}
		} else if ( $spco_obj instanceof EED_Single_Page_Checkout ) {
			$transaction = EE_Registry::instance()->SSN->get_session_data( 'transaction' );
			// for EE versions < 4.6
			if ( $transaction instanceof EE_Transaction ) {
				$spco_registrations = $transaction->registrations( array(), TRUE );
				$spco_transaction = $transaction;
			}
		} else if ( $spco_obj instanceof EE_Checkout ) {
			$spco_registrations = $spco_obj->transaction->registrations( $spco_obj->reg_cache_where_params, TRUE );
			$spco_transaction = $spco_obj;
		}
		return array( 'registrations' => $spco_registrations, 'transaction' => $spco_transaction );
	}


	/**
     * Subscribe new attendee to MailChimp list selected for current event.
     *
	 * Depending by what hook this was called (process_attendee_information__end or update_txn_based_on_payment__successful) the:
	 * $spc_obj could be an instanceof: EE_SPCO_Reg_Step_Attendee_Information, EED_Single_Page_Checkout or EE_Checkout.
     *
     * @param EED_Single_Page_Checkout/EE_SPCO_Reg_Step_Attendee_Information/EE_Checkout $spc_obj  Single Page Checkout (SPCO) Object.
     * @param array/EE_Payment $valid_data  Valid data from the Attendee Information Registration form / EE_Payment.
     * @return void
     */
	public function mci_submit_to_mailchimp( $spc_obj, $valid_data ) {
		// Do not submit if the key is not valid or there is no valid submit data.
		if ( $this->MailChimp instanceof EEA_MC\MailChimp && ! empty( $spc_obj )) {
			$spco_data = $this->_mci_get_registrations( $spc_obj );
			$registrations = $spco_data['registrations'];
			$spco_transaction = $spco_data['transaction'];

			do_action('AHEE__EE_MCI_Controller__mci_submit_to_mailchimp__start', $spco_transaction, $registrations);

			$registered_attendees = array();
			// now loop through registrations to get the related attendee objects
			if ( ! empty( $registrations )) {
				foreach ( $registrations as $registration ) {
					if ( $registration instanceof EE_Registration ) {
						$need_reg_status = $reg_approved = false;
						/** @type EE_Mailchimp_Config $mc_config */
						$mc_config = EED_Mailchimp::get_config();
						if ( $mc_config->api_settings->submit_to_mc_when === 'reg-step-approved' ) {
							$need_reg_status = true;
							$reg_status = $registration->status_ID();
							if ( $reg_status === EEM_Registration::status_id_approved ) {
								$reg_approved = true;
							}
						}

						$attendee = $registration->attendee();
						$att_email = $attendee->email();
						if ( ( $attendee instanceof EE_Attendee ) && ! in_array($att_email, $registered_attendees) && ( ! $need_reg_status || $need_reg_status && $reg_approved ) ) {
							$EVT_ID = $registration->event_ID();
							$event_list = $this->mci_event_list( $EVT_ID );
							// If no list selected for this event than skip the subscription.
							if ( ! empty( $event_list )) {
								$opt_in = isset( $this->_config->api_settings->skip_double_optin ) ? $this->_config->api_settings->skip_double_optin : true;
								$emails_type = isset( $this->_config->api_settings->emails_type ) ? $this->_config->api_settings->emails_type : 'text';
								$subscribe_args = array(
									'email_address' => $att_email
								);
								// Group vars
								$subscribe_args = $this->_add_event_group_vars_to_subscribe_args( $EVT_ID, $subscribe_args );
								// Question fields
								$subscribe_args = $this->_add_registration_question_answers_to_subscribe_args( $registration, $EVT_ID, $subscribe_args );
								// filter it
								$subscribe_args = apply_filters('FHEE__EE_MCI_Controller__mci_submit_to_mailchimp__subscribe_args', $subscribe_args );

								try {
									// Get member info if exists.
									$member_subscribed = $this->MailChimp->get( '/lists/'.$event_list.'/members/'.$this->MailChimp->subscriberHash($att_email), array('fields' => 'id,email_address,status') );
									if ( isset($member_subscribed['email_address']) && isset($member_subscribed['status']) && ! preg_match('/^(4|5)\d{2}$/', $member_subscribed['status']) ) {
										$subscribe_args['status'] = $member_subscribed['status'];
									}
									// Send opt-in emails ?
									if ( $opt_in ) {
										$subscribe_args['status_if_new'] = 'pending';
									} else {
										$subscribe_args['status_if_new'] = 'subscribed';
									}
									// What type of emails we want to send ?
									$subscribe_args['email_type'] = $emails_type;
									// Add/update member.
									$put_member = $this->MailChimp->put( '/lists/'.$event_list.'/members/'.$this->MailChimp->subscriberHash($att_email), $subscribe_args );
									// Log error.
									if ( ! $this->MailChimp->success() ) {
										$this->set_error( $put_member );
										$errors = '';
										if ( isset($put_member['errors']) && is_array($put_member['errors']) ) {
											foreach ($put_member['errors'] as $err) {
												$errors .= (isset($err['field'])) ? $err['field'] . ': ' : '';
												$errors .= (isset($err['message'])) ? $err['message'] . ', ' : '';
											}
										}
										$notice_msg = sprintf(
											__( 'Registration #%1$s (%2$s %3$s, %4$s) could not be subscribed to a MailChimp List (%5$s). There were errors regarding the following: %6$s. 
												Please verify that event %7$s has questions for all required MailChimp fields, 
												and that they\'re of the correct types, and that multi-choice MailChimp fields correspond to EE questions with all the same answer values. 
												If you have further problems please contact support.', 'event_espresso' ),
											$registration->ID(),
											$attendee->fname(),
											$attendee->lname(),
											$att_email,
											$event_list,
											$errors,
											'<a href="'.$registration->event()->get_permalink().'">'.$registration->event_name().'</a>'
										);
										// Notify the admin if there was a problem with the subscription.
										EE_Error::add_persistent_admin_notice( 'eea_mailchimp_subscription_error', $notice_msg, true );
									}
								} catch ( Exception $e ) {
									$member_subscribed = FALSE;
									$this->set_error( $e );
								}
								$registered_attendees[] = $att_email;
							}
						}
					}
				}
			}
		}
	}



	/**
	 * _add_event_group_vars_to_subscribe_args
	 *
	 * @access public
	 * @param int $EVT_ID
	 * @param array $subscribe_args
	 * @return array
	 */
	protected function _add_event_group_vars_to_subscribe_args( $EVT_ID = 0,  $subscribe_args = array() ) {
		$event_groups = $this->mci_event_list_group( $EVT_ID );
		if ( ! empty( $event_groups )) {
			if ( is_array( $event_groups )) {
				foreach ( $event_groups as $event_group ) {
					$subscribe_args = $this->_process_event_group_subscribe_args( $event_group, $subscribe_args );
				}
			} else {
				$subscribe_args = $this->_process_event_group_subscribe_args( $event_groups, $subscribe_args );
			}
		}
		return $subscribe_args;
	}


	/**
     * _process_event_group_subscribe_args
     *
     * @access public
	 * @param string $event_group
	 * @param array $subscribe_args
     * @return array  List of MailChimp lists.
     */
	protected function _process_event_group_subscribe_args( $event_group = '',  $subscribe_args = array() ) {
		$grouping = explode( '-', $event_group );
		// Is this interest selected.
		$selected = false;
		if ( isset($grouping[3]) && $grouping[3] === 'true' ) {
			$selected = true;
		}
		// Just add the interests.
		if ( isset( $subscribe_args['interests'] )) {
			foreach ( $subscribe_args['interests'] as $interest => $value ) {
				if ( $interest != $grouping[0] ) {
					$subscribe_args['interests'][$grouping[0]] = $selected;
				}
			}
		} else {
			$subscribe_args['interests'][$grouping[0]] = $selected;
		}
		return $subscribe_args;
	}



	/**
	 * _get_attendee_details_for_registration
	 *
	 * @access public
	 * @param EE_Registration $registration
	 * @return array
	 */
	protected function _get_attendee_details_for_registration( EE_Registration $registration ) {
		// empty array to add stuff to
		$question_answers = array();
		if ( $registration instanceof EE_Registration ) {
			$attendee = $registration->attendee();
			if ( $attendee instanceof EE_Attendee ) {
				EE_Registry::instance()->load_model( 'Attendee' );
				$attendee_properties = array(
					EEM_Attendee::fname_question_id       => 'ATT_fname',
					EEM_Attendee::lname_question_id       => 'ATT_lname',
					EEM_Attendee::email_question_id       => 'ATT_email',
					EEM_Attendee::address_question_id     => 'ATT_address',
					EEM_Attendee::address2_question_id    => 'ATT_address2',
					EEM_Attendee::city_question_id        => 'ATT_city',
					EEM_Attendee::state_question_id       => 'STA_ID',
					EEM_Attendee::country_question_id     => 'CNT_ISO',
					EEM_Attendee::zip_question_id         => 'ATT_zip',
					EEM_Attendee::phone_question_id       => 'ATT_phone'
				);
				foreach ( $attendee_properties as $QST_ID => $attendee_property ) {
					$question_answers[ $QST_ID ] = $attendee->get( $attendee_property );
				}
			}
		}
		return $question_answers;
	}



	/**
	 * _get_question_answers_for_registration
	 *
	 * @access public
	 * @param EE_Registration $registration
	 * @param array           $question_answers
	 * @return array
	 */
	protected function _get_question_answers_for_registration( EE_Registration $registration, $question_answers = array() ) {
		if ( $registration instanceof EE_Registration ) {
			// grab the registrant's answers
			$registration_answers = $registration->answers();
			if ( ! empty( $registration_answers ) ) {
				foreach ( $registration_answers as $registration_answer ) {
					if ( $registration_answer instanceof EE_Answer ) {
						// get the related question for the answer
						$registration_question = $registration_answer->question();
						if ( $registration_question instanceof EE_Question ) {
							// for system questions
							if ( $registration_question->is_system_question() ) {
								// use the system ID for the array key
								$question_answers[ $registration_question->system_ID() ] = $registration_answer->pretty_value();
							} else {
								// or just the regular question ID for non-system questions
								$question_answers[ $registration_question->ID() ] = $registration_answer->pretty_value();
							}
						}
					}
				}
			}
		}
		return $question_answers;
	}



	/**
	 * _add_registration_question_answers_to_subscribe_args
	 *
	 * @access public
	 * @param EE_Registration $registration
	 * @param int           $EVT_ID
	 * @param array           $subscribe_args
	 * @throws \EE_Error
	 * @return array
	 */
	protected function _add_registration_question_answers_to_subscribe_args( EE_Registration $registration, $EVT_ID = 0,  $subscribe_args = array() ) {
		if ( ! is_array( $subscribe_args ) ) {
			throw new EE_Error( __( 'The MailChimp Subscriber arguments array is malformed!','event_espresso' ));
		}
		if ( ! isset( $subscribe_args['merge_fields'] ) ) {
			$subscribe_args['merge_fields'] = array();
		}
		// get MailChimp question fields
		$question_fields = $this->mci_event_list_question_fields( $EVT_ID );
		// get the registrant's attendee details
		$question_answers = $this->_get_attendee_details_for_registration( $registration );
		// get the registrant's question answers
		$question_answers = $this->_get_question_answers_for_registration( $registration, $question_answers );
		// Get all event questions.
		$event_questions_list = $this->mci_get_event_all_questions( $EVT_ID );
		foreach ( $question_fields as $mc_list_field => $question_ID ) {
			// Older version used names for an IDs (now using int).
			$q_id = null;
			if ( is_numeric($question_ID) ) {
				$q_id = $question_ID;
			} else {
				if ( isset($event_questions_list[$question_ID]) ) {
					$q_id = $event_questions_list[$question_ID]['QST_ID'];
				}
			}
			if ( isset($question_answers[ $q_id ]) && !empty($question_answers[ $q_id ]) ) {
				// If question field is a State then get the state name not the code.
				if ( isset($event_questions_list['state']) && $q_id == $event_questions_list['state']['QST_ID'] ) {	// If a state.
					$state = $registration->attendee()->state_obj();
					if ( $state instanceof EE_State ) {
						$subscribe_args['merge_fields'][ $mc_list_field ] = $state->name();
					}

				} else if ( isset($event_questions_list['country']) && $q_id == $event_questions_list['country']['QST_ID'] ) {	// If a Country (change ISO to a full name).
					$country = $registration->attendee()->country_obj();
					if ( $country instanceof EE_Country ) {
						$subscribe_args['merge_fields'][ $mc_list_field ] = $country->name();
					}

				} else if ( is_array( $question_answers[ $q_id ] )) {
					$selected = '';
					foreach ( $question_answers[ $q_id ] as $q_key => $q_value ) {
						$selected .= $selected == '' ? $q_value : ', ' . $q_value;
					}
					$subscribe_args['merge_fields'][$mc_list_field] = $selected;
				} else {
					$subscribe_args['merge_fields'][$mc_list_field] = $question_answers[ $q_id ];
				}
			}
		}
		return $subscribe_args;
	}



	/**
	 * Retrieve all of the lists defined for your user account.
	 *
	 * @access public
	 * @return array  List of MailChimp lists.
	 */
	public function mci_get_users_lists() {
		do_action('AHEE__EE_MCI_Controller__mci_get_users_lists__start');
		$parameters = apply_filters( 'AHEE__EE_MCI_Controller__mci_get_users_lists__parameters', array('fields' => 'lists.id,lists.name', 'count' => 100) );
		
		try {
			$reply = $this->MailChimp->get('lists', $parameters);
		} catch ( Exception $e ) {
			$this->set_error($e);
			return array();
		}

		if ( $this->MailChimp->success() && isset($reply['lists']) ) {
			return (array)$reply['lists'];
		} else {
			return array();
		}
	}



	/**
	 * Get the list of Interest Categories for a given list.
	 *
	 * @access public
	 * @param string $list_id  The ID of the List.
	 * @return array  List of MailChimp groups of selected List.
	 */
	public function mci_get_users_groups( $list_id ) {
		do_action('AHEE__EE_MCI_Controller__mci_get_users_groups__start');
		$parameters = apply_filters( 'AHEE__EE_MCI_Controller__mci_get_users_groups__parameters', array('exclude_fields' => '_links,categories._links', 'count' => 50) );

		if ( $list_id == NULL )
			$list_id = $this->list_id;

		try {
			$reply = $this->MailChimp->get('lists/'.$list_id.'/interest-categories', $parameters);
		} catch ( Exception $e ) {
			$this->set_error($e);
			return array();
		}
		if ( $this->MailChimp->success() && isset($reply['categories']) ) {
			return (array)$reply['categories'];
		} else {
			return array();
		}
	}



	/**
	 * Get the list of interests for a specific MailChimp list.
	 *
	 * @access public
	 * @param string $list_id  The ID of the List.
	 * @param string $category_id  The ID of the interest category.
	 * @return array  List of MailChimp interests of selected List.
	 */
	public function mci_get_interests( $list_id, $category_id ) {
		do_action('AHEE__EE_MCI_Controller__mci_get_interests__start');
		$parameters = apply_filters( 'AHEE__EE_MCI_Controller__mci_get_interests__parameters', array('fields' => 'interests', 'exclude_fields' => 'interests._links', 'count' => 100) );

		if ( $list_id == NULL )
			$list_id = $this->list_id;
		if ( $category_id == NULL )
			$category_id = $this->category_id;

		try {
			$reply = $this->MailChimp->get('lists/'.$list_id.'/interest-categories/'.$category_id.'/interests', $parameters);
		} catch ( Exception $e ) {
			$this->set_error($e);
			return array();
		}
		if ( $this->MailChimp->success() && isset($reply['interests']) ) {
			return (array)$reply['interests'];
		} else {
			return array();
		}
	}



	/**
	 * Get the list of merge tags for a given list.
	 *
	 * @access public
	 * @param string $list_id  The ID of the List.
	 * @return array   MailChimp List of merge tags.
	 */
	public function mci_get_list_merge_vars( $list_id ) {
		do_action('AHEE__EE_MCI_Controller__mci_get_list_merge_vars__start');
		$parameters = apply_filters( 'AHEE__EE_MCI_Controller__mci_get_list_merge_vars__parameters', array('fields' => 'merge_fields', 'exclude_fields' => '_links,merge_fields._links', 'count' => 50) );
		if ( $list_id == NULL )
			$list_id = $this->list_id;

		try {
			$reply = $this->MailChimp->get( 'lists/'.$list_id.'/merge-fields', $parameters );
		} catch ( Exception $e ) {
			$this->set_error($e);
			return array();
		}
		if ( $this->MailChimp->success() && isset($reply['merge_fields']) ) {
			return (array)$reply['merge_fields'];
		} else {
			return array();
		}
	}



	/**
	 * Set up 'MailChimp List Integration' meta-box section contents.
	 *
	 * @access public
	 * @param WP_Post $event  The post object.
	 * @return string
	 */
	public function mci_set_metabox_contents( $event ) {
		// Verify the API key.
		if ( ! empty( $this->_config->api_settings->api_key ) ) {
			// Get saved list for this event (if there's one)
			$this->list_id = $this->mci_event_list( $event->ID );
			$this->category_id = $this->mci_event_list_group( $event->ID );

			$metabox_obj = new EE_MC_Metabox_Form( $this, $event->ID, $this->list_id, $this->category_id );
			return $metabox_obj->get_html_and_js();
		} else {
			$error_section = new EE_Form_Section_HTML( 
				EEH_HTML::div( EEH_HTML::span( esc_html__( 'Invalid MailChimp API.', 'event_espresso' ), 'important_mc_notice', 'important-notice' ) .
					EEH_HTML::br() . 
					esc_html__( 'Please visit the ', 'event_espresso' ) . 
					EEH_HTML::link( admin_url( 'admin.php?page=mailchimp' ), esc_html__( 'MailChimp Admin Page ', 'event_espresso' ) ) .
					esc_html__( 'to correct the issue.', 'event_espresso' ), 
				'no-lists-found-notice', 'espresso_mailchimp_integration_metabox' ) . EEH_HTML::divx()
			);
			return $error_section->get_html_and_js();
		}

	}



	/**
	 * Save the contents of 'MailChimp List Integration' meta-box.
	 *
	 * @access public
	 * @param int $event_id  An ID on the Event.
	 * @return void
	 */
	public function mci_save_metabox_contents( $event_id ) {
		// Clear MailChimp data on the current event and then save the new data.
		$lg_exists = EEM_Event_Mailchimp_List_Group::instance()->get_all( array( array('EVT_ID' => $event_id) ) );
		if ( ! empty($lg_exists) ) {
			foreach ($lg_exists as $list_group) {
				$list_group->delete();
			}
		}

		// Lists and Groups
		$list_id = $_POST['ee_mailchimp_lists'];
		if ( ! empty($_POST['ee_mailchimp_groups']) && ! empty($_POST['ee_mc_list_all_interests']) ) {
			$all_interests = $_POST['ee_mc_list_all_interests'];
			$group_ids = array();
			// Multidimensional array ? Straighten it up.
			foreach ( $_POST['ee_mailchimp_groups'] as $g_id ) {
				if ( is_array($g_id) ) {
					foreach ($g_id as $l2g_id) {
						$group_ids[] = $l2g_id;
					}
				} else {
					$group_ids[] = $g_id;
				}
			}
			// We need to save the list of all interests for the current MC List.
			foreach ( $all_interests as $interest ) {
				// Mark what lists were selected and not.
				if ( in_array($interest, $group_ids) ) {
					$interest .= '-true';
				} else {
					$interest .= '-false';
				}
				$new_list_group = EE_Event_Mailchimp_List_Group::new_instance(
					array(
						'EVT_ID' => $event_id,
						'AMC_mailchimp_list_id' => $list_id,
						'AMC_mailchimp_group_id' => $interest
					)
				);
				$new_list_group->save();
			}
		} else {
			$new_list_group = EE_Event_Mailchimp_List_Group::new_instance(
				array(
					'EVT_ID' => $event_id,
					'AMC_mailchimp_list_id' => $list_id,
					'AMC_mailchimp_group_id' => -1
				)
			);
			$new_list_group->save();
		}

		$qf_exists = EEM_Question_Mailchimp_Field::instance()->get_all( array( array('EVT_ID' => $event_id) ) );
		// Question Fields
		if ( isset($_POST['ee_mailchimp_qfields']) && is_array($_POST['ee_mailchimp_qfields']) && ! empty($_POST['ee_mailchimp_qfields']) ) {
			$qfields_list = $_POST['ee_mailchimp_qfields'];
			$list_form_rel = array();
			foreach ($qfields_list as $mc_question) {
				$encoded = base64_encode($mc_question);
				if ( isset($_POST[$encoded]) && $_POST[$encoded] != '-1' ) {
					$ev_question = $_POST[$encoded];
					$list_form_rel[$mc_question] = $ev_question;

					$q_found = false;
					// Update already present Q fields.
					foreach ($qf_exists as $question_field) {
						$mc_field = $question_field instanceof EE_Question_Mailchimp_Field ? $question_field->mc_field() : '';
						if ( $mc_field == $mc_question ) {
							EEM_Question_Mailchimp_Field::instance()->update(
								array('QST_ID' => $ev_question),
								array( array('EVT_ID' => $event_id, 'QMC_mailchimp_field_id' => $mc_question) )
							);
							$q_found = true;
						}
					}
					// Add Q field if was not present.
					if ( ! $q_found ) {
						$new_qfield = EE_Question_Mailchimp_Field::new_instance(
							array(
								'EVT_ID' => $event_id,
								'QST_ID' => $ev_question,
								'QMC_mailchimp_field_id' => $mc_question
							)
						);
						$new_qfield->save();
					}
				} else {
					$mcqe = EEM_Question_Mailchimp_Field::instance()->get_one( array( array('EVT_ID' => $event_id, 'QMC_mailchimp_field_id' => $mc_question) ) );
					if ( $mcqe != null )
						$mcqe->delete();
				}
			}
		}
	}



	/**
	 * Display the MailChimp user Lists for given event.
	 *
	 * @access public
	 * @param int $list_id
	 * @return string (HTML)
	 */
	public function mci_list_mailchimp_lists( $list_id = 0 ) {
		do_action('AHEE__EE_MCI_Controller__mci_list_mailchimp_lists__start');
		// Load the lists form.
		$lists_obj = new EE_MC_Lists_Form( $this, $list_id );
		return $lists_obj->get_html_and_js();
	}



	/**
	 * Display MailChimp interest Categories for the given event (depending on the selected List).
	 *
	 * @access public
	 * @param int $event_id The ID of the Event.
	 * @param int $list_id
	 * @return string (HTML)
	 */
	public function mci_list_mailchimp_groups( $event_id = 0, $list_id = 0 ) {
		do_action('AHEE__EE_MCI_Controller__mci_list_mailchimp_groups__start');
		if ( $list_id !== '-1' && $list_id !== NULL ) {
			// Load the interests form.
			$interest_categories_obj = new EE_MC_Interest_Categories_Form( $this, $event_id, $list_id );
			return $interest_categories_obj->get_html_and_js();
		}
		// Default to:
		return EEH_HTML::no_row();
	}



	/**
	 * Display MailChimp merge Fields of the given event (depending on the selected list).
	 *
	 * @access public
	 * @param int $event_id The ID of the Event.
	 * @param int $list_id
	 * @return string (HTML)
	 */
	public function mci_list_mailchimp_fields( $event_id = 0, $list_id = 0 ) {
		do_action('AHEE__EE_MCI_Controller__mci_list_mailchimp_fields__start');
		// No need to generate any content if no list selected.
		if ( $list_id === '-1' ) {
			return EEH_HTML::no_row();
		}
		$fields_obj = new EE_MC_Merge_Fields_Form( $this, $event_id, $list_id );
		return $fields_obj->get_html_and_js();
	}



	/**
	 * Get the list of question groups (EQG_primary) of the Event.
	 *
	 * @access public
	 * @param string $event_id  The ID of the Event.
	 * @return array  List of all primary QGs fn the Event.
	 */
	public function mci_get_event_question_groups( $event_id ) {
		return EE_Registry::instance()->load_model( 'Question_Group' )->get_all( array(
			array(
				'Event.EVT_ID' => $event_id,
				'Event_Question_Group.EQG_primary' => TRUE
			),
			'order_by'=>array( 'QSG_order' => 'ASC' )
		));
	}



	/**
	 * Get all questions of all primary question groups of the Event.
	 *
	 * @access public
	 * @param string $event_id  The ID of the Event.
	 * @return array  List of all primary Questions of the Event.
	 */
	public function mci_get_event_all_questions( $event_id ) {
		$questions = array();
		$question_groups = $this->mci_get_event_question_groups($event_id);
		if ( is_array($question_groups) && ! empty($question_groups) ) {
	        foreach ($question_groups as $QG_list) {
				if ( $QG_list instanceof EE_Question_Group ) {
					foreach ( $QG_list->questions() as $question ) {
						$qst = array(
							'QST_Name' => $question->get('QST_display_text'),
							'QST_ID' => $question->get('QST_ID'),
							'QST_system' => $question->get('QST_system')
						);
						if ( ! in_array($qst, $questions) ) {
							$questions[$question->ID()] = $qst;
						}
					}
				}
			}
			return $questions;
		} else {
			return array();
		}
	}



	/**
	 * Get MailChimp Event list
	 *
	 * @access public
	 * @param int $EVT_ID  The ID of the Event.
	 * @return EE_Event_Mailchimp_List_Group
	 */
	public function mci_event_list( $EVT_ID ) {
		EE_Registry::instance()->load_model( 'Event_Mailchimp_List_Group' );
		$event_list = EEM_Event_Mailchimp_List_Group::instance()->get_all( array( array( 'EVT_ID' => $EVT_ID ), 'limit' => 1 ));
		$event_list = reset( $event_list );
		return $event_list instanceof EE_Event_Mailchimp_List_Group ? $event_list->mc_list() : NULL;
	}



	/**
	 * Get MailChimp Event list group
	 *
	 * @access public
	 * @param int $EVT_ID  The ID of the Event.
	 * @return EE_Event_Mailchimp_List_Group[]
	 */
	public function mci_event_list_group( $EVT_ID ) {
		EE_Registry::instance()->load_model( 'Event_Mailchimp_List_Group' );
		$mc_list_groups = EEM_Event_Mailchimp_List_Group::instance()->get_all( array( array('EVT_ID' => $EVT_ID) ) );
		$event_list_groups = array();
		foreach ( $mc_list_groups as $mc_list_group ) {
			if ( $mc_list_group instanceof EE_Event_Mailchimp_List_Group ) {
				$event_list_groups[] = $mc_list_group->mc_group();
			}
		}
		return $event_list_groups;
	}



	/**
	 * Get a list of selected Interests.
	 *
	 * @access public
	 * @param int $EVT_ID  The ID of the Event.
	 * @return EE_Event_Mailchimp_List_Group[]
	 */
	public function mci_event_selected_interests( $EVT_ID ) {
		$selected = array();
		$all_interests = $this->mci_event_list_group( $EVT_ID );
		foreach ( $all_interests as $interest ) {
			if ( strpos($interest, '-true') !== false ) {
				$selected[] = str_replace('-true', '', $interest);
			}
		}
		return $selected;
	}



	/**
	 * Get MailChimp Event list question fields
	 *
	 * @access public
	 * @param int $EVT_ID  The ID of the Event.
	 * @return array
	 */
	public function mci_event_list_question_fields( $EVT_ID ) {
		EE_Registry::instance()->load_model( 'Question_Mailchimp_Field' );
		$mc_question_fields = EEM_Question_Mailchimp_Field::instance()->get_all( array( array('EVT_ID' => $EVT_ID) ) );
		$event_list_question_fields = array();
		foreach ( $mc_question_fields as $mc_question_field ) {
			if ( $mc_question_field instanceof EE_Question_Mailchimp_Field ) {
				$event_list_question_fields[ $mc_question_field->mc_field() ] = $mc_question_field->mc_event_question();
			}
		}
		return $event_list_question_fields;
	}



	/**
	 * Get MailChimp list or groups or question fields relationships, set for given event.
	 *
	 * @access public
	 * @param int $EVT_ID  The ID of the Event.
	 * @return array/string  Id of a group/list or an array of 'List fields - Event questions' relationships, or all in an array.
	 */
	public function mci_event_subscriptions( $EVT_ID ) {
		return array(
			'list' 		=> $this->mci_event_list( $EVT_ID ),
			'groups' 	=> $this->mci_event_list_group( $EVT_ID ),
			'qfields' 	=> $this->mci_event_list_question_fields( $EVT_ID ),
		);
	}


	/**
	 * Process the MailChimp error response.
	 *
	 * @access private
	 * @param array | Exception | boolean  $reply  An error reply from MailChimp API.
	 * @return void
	 */
	private function set_error( $reply ) {
		if ( $reply instanceof Exception ) {
			$error['code'] = $reply->getCode();
			$error['msg'] = $reply->getMessage();
			$error['body'] = $reply->getTraceAsString();
		} elseif ( is_array($reply) ) {
			$error['code'] = ( isset($reply['status']) ) ? $reply['status'] : 0;
			$error['msg'] = ( isset($reply['title']) ) ? $reply['title'] : __( 'Unknown MailChimp Error.', 'event_espresso' );
			$error['body'] = ( isset($reply['detail']) ) ? $reply['detail'] : '';
		} else {
			$error['code'] = 0;
			$error['msg'] = __( 'Invalid MailChimp API Key.', 'event_espresso' );
			$error['body'] = '';
		}
		$this->mcapi_error = apply_filters('FHEE__EE_MCI_Controller__mci_throw_error__mcapi_error', $error);
		EE_Log::instance()->log(__FILE__, __FUNCTION__, sprintf(
			__( 'MailChimp error "%1$s" with code: %2$s', 'event_espresso' ),
			$error['msg'],
			$error['code']
		), 'error');
	}


	/**
	 * mci_get_api_key
	 * @return string/bool  MC API Key if present/valid
	 */
	public function mci_get_api_key() {
		return $this->_api_key;
	}


	/**
	 * mci_get_response_error
	 * @return array
	 */
	public function mci_get_response_error() {
		return $this->mcapi_error;
	}

}