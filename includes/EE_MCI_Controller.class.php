<?php
/**
* Event Espresso MailChimp logic implementing. Intermediary between this integration and the MailChimp API.
*
**/

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
      $mcapi_settings = get_option(ESPRESSO_MAILCHIMP_API_OPTIONS);
      $api_key = ( strlen($mcapi_settings['api_key']) > 1 ) ? $mcapi_settings['api_key'] : 'invalid-usX';
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
      $mcapi_settings = get_option(ESPRESSO_MAILCHIMP_API_OPTIONS);
      if ( $api_key == NULL )
         $api_key = $mcapi_settings['api_key'];
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
      global $wpdb;
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
                     $mcapi_settings = get_option(ESPRESSO_MAILCHIMP_API_OPTIONS);
                     $api_key = $mcapi_settings['api_key'];
                     $double_optin = ( isset($mcapi_settings['double_optin']) ) ? $mcapi_settings['double_optin'] : true;
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
                              $args['merge_vars']['groupings'][] = array('id' => intval($grouping[1]), 'groups' => array(base64_decode($grouping[2])));
                           }
                        } else {
                           $grouping = explode('-', $evt_groups);
                           $args['merge_vars']['groupings'][] = array('id' => intval($grouping[1]), 'groups' => array(base64_decode($grouping[2])));
                        }
                     }
                     foreach ($evt_qfields as $list_field => $event_question) {
                        if ( @isset($subscriber[$event_question]) )
                           $args['merge_vars'][$list_field] = $subscriber[$event_question];
                     }
                     $subscribe_args = apply_filters('FHEE__EE_MCI_Controller__mci_submit_to_mailchimp__subscribe_args', $args);
                     // Subscribe attendee
                     $reply = $this->MailChimp->call('lists/subscribe', $subscribe_args);
                     // If there was an error during subscription than process it.
                     // If success then add new attendee/subscriber to the DB.
                     if ( isset($reply['status']) && ($reply['status'] == 'error') ) {
                        $this->mci_throw_error($reply);
                        // If the error: 'email is already subscribed to a list' then just update the groups.
                        if ( $reply['code'] == 214 ) {
                           $reply = $this->MailChimp->call('lists/update-member', $subscribe_args);
                        }
                     } else {
                        do_action('AHEE__EE_MCI_Controller__mci_submit_to_mailchimp__add_attendee_to_db', $spc_obj, $subscriber, $reply);
                        $wpdb->insert($wpdb->ee_mci_mailchimp_attendee_rel, array('event_id' => $evt_id, 'attendee_id' => $att_id, 'mailchimp_list_id' => $evt_list), array("%d", "%s", "%s"));
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
      $mcapi_settings = get_option(ESPRESSO_MAILCHIMP_API_OPTIONS);
      $api_key = $mcapi_settings['api_key'];
      $reply = $this->MailChimp->call('lists/list', array('apikey' => $api_key));
      if ( ($reply != false) && isset($reply['data']) && ! empty($reply['data'])  ) {
         return $reply['data'];
      } else {
         return array();
      }
   }

   /**
    * Retrieve all of the lists defined for your user account.
    * 
    * @access public
    * @param string $list_id  The ID of the List.
    * @return array  List of MailChimp lists.
    */
   public function mci_get_users_groups( $list_id ) {
      do_action('AHEE__EE_MCI_Controller__mci_get_users_groups__start');
      $mcapi_settings = get_option(ESPRESSO_MAILCHIMP_API_OPTIONS);
      $api_key = $mcapi_settings['api_key'];
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
    * Retrieve all of the lists defined for your user account.
    * 
    * @access public
    * @param string $list_id  The ID of the List.
    * @return array   MailChimp List fields.
    */
   public function mci_get_list_merge_vars( $list_id ) {
      do_action('AHEE__EE_MCI_Controller__mci_get_list_merge_vars__start');
      $mcapi_settings = get_option(ESPRESSO_MAILCHIMP_API_OPTIONS);
      $api_key = $mcapi_settings['api_key'];
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
    * Set 'MailChimp List Integration' meta-box section contents.
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
               <?php
               // Lists / Groups section
               $this->mci_list_mailchimp_lists( $event->ID );
               $this->mci_list_mailchimp_groups( $event->ID );
               ?>
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
    * Set 'MailChimp List Integration' meta-box section contents.
    * 
    * @access public
    * @param int $event_id  An ID on the Event.
    * @return void
    */
   public function mci_save_metabox_contents( $event_id ) {
      global $wpdb;
      // Lists and Groups
      $list_id = $_POST['ee_mailchimp_lists'];
      if ( ! empty($_POST['ee_mailchimp_groups']) ) {
         $group_ids = serialize($_POST['ee_mailchimp_groups']);
      }
      $exists = $wpdb->get_row( "SELECT * FROM $wpdb->ee_mci_mailchimp_event_rel WHERE event_id = '$event_id'" );
      if ( $exists != null ) {
         $wpdb->update($wpdb->ee_mci_mailchimp_event_rel, array('mailchimp_list_id' => $list_id, 'mailchimp_group_id' => $group_ids), array('event_id' => $event_id));
      } else {
         $wpdb->insert($wpdb->ee_mci_mailchimp_event_rel, array('event_id' => $event_id, 'mailchimp_list_id' => $list_id, 'mailchimp_group_id' => $group_ids), array("%d", "%s", "%s"));
      }

      // Question Fields
      $qfields_list = base64_decode($_POST['ee_mailchimp_qfields']);
      if ( @unserialize($qfields_list) !== false ) {
        $qfields_list = unserialize($qfields_list);
      } else {
        $qfields_list = array();
      }
      $list_form_rel = array();
      foreach ($qfields_list as $question) {
        // Do not merge Email fields as it has to default to Email question.
        if ( ($_POST[base64_encode($question)] != '-1') && $question != 'EMAIL' )
          $list_form_rel[$question] = $_POST[base64_encode($question)];
      }
      $qf_exists = $wpdb->get_row( "SELECT * FROM $wpdb->ee_mci_mailchimp_question_field_rel WHERE event_id = '$event_id'" );
      if ( $qf_exists != null ) {
         $wpdb->update($wpdb->ee_mci_mailchimp_question_field_rel, array('field_question_rel' => serialize($list_form_rel)), array('event_id' => $event_id));
      } else {
         $wpdb->insert($wpdb->ee_mci_mailchimp_question_field_rel, array('event_id' => $event_id, 'field_question_rel' => serialize($list_form_rel)), array("%d", "%s"));
      }
   }

   /**
    * Get MailChimp user Lists.
    * 
    * @access public
    * @param string $event_id  The ID of the Event.
    * @return void
    */
   public function mci_list_mailchimp_lists( $event_id ) {
      global $wpdb;
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
    * Get MailChimp user Groups (depending on the selected list).
    * 
    * @access public
    * @param string $event_id  The ID of the Event.
    * @param string $list_id  The ID of the List.
    * @return void
    */
   public function mci_list_mailchimp_groups( $event_id, $list_id = NULL ) {
      global $wpdb;
      do_action('AHEE__EE_MCI_Controller__mci_list_mailchimp_groups__start');
      // Get saved group for this event (if there's one)
      $selected_gorup = $this->mci_event_subscriptions($event_id, 'groups');
      $mc_gorups = $this->mci_get_users_groups($list_id);
      ?>
      <br /><div id="espresso-mci-groups-list">
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
      </div>
      <?php
   }

   /**
    * Get MailChimp user list Fields (depending on the selected list).
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
              ?>
              <tr>
                <td>
                  <p id="mci-field-<?php echo base64_encode($l_field['name']); ?>" class="ee_mci_list_fields">
                    <?php echo $l_field['name']; echo ( $l_field['req'] ) ? '<span class="nci_asterisk"> *</span>' : ''; ?>
                  </p>
                </td>
                <td>
                  <select id="event-question-<?php echo base64_encode($l_field['name']); ?>" name="<?php echo base64_encode($l_field['tag']); ?>" class="ee_event_fields_selects" <?php echo ( $l_field['tag'] == 'EMAIL') ? 'disabled' : ''; ?>>
                    <option value="-1">none</option>
                    <?php foreach ($evt_questions as $q_field) { ?>
                      <option value="<?php echo $q_field['QST_ID']; ?>" 
                        <?php 
                          // Default to main fields if exist:
                          echo ( (@$selected_fields[$l_field['tag']] == $q_field['QST_ID']) 
                            || (($q_field['QST_ID'] == 'email') && ($l_field['tag'] == 'EMAIL')) 
                            || (($q_field['QST_ID'] == 'lname') && ($l_field['tag'] == 'LNAME') && ! in_array('lname', $selected_fields)) 
                            || (($q_field['QST_ID'] == 'fname') && ($l_field['tag'] == 'FNAME') && ! in_array('fname', $selected_fields)) ) ? 'selected' : ''; 
                        ?>>
                        <?php echo $q_field['QST_Name']; ?>
                      </option>
                    <?php } ?>
                  </select>
                  <?php if ( $l_field['tag'] == 'EMAIL') echo '<span class="nci_asterisk"> *</span>'; ?>
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
      global $wpdb;
      $event_list = $wpdb->prepare(
         "SELECT mailchimp_list_id AS ListID 
         FROM " . $wpdb->ee_mci_mailchimp_event_rel . " 
         WHERE event_id = %s ", $evt_id
      );
      $evt_list = $wpdb->get_results($event_list, ARRAY_A);
      $event_group = $wpdb->prepare(
         "SELECT mailchimp_group_id AS GroupID 
         FROM " . $wpdb->ee_mci_mailchimp_event_rel . " 
         WHERE event_id = %s ", $evt_id
      );
      $evt_groups = $wpdb->get_results($event_group, ARRAY_A);
      $event_qfields = $wpdb->prepare(
         "SELECT field_question_rel AS FieldRel 
         FROM " . $wpdb->ee_mci_mailchimp_question_field_rel . " 
         WHERE event_id = %s ", $evt_id
      );
      $evt_qfields = $wpdb->get_results($event_qfields, ARRAY_A);
      $evt_subs = array();
      switch ( $target ) {
         case 'list':
            if ( ! empty($evt_list) ) {
               $evt_subs = $evt_list[0]['ListID'];
             } else {
              $evt_subs = -1;
             }
            break;
         case 'groups':
            if ( ! empty($evt_groups) && @unserialize($evt_groups[0]['GroupID']) !== false )
               $evt_subs = unserialize($evt_groups[0]['GroupID']);
            break;
         case 'question_fields':
            if ( ! empty($evt_qfields) && @unserialize($evt_qfields[0]['FieldRel']) !== false )
               $evt_subs = unserialize($evt_qfields[0]['FieldRel']);
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