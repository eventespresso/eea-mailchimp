<?php

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
	 * @var \Drewm\MailChimp $MailChimp
     */
	private $MailChimp = NULL;

	/**
     * The selected List ID.
     * @access private
	 * @var int $list_id
	 */
	private $list_id = 0;

	/**
	 * The List of question IDs.
	 * @access private
	 * @var array $_question_list_id
	 */
	private $_question_list_id = array();



	/**
	 * Class constructor
	 *
	 * @param string $api_key
	 * @return EE_MCI_Controller
	 */
	function __construct( $api_key = '' ) {
		do_action( 'AHEE__EE_MCI_Controller__class_constructor__init_controller' );
		$this->_config = EED_Mailchimp::instance()->config();
		$this->_question_list_id = array(
			1 => 'fname',
			2 => 'lname',
			3 => 'email',
			4 => 'address',
			5 => 'address2',
			6 => 'city',
			7 => 'state',
			8 => 'country',
			9 => 'zip',
			10 => 'phone'
		);
		// verify api key
		$api_key = ! empty( $api_key ) ? $api_key : $this->_config->api_settings->api_key;
		$this->_api_key = $this->mci_is_api_key_valid( $api_key );
		// create
		require_once( ESPRESSO_MAILCHIMP_DIR . 'includes' . DS . 'MailChimp.class.php' );
		$this->MailChimp = new \Drewm\MailChimp( $this->_api_key );
		$reply = $this->MailChimp->call( 'lists/list', array( 'apikey' => $this->_api_key ) );
		$this->_api_key = $this->mci_is_api_key_valid( $this->_api_key, $reply );
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
		// if a call reply is present, then let's process that first
		if ( $call_reply === FALSE || ( isset( $call_reply['status'] ) && $call_reply['status'] == 'error' )) {
			$this->mci_throw_error( $call_reply );
			do_action( 'AHEE__EE_MCI_Controller__mci_is_api_key_valid__api_key_error' );
			unset( $this->MailChimp );
			return FALSE;
		}
		// MailChimp API does not check for the '-' and throws an error, so let's do the check ourselves.
		if ( ! strlen( $this->_api_key ) > 1 ||  strpos( $api_key, '-' ) === FALSE ) {
			$this->mci_throw_error( FALSE );
			do_action( 'AHEE__EE_MCI_Controller__mci_is_api_key_valid__api_key_error' );
			return FALSE;
		}
		// make sure api key only has one '-'
		$exp_key = explode( '-', $api_key );
		if ( ! is_array( $exp_key ) || count( $exp_key ) != 2 ) {
			$this->mci_throw_error( FALSE );
			do_action( 'AHEE__EE_MCI_Controller__mci_is_api_key_valid__api_key_error' );
			return FALSE;
		}
		do_action( 'AHEE__EE_MCI_Controller__mci_is_api_key_valid__api_key_ok' );
		return $api_key;
	}



	/**
	 * Retrieve EE_Registration objects from incoming data
	 *
	 * @access protected
	 * @param EED_Single_Page_Checkout || EE_SPCO_Reg_Step_Attendee_Information $spco_obj
	 * @return EE_Registration[]
	 */
	protected function _mci_get_registrations( $spco_obj ) {
		$registrations = array();
		// what kind of SPCO object did we receive?
		if ( $spco_obj instanceof EE_SPCO_Reg_Step_Attendee_Information ) {
			// for EE versions > 4.6
			if ( $spco_obj->checkout instanceof EE_Checkout && $spco_obj->checkout->transaction instanceof EE_Transaction ) {
				$registrations = $spco_obj->checkout->transaction->registrations( $spco_obj->checkout->reg_cache_where_params, TRUE );
			}
		} else if ( $spco_obj instanceof EED_Single_Page_Checkout ) {
			$transaction = EE_Registry::instance()->SSN->get_session_data( 'transaction' );
			// for EE versions < 4.6
			if ( $transaction instanceof EE_Transaction ) {
				$registrations = $transaction->registrations( array(), TRUE );
			}
		}
		return $registrations;
	}

	/**
     * Subscribe new attendee to MailChimp list selected for current event.
     *
     * @param EED_Single_Page_Checkout $spc_obj  Single Page Checkout (SPCO) Object.
     * @param array $valid_data  Valid data from the Attendee Information Registration form.
     * @return void
     */
	public function mci_submit_to_mailchimp( $spc_obj, $valid_data ) {
		do_action('AHEE__EE_MCI_Controller__mci_submit_to_mailchimp__start', $spc_obj, $valid_data);
		// Do not submit if the key is not valid or there is no valid submit data.
		if ( $this->MailChimp instanceof \Drewm\MailChimp && ! empty( $spc_obj )) {
			$registrations = $this->_mci_get_registrations( $spc_obj );
			// now loop thru registrations to get the related attendee objects
			if ( ! empty( $registrations )) {
				foreach ( $registrations as $registration ) {
					if ( $registration instanceof EE_Registration ) {
						$attendee = $registration->attendee();
						if ( $attendee instanceof EE_Attendee ) {
							$EVT_ID = $registration->event_ID();
							$event_list = $this->mci_event_list( $EVT_ID );
							// If no list selected for this event than skip the subscription.
							if ( ! empty( $event_list )) {
								$subscribe_args = array(
									'apikey' 				=> $this->_api_key,
									'id' 						=> $event_list,
									'email' 				=> array( 'email' => $attendee->email() ),
									'double_optin' 	=> isset( $this->_config->api_settings->skip_double_optin ) ? $this->_config->api_settings->skip_double_optin : TRUE
								);
								// Group vars
								$subscribe_args = $this->_add_event_group_vars_to_subscribe_args( $EVT_ID, $subscribe_args );
								// Question fields
								$subscribe_args = $this->_add_registration_question_answers_to_subscribe_args( $registration, $EVT_ID, $subscribe_args );
								// filter it
								$subscribe_args = apply_filters('FHEE__EE_MCI_Controller__mci_submit_to_mailchimp__subscribe_args', $subscribe_args );
								// Subscribe attendee
								$reply = $this->MailChimp->call( 'lists/subscribe', $subscribe_args );
								// If there was an error during subscription than process it.
								if ( isset( $reply['status'] ) && $reply['status'] == 'error' ) {
									$this->mci_throw_error( $reply );
									// If the error: 'email is already subscribed to a list' then just update the groups.
									if ( $reply['code'] == 214 ) {
										$this->MailChimp->call( 'lists/update-member', $subscribe_args );
									}
								}
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
	 * @param array $event_group
	 * @param array $subscribe_args
     * @return array  List of MailChimp lists.
     */
	protected function _process_event_group_subscribe_args( $event_group = array(),  $subscribe_args = array() ) {
		$grouping = explode( '-', $event_group );
		// Add groups to their groupings.
		if ( isset( $subscribe_args['merge_vars']['groupings'] )) {
			foreach ( $subscribe_args['merge_vars']['groupings'] as $key => $g_grouping ) {
				if ( $g_grouping['id'] == $grouping[1] ) {
					$subscribe_args['merge_vars']['groupings'][ intval( $key ) ]['groups'][] = base64_decode( $grouping[2] );
					continue;
				}
			}
		}
		if ( isset( $grouping[2] )) {
			$subscribe_args['merge_vars']['groupings'][] = array( 'id' => intval( $grouping[1] ), 'groups' => array( base64_decode( $grouping[2] )));
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
					EEM_Attendee::city_question_id        => 'STA_ID',
					EEM_Attendee::state_question_id       => 'CNT_ISO',
					EEM_Attendee::country_question_id     => 'ATT_zip',
					EEM_Attendee::zip_question_id         => 'ATT_email',
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
			if ( ! empty( $registration_answers )) {
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
		if ( ! is_array( $subscribe_args )) {
			throw new EE_Error( __( 'The MailChimp Subscriber arguments array is malformed!','event_espresso' ));
		}
		if ( ! isset( $subscribe_args['merge_vars'] )) {
			$subscribe_args['merge_vars'] = array();
		}
			// get MailChimp question fields
		$question_fields = $this->mci_event_list_question_fields( $EVT_ID );
		// get the registrant's attendee details
		$question_answers = $this->_get_attendee_details_for_registration( $registration );
		// get the registrant's question answers
		$question_answers = $this->_get_question_answers_for_registration( $registration, $question_answers );
		foreach ( $question_fields as $mc_list_field => $question_ID ) {
			// Older version used names for an IDs (now using int).
			$q_id = ( is_numeric($question_ID) )? $question_ID : array_search($question_ID, $this->_question_list_id);
			if ( isset( $question_answers[ $q_id ] )) {
				// If question field is a State then get the state name not the code.
				if ( $q_id === 7 ) {	// If a state.
					$state = EEM_State::instance()->get_one_by_ID( $question_answers[ $q_id ] );
					$subscribe_args['merge_vars'][ $mc_list_field ] = $state->name();
				} else if ( is_array( $question_answers[ $q_id ] )) {
					$selected = '';
					foreach ( $question_answers[ $q_id ] as $q_key => $q_value ) {
						$selected .= $selected == '' ? $q_value : ', ' . $q_value;
					}
					$subscribe_args['merge_vars'][$mc_list_field] = $selected;
				} else {
					$subscribe_args['merge_vars'][$mc_list_field] = $question_answers[ $q_id ];
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
		$reply = $this->MailChimp->call('lists/list', array('apikey' => $this->_api_key));
		if ( ($reply != false) && isset($reply['data']) && ! empty($reply['data'])  ) {
			return $reply['data'];
		} else {
			return array();
		}
	}



	/**
	 * Get the list of interest groupings for a given list.
	 *
	 * @access public
	 * @param string $list_id  The ID of the List.
	 * @return array  List of MailChimp groups of selected List.
	 */
	public function mci_get_users_groups( $list_id ) {
		do_action('AHEE__EE_MCI_Controller__mci_get_users_groups__start');
		if ( $list_id == NULL )
			$list_id = $this->list_id;
		$reply = $this->MailChimp->call('lists/interest-groupings', array('apikey' => $this->_api_key, 'id' => $list_id));
		if ( $reply != FALSE && ! empty( $reply ) && ( ! isset($reply['status']) || $reply['status'] != 'error') ) {
			return $reply;
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
		if ( $list_id == NULL )
			$list_id = $this->list_id;
		$reply = $this->MailChimp->call('lists/merge-vars', array('apikey' => $this->_api_key, 'id' => array($list_id)));
		if ( $reply != FALSE && isset( $reply['data'] ) && ! empty( $reply['data'] )) {
			return $reply['data'][0]['merge_vars'];
		} else {
			return array();
		}
	}



	/**
	 * mci_ajax_loading
	 *
	 * @access public
	 * @param string $id  extra css id tag to be appended to existing
	 * @return void
	 */
	public function mci_ajax_loading( $id = '' ) {
		$id = ! empty( $id ) ? 'id="ee-mailchimp-ajax-loading-' . $id . '" ' : '';
		?>
		<div <?php echo $id; ?>class="ee-mailchimp-ajax-loading" style="display:none;">
			<span class="ee-spinner ee-spin"></span>
			<span class="ee-loading-txt small-text"><?php _e( 'loading...', 'event_espresso' );?></span>
		</div>
		<?php
	}



	/**
	 * Set up 'MailChimp List Integration' meta-box section contents.
	 *
	 * @access public
	 * @param WP_Post $event  The post object.
	 * @return void
	 */
	public function mci_set_metabox_contents( $event ) {
		// verify api key
		if ( ! empty( $this->_config->api_settings->api_key )) {
			// Get saved list for this event (if there's one)
			$this->list_id = $this->mci_event_list( $event->ID );
			?>
			<div class="espresso_mailchimp_integration_metabox">
				<div class="espresso_mci_lists_groups">
					<div id="espresso-mci-lists">
						<?php
						// Lists / Groups section
						$this->mci_list_mailchimp_lists( $this->list_id );
						?>
					</div>
					<?php $this->mci_ajax_loading( 'groups' ); ?>
					<div id="espresso-mci-groups-list">
						<?php $this->mci_list_mailchimp_groups( $event->ID, $this->list_id ); ?>
					</div>
				</div>
				<?php $this->mci_ajax_loading( 'fields' ); ?>
				<div id="espresso-mci-list-fields" class="espresso_mci_list_fields">
					<?php $this->mci_list_mailchimp_fields( $event->ID, $this->list_id ); ?>
				</div>
			</div>
			<?php
		} else {
			?>
			<div class="espresso_mailchimp_integration_metabox">
				<p>
					<?php
					printf(
						__( '%1$sInvalid MailChimp API%2$s%3$sPlease visit the %4$sMailChimp Admin Page%5$s to correct the issue.', 'event_espresso' ),
						'<span class="important-notice">',
						'</span>',
						'<br />',
						'<a href="' . admin_url( 'admin.php?page=mailchimp' ) . '">',
						'</a>'
					);
					?>
				</p>
			</div>
			<?php
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
		if ( ! empty($_POST['ee_mailchimp_groups']) ) {
			$group_ids = $_POST['ee_mailchimp_groups'];
			foreach ($group_ids as $group) {
				$new_list_group = EE_Event_Mailchimp_List_Group::new_instance(
					array(
						'EVT_ID' => $event_id,
						'AMC_mailchimp_list_id' => $list_id,
						'AMC_mailchimp_group_id' => $group
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
		if ( isset($_POST['ee_mailchimp_qfields']) ) {
			$qfields_list = base64_decode( $_POST['ee_mailchimp_qfields'] );
			$qfields_list = maybe_unserialize( $qfields_list );
			$qfields_list = is_array( $qfields_list ) ? $qfields_list : array();
			$list_form_rel = array();
			foreach ($qfields_list as $mc_question) {
				if ( ($_POST[base64_encode($mc_question)] != '-1') ) {
					$ev_question = $_POST[base64_encode($mc_question)];
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
	 * @return void
	 */
	public function mci_list_mailchimp_lists( $list_id = 0 ) {
		do_action('AHEE__EE_MCI_Controller__mci_list_mailchimp_lists__start');
		?>
		<label for="ee-mailchimp-lists"><?php _e( 'Please select a List:', 'event_espresso' );?></label><br />
		<?php
		$mc_lists = $this->mci_get_users_lists();
		array_push( $mc_lists, array( 'id' => '-1', 'name' => __( 'Do not send to MailChimp', 'event_espresso' )));
		if ( ! empty( $mc_lists )) {
			?>
			<select id="ee-mailchimp-lists" name="ee_mailchimp_lists" class="ee_mailchimp_dropdowns">
				<?php foreach ( $mc_lists as $list ) { ?>
					<option value="<?php echo $list['id']; ?>" <?php echo ( $list_id == $list['id'] ) ? 'selected="selected"' : ''; ?>><?php echo $list['name']; ?></option>
				<?php } ?>
			</select>
			<?php
		} else {
			?>
			<p class="important-notice"><?php _e( 'No lists found! Please log into your MailChimp account and create at least one mailing list.', 'event_espresso' );?></p>
			<?php
		}
	}



	/**
	 * Display MailChimp interest Groupings for the given event (depending on the selected List).
	 *
	 * @access public
	 * @param int $event_id The ID of the Event.
	 * @param int $list_id
	 * @return void
	 */
	public function mci_list_mailchimp_groups( $event_id = 0, $list_id = 0 ) {
		do_action('AHEE__EE_MCI_Controller__mci_list_mailchimp_groups__start');
		// Get saved group for this event (if there's one)
		$event_list_group = $this->mci_event_list_group( $event_id );
		$user_groups = $this->mci_get_users_groups( $list_id );

		?>
		<form id="ee-mailchimp-groups-list" method="post">
			<label for="ee-mailchimp-groups"><?php _e( 'Please select a Group:', 'event_espresso' );?></label>
			<dl id="ee-mailchimp-groups" class="ee_mailchimp_dropdowns">
				<?php
				if ( ! empty( $user_groups )) {
					foreach ( $user_groups as $user_group ) {
						?>
						<dt><b><?php echo $user_group['name']; ?></b></dt>
						<?php
						foreach ( $user_group['groups'] as $group ) {
							$group_id = $group['bit'] . '-' . $user_group['id'] . '-' . base64_encode($group['name']);
							?>
							<dd>
								<input type="checkbox" id="<?php echo $group_id; ?>" value="<?php echo $group_id; ?>" name="ee_mailchimp_groups[]" <?php echo ( in_array( $group_id, $event_list_group )) ? 'checked' : ''; ?>>
								<label for="<?php echo $group_id; ?>"><?php echo $group['name']; ?></label>
							</dd>
							<?php
						}
					}
				} else {
					?>
					<p class="important-notice"><?php _e( 'No groups found for this List.', 'event_espresso' );?></p>
					<?php
				}
				?>
			</dl>
		</form>
		<?php
	}



	/**
	 * Display MailChimp merge Fields of the given event (depending on the selected list).
	 *
	 * @access public
	 * @param int $event_id The ID of the Event.
	 * @param int $list_id
	 * @return void
	 */
	public function mci_list_mailchimp_fields( $event_id = 0, $list_id = 0  ) {
		$list_fields = $this->mci_get_list_merge_vars( $list_id );
		$selected_fields = $this->mci_event_list_question_fields( $event_id );
		$evt_questions = $this->mci_get_event_all_questions($event_id);

		// To save the list of mailchimp Fields for the future use.
		$hide_fields = array();
		if ( ! empty($list_fields) ) {
			?>
			<div id="espresso-mci-list-merge-fields">
				<table class="espresso_mci_merge_fields_tb">
					<thead>
						<tr class="ee_mailchimp_field_heads">
							<th><b><?php _e( 'List Fields', 'event_espresso' );?></b></th>
							<th><b><?php _e( 'Form Fields', 'event_espresso' );?></b></th>
						</tr>
					</thead>
					<tbody>
						<?php
						foreach ($list_fields as $l_field) {
							$starred = '*';
							?>
							<tr>
								<td>
									<p id="mci-field-<?php echo base64_encode($l_field['name']); ?>" class="ee_mci_list_fields">
										<?php echo $l_field['name']; echo ( $l_field['req'] ) ? '<span class="nci_asterisk">' . $starred . '</span>' : ''; ?>
									</p>
								</td>
								<td>
									<select id="event-question-<?php echo base64_encode($l_field['name']); ?>" name="<?php echo base64_encode($l_field['tag']); ?>" class="ee_event_fields_selects" >
										<option value="-1"><?php _e( 'none', 'event_espresso' );?></option>
											<?php foreach ($evt_questions as $q_field) { ?>
												<option value="<?php echo $q_field['QST_ID']; ?>"
													<?php
													// Default to main fields if exist:
													if (
														( isset( $l_field['tag'], $selected_fields[ $l_field['tag'] ] ) 
														&& ( $selected_fields[ $l_field['tag'] ] == $q_field['QST_ID'] || ( isset($this->_question_list_id[$q_field['QST_ID']]) && $selected_fields[ $l_field['tag'] ] == $this->_question_list_id[$q_field['QST_ID']] ) ) )
														|| ( ($q_field['QST_ID'] == 3 || $q_field['QST_ID'] == 'email') && $l_field['tag'] == 'EMAIL' && ! array_key_exists( 'EMAIL', $selected_fields ))
														|| ( ($q_field['QST_ID'] == 2 || $q_field['QST_ID'] == 'lname') && $l_field['tag'] == 'LNAME' && ! array_key_exists( 'LNAME', $selected_fields ))
														|| ( ($q_field['QST_ID'] == 1 || $q_field['QST_ID'] == 'fname') && $l_field['tag'] == 'FNAME' && ! array_key_exists( 'FNAME', $selected_fields ))
													) {
														echo 'selected';
													}
													?>>
													<?php echo $q_field['QST_Name']; ?>
												</option>
											<?php } ?>
									</select>
								</td>
							</tr>
							<?php
							$hide_fields[] = $l_field['tag'];
						}
						?>
					</tbody>
				</table>
				<input type="hidden" name="ee_mailchimp_qfields" value="<?php echo base64_encode(serialize($hide_fields)); ?>" />
			</div>
			<?php
		}
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
			'order_by'=>array( 'QSG_order'=>'ASC' )
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
					foreach ( $QG_list->questions() as $q_list ) {
						$questions[] = array( 
							'QST_Name' => $q_list->get('QST_display_text'),
							'QST_ID' => $q_list->get('QST_ID')
						);
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
		$event_list = current( $event_list );
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
		$event_list = $this->mci_event_list( $EVT_ID );
		$event_groups = $this->mci_event_list_group( $EVT_ID );
		$question_fields = $this->mci_event_list_question_fields( $EVT_ID );
		return array(
			'list' 			=> isset( $event_list[0], $event_list[0]['ListID'] ) ? $event_list[0]['ListID'] : array(),
			'groups' 	=> isset( $event_groups[0], $event_groups[0]['GroupID'] ) ? $event_groups[0]['GroupID'] : array(),
			'qfields' 	=> isset( $question_fields[0], $question_fields[0]['FieldRel'] ) ? $question_fields[0]['FieldRel'] : array(),
		);
	}

	/**
	 * Get MailChimp list or groups or question fields relationships, set for given event.
	 *
	 * @access public
	 * @param string $evt_id  The ID of the Event.
	 * @param string $target (pass: 'groups' or 'list' or 'question_fields')  What to get, groups or list or fields relationships. If target = null then both are returned.
	 * @return array/string  Id of a group/list or an array of 'List fields - Event questions' relationships, or all in an array.
	 */
	public function mci_event_subscriptions_OLD( $evt_id, $target = NULL ) {
		$evt_list = EEM_Event_Mailchimp_List_Group::instance()->get_one( array( array('EVT_ID' => $evt_id) ) );
		if ( $evt_list != null ) {
			if ( $evt_list instanceof EE_Event_Mailchimp_List_Group ) {
				$evt_list = $evt_list->mc_list();
			}
		}
		$evt_groups = array();
		$mc_list_group = EEM_Event_Mailchimp_List_Group::instance()->get_all( array( array('EVT_ID' => $evt_id) ) );
		foreach ($mc_list_group as $mc_group) {
			if ( $mc_group instanceof EE_Event_Mailchimp_List_Group ) {
				$evt_groups[] = $mc_group->mc_group();
			}
		}
		$evt_qfields = array();
		$mc_question_field = EEM_Question_Mailchimp_Field::instance()->get_all( array( array('EVT_ID' => $evt_id) ) );
		foreach ($mc_question_field as $mc_qfield) {
			if ( $mc_qfield instanceof EE_Question_Mailchimp_Field ) {
				$evt_qfields[$mc_qfield->mc_field()] = $mc_qfield->mc_event_question();
			}
		}

		$evt_subs = array();
		switch ( $target ) {
			case 'list':
				if ( ! empty($evt_list) ) {
					$evt_subs = $evt_list;
				} else {
					$evt_subs = -1;
				}
				break;
			case 'groups':
				if ( ! empty($evt_groups) )
					$evt_subs = $evt_groups;
				break;
			case 'question_fields':
				if ( ! empty($evt_qfields) )
					$evt_subs = $evt_qfields;
				break;
			case NULL:
				if ( ! empty($evt_groups) && ! empty($evt_list) )
					$evt_subs = array('list' => $evt_list, 'groups' => $evt_groups, 'qfields' => $evt_qfields);
				break;
		}
		return $evt_subs;
	}


	/**
	 * Process the MailChimp error response.
	 *
	 * @access private
	 * @param array | bool $reply  An error reply from MailChimp API.
	 * @return void
	 */
	private function mci_throw_error( $reply ) {
		$error['code'] = 0;
		$error['msg'] = __( 'Invalid MailChimp API Key.', 'event_espresso' );
		$error['body'] = '';
		if ( $reply != false ) {
			$error['code'] = $reply['code'];
			$error['msg'] = $reply['name'];
			$error['body'] = $reply['error'];
		}
		$this->mcapi_error = apply_filters('FHEE__EE_MCI_Controller__mci_throw_error__mcapi_error', $error);
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

?>