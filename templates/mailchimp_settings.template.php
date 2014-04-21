<div class="wrap">
   <div class="meta-box-sortables ui-sortable">
      <ul id="espresso-mailchimp-sortables">
         <li>
            <div class='metabox-holder ee_mailchimp_about_instruct'>
               <div class="postbox">
                  <div title="Click to toggle" class="handlediv"><br />
                  </div>
                  <h3 class="hndle"><?php _e("About / Instructions", 'event_espresso')?></h3>
                  <div class='inside'>
                     <div class="padding">
                        <p>
                           <?php _e("With the Event Espresso MailChimp Add-On we make it easy to quickly add subscribers from any of your events. Once a visitor submits their information on your registration form, the attendee is instantly added to the MailChimp mailing list that you have configured for that event.", 'event_espresso'); ?>
                        </p>
                        <p>
                           <?php _e("* A <b>MailChimp API Key</b> is required for this plugin.", 'event_espresso'); ?>
                        </p>
                        <p>
                           <?php _e("Once the API key is configured successfully, a <b>'MailChimp List Integration' box</b> will appear in the right menu of the Event Creation and Event Update dialogs, which will allow you to select which MailChimp List and Group you want to integrate with.", 'event_espresso'); ?>
                        </p>
                     </div>
                  </div>
               </div>
            </div>
         </li>
         <li>
            <div class='metabox-holder ee_mailchimp_api_key_setup'>
               <div class="postbox">
                  <div title="Click to toggle" class="handlediv"><br /></div>
                  <h3 class="hndle"><?php _e("API Settings", 'event_espresso')?></h3>
                  <div class='inside'>
                     <form method='post'>
                        <div class="mailchimp_api_keybox">
                           <p>
                              <b>Your MailChimp API Key:</b>
                           </p>
                           <p>
                              <input size="45" type="text" id="ee-mailchimp-api-key" name="mailchimp_api_key" value="<?php echo $mailchimp_api_key; ?>" />
                              <img class="ee_mailchimp_apikey_ok" src="<?php echo ESPRESSO_MAILCHIMP_URL; ?>/assets/img/check.png" style="display: <?php echo ( strlen($mc_api_key_ok) > 0 ) ? $mc_api_key_ok : 'none'; ?>;" />
                              <img class="ee_mailchimp_apikey_error" src="<?php echo ESPRESSO_MAILCHIMP_URL; ?>/assets/img/error.png" style="display: <?php echo ( strlen($mc_api_key_error) > 0 ) ? $mc_api_key_error : 'none'; ?>;" />
                              <?php echo $mailchimp_key_error; ?>
                           </p>
                        </div>
                        <div class="padding">
                           <p class="ee_mailchimp_tips">
                              <?php _e("* If you do not have a MailChimp API key, please <a href='http://kb.mailchimp.com/article/where-can-i-find-my-api-key/' target='_blank'>click here</a> to learn how to create one.", 'event_espresso'); ?>
                           </p>
                        </div>
                        <div class="ee_mailchimp_api_options">
                           <p>
                              <b>MailChimp API Options:</b>
                           </p>
                           <div class="ee_mailchimp_double_opt">
                              <input type="checkbox" id="ee-mailchimp-double-opt" name="mailchimp_double_opt" <?php echo $mailchimp_double_opt_check; ?> />
                              <label for="ee-mailchimp-double-opt">Skip double opt-in emails.</label>
                              <p class="ee_mailchimp_tips">
                                 <?php _e("* You can read more about How does double opt-in work, <a href='http://kb.mailchimp.com/article/how-does-confirmed-optin-or-double-optin-work/' target='_blank'>here</a>.", 'event_espresso'); ?>
                              </p>
                           </div>
                        </div>
                        <div>
                           <input type='submit' class='button-primary' value='Save Settings' name='save_key_button' />
                        </div>
                     </form>
                  </div>
               </div>
            </div>
         </li>
      </ul>
   </div>
</div>