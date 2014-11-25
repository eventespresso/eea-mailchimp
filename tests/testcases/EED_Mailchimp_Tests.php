<?php
/**
 * Contains test class for EED_Mailchimp.module.php
 *
 * @since       2.1.0
 * @package     EE4 MailCHimp
 * @subpackage  Tests
 */


/**
 * Test class for EED_Mailchimp
 */
class EED_Mailchimp_Tests extends EE_UnitTestCase {

    public function setUp() {
        parent::setUp();

        $this->_list_id = 'a1913d44fe';

        // MailChimp Settings.
        $this->_mci_config = EE_Config::instance()->get_config( 'addons', 'EE_Mailchimp', 'EE_Mailchimp_Config' );

        // Set a valid MailChimp API Key.
        $this->_mci_config->api_settings->api_key = 'b40528421d083eff83b9b6ba11d8f928-us8';
        EE_Config::instance()->update_config( 'addons', 'EE_Mailchimp', $this->_mci_config );

        // MailChimp Controller.
        $this->_mci_controller = new EE_MCI_Controller();

        // New Event.
        $this->_ee_event = $this->factory->event->create();
    }

    /**
     * Test the frontend hook.
     */
    function test_mailchimp_front_hooks_added() {
        // Hook into the EE _process_attendee_information.
        $this->assertTrue( has_action('AHEE__EE_Single_Page_Checkout__process_attendee_information__end', array('EED_Mailchimp', 'espresso_mailchimp_submit_to_mc'), 10, 2) !== false );
    }
}