<?php

/*
  Plugin Name: Event Espresso - MailChimp (EE4.4.5+)
  Plugin URI: http://www.eventespresso.com/
  Description: A MailChimp addon for Event Espresso.

  Version: 2.4.7.rc.000

  Usage: Configure the MailChimp API credentials under Event Espresso -> MailChimp. When creating/updating an event, select the Mail Chimp list you would like to integrate with.
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
define('ESPRESSO_MAILCHIMP_VERSION', '2.4.7.rc.000');
define('ESPRESSO_MAILCHIMP_MAIN_FILE', __FILE__);
define('ESPRESSO_MAILCHIMP_DIR', plugin_dir_path(__FILE__));
define('ESPRESSO_MAILCHIMP_BASE_NAME', plugin_basename(__FILE__));
// Register and run MC Integration if EE4 is Active.
function load_ee4_espresso_mailchimp_class()
{
    // Check the PHP version. MC SDK requires PHP 7.2 and up.
    if (version_compare(PHP_VERSION, '7.2', '>=')) {
        if (class_exists('EE_Addon')) {
            // ...and register our add-on.
            require_once(plugin_dir_path(__FILE__) . 'EE_MailChimp.class.php');
            EE_MailChimp::register_addon();
        }
    } else {
        // Incompatible PHP version, disable the plugin.
        add_action('admin_init', 'eeaDisableMailChimp');
    }
}
add_action('AHEE__EE_System__load_espresso_addons', 'load_ee4_espresso_mailchimp_class', 11);


// Disable MailChimp.
function eeaDisableMailChimp()
{
    unset($_GET['activate'], $_REQUEST['activate']);
    if (! function_exists('deactivate_plugins')) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }
    deactivate_plugins(plugin_basename(ESPRESSO_MAILCHIMP_MAIN_FILE));
    add_action('admin_notices', 'eeaMailChimpDisableNotice');
}

// Show deactivation notice.
function eeaMailChimpDisableNotice()
{
    echo '<div class="error"><p>'
         . sprintf(
             esc_html__(
                 '%1$s Event Espresso - MailChimp %2$s was deactivated! This plugin requires a %1$sPHP version 7.2 or higher%2$s.',
                 'event_espresso'
             ),
             '<strong>',
             '</strong>'
         )
         . '</p></div>';
}
