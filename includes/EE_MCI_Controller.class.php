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
         foreach ($valid_data as $atnd) {
            if ( ! empty($atnd) && isset($atnd['fname']) ) {
               $subscriber = $atnd;
               $attendee = EE_Registry::instance()->LIB->EEM_Attendee->find_existing_attendee( array(
                  'ATT_fname' => $atnd['fname'],
                  'ATT_lname' => $atnd['lname'],
                  'ATT_email' => $atnd['email']
                  ));
            }
         }
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
            // If no list selected for this event than skip the subscription.
            if ( $evt_list != -1 ) {
               $mcapi_settings = get_option(ESPRESSO_MAILCHIMP_API_OPTIONS);
               $api_key = $mcapi_settings['api_key'];
               $args = array('apikey' => $api_key, 'id' => $evt_list, 'email' => array('email' => $subscriber['email']));
               if ( $evt_groups != -1 ) {
                  if ( (@unserialize($evt_groups) !== false) && is_array(@unserialize($evt_groups)) ) {
                     $groupings = unserialize($evt_groups);
                     foreach ($groupings as $grgs) {
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
               $args['merge_vars']['LNAME'] = $subscriber['lname'];
               $args['merge_vars']['FNAME'] = $subscriber['fname'];
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
            <div class="espresso_mci_list_fields">
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
      $evt_grg = $this->mci_event_subscriptions($event_id, 'groups');
      if ( @unserialize($evt_grg) !== false ) {
         $selected_gorup = unserialize($evt_grg);
      } else {
         $selected_gorup = array();
      }
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
   public function mci_list_mailchimp_fields( $event_id ) {

   }

   /**
    * Get MailChimp list or groups set for given event.
    * 
    * @access public
    * @param string $evt_id  The ID of the Event.
    * @param string $target (pass: 'groups' or 'list')  What to get, groups or list. If target = null then both are returned.
    * @return array/string  Id of a group/list or both in an array.
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
      $evt_subs = -1;
      switch ( $target ) {
         case 'list':
            if ( ! empty($evt_list) )
               $evt_subs = $evt_list[0]['ListID'];
            break;
         case 'groups':
            if ( ! empty($evt_groups) ) {
               $evt_subs = $evt_groups[0]['GroupID'];
            } else {
               $evt_subs = array();
            }
            break;
         case NULL:
            if ( ! empty($evt_groups) && ! empty($evt_list) )
               $evt_subs = array('list' => $evt_list[0]['ListID'], 'groups' => $evt_groups[0]['GroupID']);
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