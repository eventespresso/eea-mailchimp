<?php
/*
  Plugin Name: Event Espresso - MailChimp Integration (for EE4+)
  Plugin URI: http://www.eventespresso.com/
  Description: A MailChimp integration addon for Event Espresso. Requires version of Event Espresso 4 and greater.

  Version: 1.0

  Usage: Configure the MailChimp API credentials under Event Espresso -> MailChimp integration. When creating/updating an event, select the Mail Chimp list you would like to integrate with.
  Author: Event Espresso
  Author URI: http://www.eventespresso.com

  Copyright (c) 2014  Event Espresso  All Rights Reserved.

     This program is free software; you can redistribute it and/or modify
     it under the terms of the GNU General Public License as published by
     the Free Software Foundation; either version 2 of the License, or
     (at your option) any later version.

     This program is distributed in the hope that it will be useful,
     but WITHOUT ANY WARRANTY; without even the implied warranty of
     MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
     GNU General Public License for more details.

     You should have received a copy of the GNU General Public License
     along with this program; if not, write to the Free Software
     Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307 USA
 */

// Define the version of the plugin
function espresso_mailchimp_version() {
   return '1.0';
}

// Define some plugin info/constants
$mci_dir_path = plugin_dir_path(__FILE__);
$mci_base_name = plugin_basename(__FILE__);
$mci_url = plugin_dir_url(__FILE__);
$mci_admin_url = get_admin_url();

define('ESPRESSO_MAILCHIMP_DIR', $mci_dir_path);
define('ESPRESSO_MAILCHIMP_URL', $mci_url);
define('ESPRESSO_MAILCHIMP_ADMIN_URL', $mci_admin_url);
define('ESPRESSO_MAILCHIMP_BASE_NAME', $mci_base_name);
define('ESPRESSO_MAILCHIMP_MAIN_FILE', __FILE__);
define('ESPRESSO_MAILCHIMP_SETTINGS_PAGE_SLUG', 'espresso_mailchimp_settings');
define('ESPRESSO_MAILCHIMP_INTEGRATION_ACTIVE_OPTION', 'ee_mailchimp_integration_active');
define('ESPRESSO_MAILCHIMP_API_OPTIONS', 'ee_mailchimp_integration_user_settings');

// Lets us to continue on
require_once( ESPRESSO_MAILCHIMP_DIR . 'includes/EE_MCI_Controller.class.php' );
require_once( ESPRESSO_MAILCHIMP_DIR . 'includes/MailChimp.class.php' );
require_once( ESPRESSO_MAILCHIMP_DIR . 'EE_MCI_Setup.class.php' );

// Store the names of the tables into the $wpdb
function espresso_mailchimp_register_integration_tables() {
   global $wpdb;
   $wpdb->ee_mci_mailchimp_attendee_rel = "{$wpdb->prefix}events_mailchimp_attendee_rel";
   $wpdb->ee_mci_mailchimp_event_rel = "{$wpdb->prefix}events_mailchimp_event_rel";
   $wpdb->ee_mci_mailchimp_question_field_rel = "{$wpdb->prefix}events_mailchimp_question_field_rel";
}
add_action('init', 'espresso_mailchimp_register_integration_tables');


//require_once(ESPRESSO_MAILCHIMP_DIR . 'includes/mcapi/vendor/autoload.php');

// Update notifications
if ( ! function_exists('ee_mailchimp_load_pue_update') ) {
   function ee_mailchimp_load_pue_update() {
      if ( file_exists(EE_THIRD_PARTY . 'pue/pue-client.php') ) { //include the file
         require(EE_THIRD_PARTY . 'pue/pue-client.php');
         require_once(EE_CORE . 'EE_Network_Config.core.php');

         $settings = EE_Network_Config::instance()->get_config();
         $api_key = $settings->core->site_license_key;
         $host_server_url = 'http://eventespresso.com';
         $plugin_slug = array(
            'premium' => array('p' => 'espresso-mailchimp'),
            'prerelease' => array('b' => 'espresso-mailchimp-pr')
         );
         $options = array(
            'apikey' => $api_key,
            'lang_domain' => 'event_espresso',
            'checkPeriod' => '24',
            'option_key' => 'site_license_key',
            'options_page_slug' => 'event_espresso',
            'plugin_basename' => ESPRESSO_MAILCHIMP_BASE_NAME,
            'use_wp_update' => FALSE, //if TRUE then you want FREE versions of the plugin to be updated from WP
         );
         do_action('AHEE__ee_mailchimp_load_pue_update__pre_update_check');
         $check_for_updates = new PluginUpdateEngineChecker($host_server_url, $plugin_slug, $options); //initiate the class and start the plugin update engine!
      }
   }

   if ( is_admin() ) {
    //Do not load update notifications for now
      //$mci_update = ee_mailchimp_load_pue_update();
   }
}

/**
 *  check if the older version of MC integration is activated (prevent multiple activations)
 */
function espresso_mailchimp_check_on_duplicate() {
   if ( class_exists('MailChimpController') ) {
      if ( ! function_exists('deactivate_plugins') )
         require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
      deactivate_plugins( ESPRESSO_MAILCHIMP_DIR . 'espresso-mailchimp.php', true );
      unset( $_GET['activate'] );
      add_action( 'admin_notices', 'ee_mailchimp_duplicate_error_msg', 1 );
   }
}
add_action('plugins_loaded', 'espresso_mailchimp_check_on_duplicate');

/**
 *  plugin activation
 */
function espresso_mailchimp_activation() {
   espresso_mailchimp_register_integration_tables();
   EE_MCI_Setup::instance(true);
   add_option(ESPRESSO_MAILCHIMP_INTEGRATION_ACTIVE_OPTION, 'false', '', 'yes');
   update_option(ESPRESSO_MAILCHIMP_INTEGRATION_ACTIVE_OPTION, 'false');
   do_action('AHEE__espresso_mailchimp_activation__post_activation');
}
register_activation_hook(ESPRESSO_MAILCHIMP_MAIN_FILE, 'espresso_mailchimp_activation');

/**
 *  plugin deactivation
 */
function espresso_mailchimp_deactivation() {
   delete_option(ESPRESSO_MAILCHIMP_API_OPTIONS);
   update_option(ESPRESSO_MAILCHIMP_INTEGRATION_ACTIVE_OPTION, 'flase');
   do_action('AHEE__mailchimp_integration__post_deactivation');
}
register_deactivation_hook(ESPRESSO_MAILCHIMP_MAIN_FILE, 'espresso_mailchimp_deactivation');

/**
 *  a regular setup call
 */
function espresso_mailchimp_setup_call() {
   $reee = new EE_MCI_Setup();
}
add_action('plugins_loaded', 'espresso_mailchimp_setup_call');

function ee_mailchimp_duplicate_error_msg() {
   ?>
   <div class="error">
      <p>
         <?php _e('Can\'t run multiple versions of EE <b>MailChimp Integration</b> Plugin. The plugin was <b>Not activated!</b> Please deactivate the other one to activate this version.', 'event_espresso'); ?>
      </p>
   </div>
   <?php
   do_action('AHEE__mailchimp_integration__ee_mailchimp_duplicate_error_msg__after');
}