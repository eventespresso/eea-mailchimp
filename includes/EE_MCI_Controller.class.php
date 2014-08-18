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
    * Error details.
    * @access private
    */
   private $mcapi_error = array();

   /**
    * MailChimp API Object.
    * @access private
    */
   private $MailChimp = NULL;

   /**
    * The selected List ID.
    * @access private
    */
   private $list_id = 0;

   /**
    * Class constructor
    * 
    * @return void
    */
   function __construct() {
      do_action('AHEE__EE_MCI_Controller__class_constructor__init_controller');
      $this->mci_set_mailchimp_api();
   }

   /**
    * Create an Object for the MailChimp API.
    * 
    * @access public
    * @return void
    */
   public function mci_set_mailchimp_api() {
      $mcapi_settings = EE_Config::instance()->get_config( 'addons', 'EE_Mailchimp', 'EE_Mailchimp_Config' );
      $set_apik = $mcapi_settings->api_settings->api_key;
      $api_key = ( (strlen($set_apik) > 1) && $this->mci_is_api_key_valid($set_apik) ) ? $set_apik : 'invalid-usX';
      $this->MailChimp = new \Drewm\MailChimp($api_key);
   }

   /**
    * Validate the MailChimp API key. If key not provided then the one that is saved in the settings will be checked.
    * 
    * @access public
    * @param string $api_key  MailChimp API Key.
    * @return bool  Is Key valid or not
    */
   public function mci_is_api_key_valid( $api_key = NULL ) {
      do_action('AHEE__EE_MCI_Controller__mci_is_api_key_valid__start');
      $mcapi_settings = EE_Config::instance()->get_config( 'addons', 'EE_Mailchimp', 'EE_Mailchimp_Config' );
      if ( $api_key == NULL ) {
         $api_key = $mcapi_settings->api_settings->api_key;
      }
      // MailChimp API does not check for the '-' and throws an error, so let's do the check ourselves.
      $exp_key = explode('-', $api_key);
      if ( (strpos($api_key, '-') === false) || ! is_array($exp_key) || (count($exp_key) <= 1) || (count($exp_key) > 2) ) {
         $this->mci_throw_error(false);
         do_action( 'AHEE__EE_MCI_Controller__mci_is_api_key_valid__api_key_error' );
         return false;
      }
      $MailChimp = new \Drewm\MailChimp($api_key);
      $reply = $MailChimp->call('lists/list', array('apikey' => $api_key));
      if ( ($reply == false) || ( isset($reply['status']) && $reply['status'] == 'error' ) ) {
         $this->mci_throw_error($reply);
         do_action( 'AHEE__EE_MCI_Controller__mci_is_api_key_valid__api_key_error' );
         return false;
      } else {
         do_action( 'AHEE__EE_MCI_Controller__mci_is_api_key_valid__api_key_ok' );
         return true;
      }
   }

   /**
    * Subscribe new attendee to MailChimp list selected for current event.
    *
    * @param EED_Single_Page_Checkout $spc_obj  Single Page Checkout (SPCO) Object.
    * @param array $valid_data  Valid data from the Attendee Information Registration form.
    * @return void
    */
   public function mci_submit_to_mailchimp( $spc_obj, $valid_data ) {
      $attendee = false;
      $subscriber = array();
      do_action('AHEE__EE_MCI_Controller__mci_submit_to_mailchimp__start', $spc_obj, $valid_data);
      // Do not submit if the key is not valid or there is no valid submit data.
      if ( $this->mci_is_api_key_valid() && ! empty( $valid_data ) ) {
         foreach ($valid_data as $subscriber) {
            if ( ! empty($subscriber) && isset($subscriber['email']) ) {
               $attendee = EE_Registry::instance()->LIB->EEM_Attendee->find_existing_attendee( array(
                  'ATT_fname' => $subscriber['fname'],
                  'ATT_lname' => $subscriber['lname'],
                  'ATT_email' => $subscriber['email']
                  ));
               /**
                * By this point the attendee should be already added.
                * If attendee was not found (not subscribed) then there was an error with the data so we will not subscribe him to to MailChimp list at this point.
                */
               if ( $attendee instanceof EE_Attendee ) {
                  $transaction = EE_Registry::instance()->SSN->get_session_data('transaction');
                  $registration = $transaction->primary_registration();
                  $att_id = $registration->get('ATT_ID');
                  $evt_id = $registration->get('EVT_ID');
                  $evt_list = $this->mci_event_subscriptions($evt_id, 'list');
                  $evt_groups = $this->mci_event_subscriptions($evt_id, 'groups');
                  $evt_qfields = $this->mci_event_subscriptions($evt_id, 'question_fields');
                  // If no list selected for this event than skip the subscription.
                  if ( $evt_list != -1 ) {
                     $mcapi_settings = EE_Config::instance()->get_config( 'addons', 'EE_Mailchimp', 'EE_Mailchimp_Config' );
                     $api_key = $mcapi_settings->api_settings->api_key;
                     $double_optin = ( isset($mcapi_settings->api_settings->skip_double_optin) ) ? $mcapi_settings->api_settings->skip_double_optin : true;
                     $args = array('apikey' => $api_key, 'id' => $evt_list, 'email' => array('email' => $subscriber['email']), 'double_optin' => $double_optin);
                     if ( ($evt_groups != -1) && ! empty($evt_groups) ) {
                        if ( is_array($evt_groups) ) {
                           foreach ($evt_groups as $grgs) {
                              $grouping = explode('-', $grgs);
                              // Add groups to their groupings.
                              if ( @isset($args['merge_vars']['groupings']) ) {
                                 foreach ( $args['merge_vars']['groupings'] as $key => $g_grouping ) {
                                    if ( $g_grouping['id'] == $grouping[1] ) {
                                       $args['merge_vars']['groupings'][intval($key)]['groups'][] = base64_decode($grouping[2]);
                                       continue;
                                    }
                                 }
                              }
                              if ( isset($grouping[2]) ) {
                                 $args['merge_vars']['groupings'][] = array('id' => intval($grouping[1]), 'groups' => array(base64_decode($grouping[2])));
                              }
                           }
                        } else {
                           $grouping = explode('-', $evt_groups);
                           if ( isset($grouping[2]) ) {
                             $args['merge_vars']['groupings'][] = array('id' => intval($grouping[1]), 'groups' => array(base64_decode($grouping[2])));
                           }
                        }
                     }
                     // Question fields.
                     foreach ($evt_qfields as $list_field => $event_question) {
                        if ( @isset($subscriber[$event_question]) ) {
                           // If Qfield is a State then get the state name not the code.
                           if ( $event_question === 'state' ) {
                              $state = EEM_State::instance()->get_one_by_ID($subscriber[$event_question]);
                              $args['merge_vars'][$list_field] = $state->name();
                           } elseif ( is_array($subscriber[$event_question]) ) {
                              $selected = '';
                              foreach ($subscriber[$event_question] as $q_key => $q_value) {
                                 if ( ! empty($selected) )
                                    $selected .= ', ';
                                 $selected .= $q_value;
                              }
                              $args['merge_vars'][$list_field] = $selected;
                           } else {
                              $args['merge_vars'][$list_field] = $subscriber[$event_question];
                           }
                        }
                     }
                     $subscribe_args = apply_filters('FHEE__EE_MCI_Controller__mci_submit_to_mailchimp__subscribe_args', $args);
                     // Subscribe attendee
                     $reply = $this->MailChimp->call('lists/subscribe', $subscribe_args);
                     // If there was an error during subscription than process it.
                     if ( isset($reply['status']) && ($reply['status'] == 'error') ) {
                        $this->mci_throw_error($reply);
                        // If the error: 'email is already subscribed to a list' then just update the groups.
                        if ( $reply['code'] == 214 ) {
                           $reply = $this->MailChimp->call('lists/update-member', $subscribe_args);
                        }
                     }
                  }
               }
            }
         }
      }
   }

   /**
    * Retrieve all of the lists defined for your user account.
    * 
    * @access public
    * @return array  List of MailChimp lists.
    */
   public function mci_get_users_lists() {
      do_action('AHEE__EE_MCI_Controller__mci_get_users_lists__start');
      $mcapi_settings = EE_Config::instance()->get_config( 'addons', 'EE_Mailchimp', 'EE_Mailchimp_Config' );
      $api_key = $mcapi_settings->api_settings->api_key;
      $reply = $this->MailChimp->call('lists/list', array('apikey' => $api_key));
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
      $mcapi_settings = EE_Config::instance()->get_config( 'addons', 'EE_Mailchimp', 'EE_Mailchimp_Config' );
      $api_key = $mcapi_settings->api_settings->api_key;
      if ( $list_id == NULL )
         $list_id = $this->list_id;
      $reply = $this->MailChimp->call('lists/interest-groupings', array('apikey' => $api_key, 'id' => $list_id));
      if ( ($reply != false) && ! empty($reply) && (@$reply['status'] != 'error') ) {
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
      $mcapi_settings = EE_Config::instance()->get_config( 'addons', 'EE_Mailchimp', 'EE_Mailchimp_Config' );
      $api_key = $mcapi_settings->api_settings->api_key;
      if ( $list_id == NULL )
         $list_id = $this->list_id;
      $reply = $this->MailChimp->call('lists/merge-vars', array('apikey' => $api_key, 'id' => array($list_id)));
      if ( ($reply != false) && isset($reply['data']) && ! empty($reply['data'])  ) {
        return $reply['data'][0]['merge_vars'];
      } else {
        return array();
      }
   }

   /**
    * Set up 'MailChimp List Integration' meta-box section contents.
    * 
    * @access public
    * @param WP_Post $event  The post object.
    * @return void
    */
   public function mci_set_metabox_contents( $event ) {
      if ( $this->mci_is_api_key_valid() ) {
         ?>
         <div class="espresso_mailchimp_integration_metabox">
            <div class="espresso_mci_lists_groups">
               <div id="espresso-mci-lists">
                   <?php
                   // Lists / Groups section
                   $this->mci_list_mailchimp_lists( $event->ID ); ?>
               </div>
               <div id="espresso-mci-groups-list">
                  <?php $this->mci_list_mailchimp_groups( $event->ID ); ?>
               </div>
            </div>
            <div id="espresso-mci-list-fields" class="espresso_mci_list_fields">
               <?php
               // List fields section
               $this->mci_list_mailchimp_fields( $event->ID );
               ?>
            </div>
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
      $qfields_list = base64_decode($_POST['ee_mailchimp_qfields']);
      if ( @unserialize($qfields_list) !== false ) {
        $qfields_list = unserialize($qfields_list);
      } else {
        $qfields_list = array();
      }
      $list_form_rel = array();
      foreach ($qfields_list as $mc_question) {
        if ( ($_POST[base64_encode($mc_question)] != '-1') ) {
          $ev_question = $_POST[base64_encode($mc_question)];
          $list_form_rel[$mc_question] = $ev_question;

          $q_found = false;
          // Update already present Q fields.
          foreach ($qf_exists as $question_field) {
            $mc_field = $question_field->mc_field();
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

   /**
    * Display the MailChimp user Lists for given event.
    * 
    * @access public
    * @param string $event_id  The ID of the Event.
    * @return void
    */
   public function mci_list_mailchimp_lists( $event_id ) {
      do_action('AHEE__EE_MCI_Controller__mci_list_mailchimp_lists__start');
      // Get saved list for this event (if there's one)
      $selected_list = $this->mci_event_subscriptions($event_id, 'list');
      $mc_lists = $this->mci_get_users_lists();
      ?>
      <label for="ee-mailchimp-lists">Please select a List:</label><br />
      <select id="ee-mailchimp-lists" name="ee_mailchimp_lists" class="ee_mailchimp_dropdowns">
         <option value="-1" <?php echo ( $selected_list === -1 ) ? 'selected' : ''; ?>>Do Not send to MailChimp</option>
         <?php
            foreach ( $mc_lists as $list_no => $list ) {
               ?>
               <option value="<?php echo $list['id']; ?>" <?php echo ( $selected_list === $list['id'] ) ? 'selected' : ''; ?>><?php echo $list['name']; ?></option>
               <?php
            }
         ?>
      </select>
      <?php
      $this->list_id = $selected_list;
   }

   /**
    * Display MailChimp interest Groupings for the given event (depending on the selected List).
    * 
    * @access public
    * @param string $event_id  The ID of the Event.
    * @param string $list_id  The ID of the List.
    * @return void
    */
   public function mci_list_mailchimp_groups( $event_id, $list_id = NULL ) {
      do_action('AHEE__EE_MCI_Controller__mci_list_mailchimp_groups__start');
      // Get saved group for this event (if there's one)
      $selected_gorup = $this->mci_event_subscriptions($event_id, 'groups');
      $mc_gorups = $this->mci_get_users_groups($list_id);
      ?>
      <form id="ee-mailchimp-groups-list" method="post">
         <label for="ee-mailchimp-groups">Please select a Group:</label>
         <dl id="ee-mailchimp-groups" class="ee_mailchimp_dropdowns">
            <?php
            if ( ! empty($mc_gorups) ) {
               foreach ( $mc_gorups as $grouping ) {
                  ?>
                  <dt><b><?php echo $grouping['name']; ?></b></dt>
                  <?php
                  foreach ( $grouping['groups'] as $group ) {
                     $group_id = $group['bit'] . '-' . $grouping['id'] . '-' . base64_encode($group['name']);
                     ?>
                     <dd><input type="checkbox" id="<?php echo $group_id; ?>" value="<?php echo $group_id; ?>" name="ee_mailchimp_groups[]" <?php echo ( in_array($group_id, $selected_gorup) ) ? 'checked' : ''; ?>>
                        <label for="<?php echo $group_id; ?>"><?php echo $group['name']; ?></label>
                     </dd>
                     <?php
                  }
               }
            } else {
               ?>
               <p><b>No groups found for this List.</b></p>
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
    * @param string $event_id  The ID of the Event.
    * @return void
    */
   public function mci_list_mailchimp_fields( $event_id, $list_id = NULL  ) {
      $list_fields = $this->mci_get_list_merge_vars( $list_id );
      $selected_fields = $this->mci_event_subscriptions($event_id, 'question_fields');
      $evt_questions = $this->mci_get_event_all_questions($event_id);
      // To save the list of mailchimp Fields for the future use.
      $hide_fields = array();
      if ( ! empty($list_fields) ) {
        ?>
        <div id="espresso-mci-list-merge-fields">
          <table class="espresso_mci_merge_fields_tb">
            <tr class="ee_mailchimp_field_heads">
              <th><b>List Fields</b></th>
              <th><b>Form Fields</b></th>
            </tr>
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
                    <option value="-1">none</option>
                    <?php foreach ($evt_questions as $q_field) { ?>
                      <option value="<?php echo $q_field['QST_ID']; ?>" 
                        <?php 
                          // Default to main fields if exist:
                          echo ( (@$selected_fields[$l_field['tag']] == $q_field['QST_ID']) 
                            || (($q_field['QST_ID'] == 'email') && ($l_field['tag'] == 'EMAIL') && ! array_key_exists('EMAIL', $selected_fields)) 
                            || (($q_field['QST_ID'] == 'lname') && ($l_field['tag'] == 'LNAME') && ! array_key_exists('LNAME', $selected_fields)) 
                            || (($q_field['QST_ID'] == 'fname') && ($l_field['tag'] == 'FNAME') && ! array_key_exists('FNAME', $selected_fields)) ) ? 'selected' : ''; 
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
      $question_groups = EE_Registry::instance()->load_model( 'Question_Group' )->get_all( array( 
        array( 
          'Event.EVT_ID' => $event_id, 
          'Event_Question_Group.EQG_primary' => TRUE
        ),
        'order_by'=>array( 'QSG_order'=>'ASC' )
      ));
      return $question_groups;
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
          foreach ($QG_list->questions() as $q_list) {
            $questions[] = array( 'QST_Name' => $q_list->get('QST_display_text'), 'QST_ID' => ( $q_list->get('QST_system') != '' ) ? $q_list->get('QST_system') : $q_list->get('QST_ID') );
          }
        }
        return $questions;
      } else {
        return array();
      }
   }

   /**
    * Get MailChimp list or groups or question fields relationships, set for given event.
    * 
    * @access public
    * @param string $evt_id  The ID of the Event.
    * @param string $target (pass: 'groups' or 'list' or 'question_fields')  What to get, groups or list or fields relationships. If target = null then both are returned.
    * @return array/string  Id of a group/list or an array of 'List fields - Event questions' relationships, or all in an array.
    */
   public function mci_event_subscriptions( $evt_id, $target = NULL ) {
      $mc_list_group = EEM_Event_Mailchimp_List_Group::instance()->get_all( array( array('EVT_ID' => $evt_id) ) );
      $mc_question_field = EEM_Question_Mailchimp_Field::instance()->get_all( array( array('EVT_ID' => $evt_id) ) );
      $evt_list = EEM_Event_Mailchimp_List_Group::instance()->get_one( array( array('EVT_ID' => $evt_id) ) );
      if ( $evt_list != null ) {
        $evt_list = $evt_list->mc_list();
      }
      $evt_groups = $evt_qfields = array();
      foreach ($mc_list_group as $mc_group) {
        $evt_groups[] = $mc_group->mc_group();
      }
      foreach ($mc_question_field as $mc_qfield) {
        $evt_qfields[$mc_qfield->mc_field()] = $mc_qfield->mc_event_question();
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
               $evt_subs = array('list' => $evt_list[0]['ListID'], 'groups' => $evt_groups[0]['GroupID'], 'qfields' => $evt_qfields[0]['FieldRel']);
            break;
      }
      return $evt_subs;
   }

   /**
    * Process the MailChimp error response.
    * 
    * @access private
    * @param array $reply  An error reply from MailChimp API.
    * @return void
    */
   private function mci_throw_error( $reply ) {
      $error['code'] = 0;
      $error['msg'] = 'Unknown MailChimp API Error';
      $error['body'] = '';
      if ( $reply != false ) {
         $error['code'] = $reply['code'];
         $error['msg'] = $reply['name'];
         $error['body'] = $reply['error'];
      }
      $this->mcapi_error = apply_filters('FHEE__EE_MCI_Controller__mci_throw_error__mcapi_error', $error);
   }

   public function mci_get_response_error() {
      return $this->mcapi_error;
   } 

}

?>