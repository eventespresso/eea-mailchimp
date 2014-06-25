<?php if ( ! defined( 'EVENT_ESPRESSO_VERSION' )) { exit('NO direct script access allowed'); }
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
 * Class  EES_MailChimp
 *
 * @package         Event Espresso
 * @subpackage      ee4-mailchimp
 *
 * ------------------------------------------------------------------------
 */

class EES_MailChimp extends EES_Shortcode {

    /**
     * For hooking into EE Core, modules, etc.
     *
     * @access public
     * @return void
     */
    public static function set_hooks() {}

    /**
     * For hooking into EE Admin Core, modules, etc.
     *
     * @access public
     * @return void
     */
    public static function set_hooks_admin() {}

    /**
     * Set definitions.
     *
     * @access public
     * @return void
     */
    public static function set_definitions() {}

    /**
     * Initial shortcode module setup called during "wp_loaded" hook.
     * This method is primarily used for loading resources that will be required by the shortcode when it is actually processed.
     *
     * @access public
     * @param WP  $WP
     * @return void
     */
    public function run( WP $WP ) {
        // This will trigger the EED_Espresso_MailChimp's run() method during the pre_get_posts hook point.
        // This allows us to initialize things, enqueue assets, etc.
        // As well, this saves an instantiation of the module in an array, using 'mailchimp' as the key, so that we can retrieve it.
        EE_Registry::instance()->REQ->set( 'ee', 'mailchimp' );
    }

    /**
     * process_shortcode
     *
     * @access public
     * @param array  $attributes
     */
    public function process_shortcode( $attributes = array() ) {}
    
}