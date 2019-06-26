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
        $this->_mci_config = EED_Mailchimp::get_config();

        // Set a valid MailChimp API Key.
        $this->_mci_config->api_settings->api_key = getenv('EEA_MAILCHIMP_SANDBOX_API_KEY');

	    EED_Mailchimp::update_config( $this->_mci_config );

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
        // Use one hook or the other. Should not be both at the same time.
	    $this->assertTrue( (
                has_action(
    			    'AHEE__EE_Single_Page_Checkout__process_attendee_information__end',
    			    array( 'EED_Mailchimp', 'espresso_mailchimp_submit_to_mc' )
    		    ) !== false
                && has_action(
                    'AHEE__EE_SPCO_Reg_Step_Finalize_Registration__process_reg_step__completed',
                    array( 'EED_Mailchimp', 'espresso_mailchimp_submit_to_mc' )
                ) === false
            )
            || (
                has_action(
                    'AHEE__EE_SPCO_Reg_Step_Finalize_Registration__process_reg_step__completed',
                    array( 'EED_Mailchimp', 'espresso_mailchimp_submit_to_mc' )
                ) !== false
                && has_action(
                    'AHEE__EE_Single_Page_Checkout__process_attendee_information__end',
                    array( 'EED_Mailchimp', 'espresso_mailchimp_submit_to_mc' )
                ) === false
            )
	    );
    }
}