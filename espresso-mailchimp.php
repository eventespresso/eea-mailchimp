<?php
/*
  Plugin Name: Event Espresso - MailChimp Integration (for EE4+)
  Plugin URI: http://www.eventespresso.com/
  Description: A MailChimp integration addon for Event Espresso. Requires version of Event Espresso 4 and greater.

  Version: 2.0.0.dev.000

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


// Define our plugin version and other base stuff.
define( 'ESPRESSO_MAILCHIMP_VERION', '2.0.0.dev.000' );
define( 'ESPRESSO_MAILCHIMP_MAIN_FILE', __FILE__ );
// Register and run MC Integration if EE4 is Active.
function load_ee4_espresso_mailchimp_integration_class() {
   // ..and register our add-on.
	require_once( plugin_dir_path( __FILE__ ) . 'EE_MailChimp_Integration.class.php' );
	EE_MailChimp_Integration::register_addon();
}
add_action( 'AHEE__EE_System__load_espresso_addons', 'load_ee4_espresso_mailchimp_integration_class', 11 );


// Store the names of the tables into the $wpdb
function espresso_mailchimp_register_integration_tables() {
   global $wpdb;
   $wpdb->ee_mci_mailchimp_attendee_rel = "{$wpdb->prefix}events_mailchimp_attendee_rel";
   $wpdb->ee_mci_mailchimp_event_rel = "{$wpdb->prefix}events_mailchimp_event_rel";
   $wpdb->ee_mci_mailchimp_question_field_rel = "{$wpdb->prefix}events_mailchimp_question_field_rel";
}
add_action('init', 'espresso_mailchimp_register_integration_tables');