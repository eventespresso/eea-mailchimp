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
 * Class  EEW_MailChimp_Integration
 *
 * @package         Event Espresso
 * @subpackage      ee4-mailchimp-integration
 *
 * ------------------------------------------------------------------------
 */

class EEW_MailChimp_Integration extends WP_Widget {

    /**
     * Register widget with WordPress.
     */
    public function __construct() {
        parent::__construct(
            'ee-mailchimp-widget',
            __( 'EE4 MailChimp Widget', 'event_espresso' ),
            array( 'description' => __( '* Not available yet. * Some MailChimp data in a widget.', 'event_espresso' ) ),
            array( 'id_base' => 'ee-mailchimp-widget' )
        );
    }

    /**
     * Back-end widget form.
     *
     * @see WP_Widget::form()
     * @param array $instance  Previously saved values from database.
     */
    public function form( $instance ) {
        return;
    }

    /**
     * Sanitize widget form values as they are saved.
     *
     * @see WP_Widget::update()
     * @param array $new_instance Values just sent to be saved.
     * @param array $instance Previously saved values from database.
     * @return array Updated safe values to be saved.
     */
    public function update( $new_instance, $instance ) {
        return;
    }

    /**
     * Front-end display of widget.
     *
     * @see WP_Widget::widget()
     * @param array $args     Widget arguments.
     * @param array $instance Saved values from database.
     */
    public function widget( $args, $instance ) {
        return;
    }

}