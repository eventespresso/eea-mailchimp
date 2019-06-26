<?php
/**
 * Contains test class for EE_MCI_Controller.class.php
 *
 * @since       2.1.0
 * @package     EE4 MailCHimp
 * @subpackage  Tests
 */


/**
 * Test class for EE_MCI_Controller
 */
class EE_MCI_Controller_Tests extends EE_UnitTestCase {

	/**
	 * @var EE_MCI_Controller $_mci_controller
	 */
	private $_mci_controller;

	/**
	 * @var EE_Mailchimp_Config $_mci_config
	 */
	private $_mci_config;

	/**
	 * @var EE_Event $_ee_event
	 */
	private $_ee_event;

	/**
	 * @var string $_list_id
	 */
	private $_list_id;

	/**
	 * @var string $_sandbox_key
	 */
	private $_sandbox_key;



    public function setUp() {
        parent::setUp();

        $this->_list_id = 'a1913d44fe';

        // MailChimp Settings.
        $this->_mci_config = EED_Mailchimp::get_config();

        // MailChimp sandbox key.
        // This needs to be set as an environment variable.
        $this->_sandbox_key = getenv('EEA_MAILCHIMP_SANDBOX_API_KEY');
        if( ! $this->_sandbox_key && defined( 'EEA_MAILCHIMP_SANDBOX_API_KEY')) {
            $this->_sandbox_key = EEA_MAILCHIMP_SANDBOX_API_KEY;
        }
        if (! $this->_sandbox_key) {
            $this->markTestSkipped('Unable to complete test because the MailChimp API Key was not set (env variable)');
        }

        // Set a valid MailChimp API Key.
        $this->_mci_config->api_settings->api_key = $this->_sandbox_key;
	    EED_Mailchimp::update_config( $this->_mci_config );

        // MailChimp Controller.
        $this->_mci_controller = new EE_MCI_Controller();

        // New Event.
        $this->_ee_event = $this->factory->event->create();

        // Add the MCI error filter.
        add_filter('FHEE__EE_MCI_Controller__mci_throw_error__mcapi_error', array('EE_MCI_Controller_Tests', 'notatest_apply_mci_error_filter'));
    }



	/**
	 * @param $error
	 * @return mixed
	 */
	public static function notatest_apply_mci_error_filter( $error ) {
        $error['code'] = '404';
        return $error;
    }

    /**
     * Test the instance of mci_controller.
     */
    public function test_mci_controller_instance() {
        $this->assertTrue( $this->_mci_controller instanceof EE_MCI_Controller );
    }

    /**
     * Test the instance of mci_config.
     */
    public function test_mci_config_instance() {
        $this->assertTrue( $this->_mci_config instanceof EE_Mailchimp_Config );
    }

    /**
     * Test MailChimp API Key validation.
     *
     * @depends test_mci_controller_instance
     * @dataProvider mc_test_keys_provider
     *
     * @param string $key MC Api test Key
     * @param bool $result is key valid
     * @param array $call_reply MC Api call response
     */
    public function test_mc_api_key_validation($key, $result, $call_reply = array() ) {
        $key_valid = $this->_mci_controller->mci_is_api_key_valid($key);
        $this->assertEquals( $result, $key_valid );

        // Test how MC Controller will handle an error and what will we get in the $mcapi_error.
        if ( $result === false ) {
            $mci_error = $this->_mci_controller->mci_get_response_error();
            // Was the code changed through the filter?
            $this->assertEquals( $mci_error['code'], $call_reply['code'] );
            $this->assertEquals( $mci_error['msg'], $call_reply['msg'] );
        }
    }

    /**
     * Data provider. MC API Test Keys.
     */
    public function mc_test_keys_provider() {
        return array(
            // Key accepted:
            array($this->_sandbox_key, $this->_sandbox_key),
            // Not acceptable keys:
            array(substr($this->_sandbox_key, 0, -1), false, array(
                'code' => '404',
                'msg' => 'Invalid MailChimp API Key.')
            ),
            array(substr_replace($this->_sandbox_key, '-', 20, 0), false, array(
                'code' => '404',
                'msg' => 'Invalid MailChimp API Key.')
            ),
            array(str_replace('-', '', $this->_sandbox_key), false, array(
                'code' => '404',
                'msg' => 'Invalid MailChimp API Key.')
            ),
            // Dummy key:
            array('12345abcde1234abcde1234abcde12-us1', false, array(
                'code' => '404',
                'msg' => 'API Key Invalid')
            )
        );
    }

    /**
     * Test return value of mci_get_users_lists.
     */
    public function test_what_mci_get_users_lists_returns() {
        $this->assertTrue( is_array($this->_mci_controller->mci_get_users_lists()) );
    }

    /**
     * Test return value of mci_get_users_groups.
     */
    public function test_what_mci_get_users_groups_returns() {
        // Should return an array of values.
        $ok_value = $this->_mci_controller->mci_get_users_groups($this->_list_id);
        $this->assertTrue( is_array($ok_value) );
        $this->assertTrue( ! empty($ok_value) );
        // Should return an empty array.
        $bad_value = $this->_mci_controller->mci_get_users_groups('wrong-' . $this->_list_id);
        $this->assertTrue( is_array($bad_value) );
        $this->assertTrue( empty($bad_value) );
    }

    /**
     * Test return value of mci_get_list_merge_vars.
     */
    public function test_what_mci_get_list_merge_vars_returns() {
        // Should return an array of values.
        $ok_value = $this->_mci_controller->mci_get_list_merge_vars($this->_list_id);
        $this->assertTrue( is_array($ok_value) );
        $this->assertTrue( ! empty($ok_value) );
        // Should return an empty array.
        $bad_value = $this->_mci_controller->mci_get_list_merge_vars('wrong-' . $this->_list_id);
        $this->assertTrue( is_array($bad_value) );
        $this->assertTrue( empty($bad_value) );
    }

    /**
     * Test return value of mci_get_event_question_groups.
     */
    public function test_mci_getting_event_question_groups() {
        $event_qg = $this->_mci_controller->mci_get_event_question_groups($this->_ee_event->ID());
        $this->assertTrue( is_array($event_qg) );
        //$this->assertTrue( ! empty($event_qg) );
    }

    /**
     * Test return value of mci_get_event_all_questions.
     */
    public function test_mci_getting_event_questions() {
        $event_questions = $this->_mci_controller->mci_get_event_all_questions($this->_ee_event->ID());
        $this->assertTrue( is_array($event_questions) );
        //$this->assertTrue( ! empty($event_questions) );
    }

    /**
     * Test return value of mci_event_subscriptions.
     */
    public function test_mci_getting_event_subscriptions() {
        // These will be empty as no lists/groups were added.
        $mci_event_subscriptions = $this->_mci_controller->mci_event_subscriptions($this->_ee_event->ID() );
        $this->assertNull( $mci_event_subscriptions['list'] );
        $this->assertTrue( is_array( $mci_event_subscriptions['groups']) );

        // Add a list and a group to the event.
        $group_id = '907bfaf16d-94127ddbe7-QWxs-true';
        $new_list_group = EE_Event_Mailchimp_List_Group::new_instance(
            array(
                'EVT_ID' => $this->_ee_event->ID(),
                'AMC_mailchimp_list_id' => $this->_list_id,
                'AMC_mailchimp_group_id' => $group_id
            )
        );
        $new_list_group->save();
	    $mci_event_subscriptions = $this->_mci_controller->mci_event_subscriptions( $this->_ee_event->ID() );
	    $this->assertTrue( isset( $mci_event_subscriptions['list']) );
	    $this->assertTrue( $mci_event_subscriptions['list'] === $this->_list_id );
        $this->assertTrue( is_array( $mci_event_subscriptions['groups']) );
        $this->assertTrue( in_array($group_id, $mci_event_subscriptions['groups']) );

        // Test for event questions.
        $this->assertTrue( is_array( $mci_event_subscriptions['qfields']) );
        //$this->assertTrue( ! empty($event_questions) );

        // Test with no target (second) variable.
        $event_lgq = $this->_mci_controller->mci_event_subscriptions($this->_ee_event->ID());
        $this->assertTrue( is_array($event_lgq) );
        $this->assertTrue( array_key_exists('list', $event_lgq) );
        $this->assertTrue( array_key_exists('groups', $event_lgq) );
        $this->assertTrue( array_key_exists('qfields', $event_lgq) );
        $this->assertTrue( $event_lgq['list'] === $this->_list_id );
        $this->assertTrue( in_array($group_id, $event_lgq['groups']) );
        $this->assertTrue( is_array($event_lgq['qfields']) );
    }

    /**
     * Test how will mci save metabox contents.
     */
    public function test_mci_saving_metabox_contents() {
        $list_groups = array('907bfaf16d-94127ddbe7-QWxs', 'bceb085d63-94127ddbe7-Tm90IGFsbA==');
        $all_interests = array('907bfaf16d-94127ddbe7-QWxs', 'bceb085d63-94127ddbe7-Tm90IGFsbA==', 'b28a6191c0-b63bc1efcd-RzI=', '71b296c9dc-d41c360a53-RGcy');
        $saved_interests = array('907bfaf16d-94127ddbe7-QWxs-true', 'bceb085d63-94127ddbe7-Tm90IGFsbA==-true', 'b28a6191c0-b63bc1efcd-RzI=-false', '71b296c9dc-d41c360a53-RGcy-false');
        $_POST['ee_mailchimp_lists'] = $this->_list_id;
        $_POST['ee_mailchimp_groups'] = $list_groups;
        $_POST['ee_mc_list_all_interests'] = $all_interests;
        // Set test data for question fields.
        $list_fields = $this->_mci_controller->mci_get_list_merge_vars( $this->_list_id );
        $list_field_tags = array();
        foreach ($list_fields as $element) {
            $list_field_tags[] = $element['tag'];
            $_POST[base64_encode($element['tag'])] = $element['tag'];
        }
        $_POST['ee_mailchimp_qfields'] = $list_field_tags;

        $this->_mci_controller->mci_save_metabox_contents($this->_ee_event->ID());
	    $mci_event_subscriptions = $this->_mci_controller->mci_event_subscriptions( $this->_ee_event->ID() );
	    $list = $mci_event_subscriptions['list'];
        $groups = $mci_event_subscriptions['groups'];
        $q_fields = $mci_event_subscriptions['qfields'];
	    $this->assertTrue( $list === $this->_list_id );
        foreach ($saved_interests as $group) {
            $this->assertTrue( in_array($group, $groups) );
        }
        foreach ($list_fields as $q_element) {
            $this->assertTrue( array_key_exists($q_element['tag'], $q_fields) );
        }
    }

    /**
     * Test what is displayed when requesting a list of MC lists.
     */
    public function test_mailchimp_lists_content() {
        $mc_lists = $this->_mci_controller->mci_get_users_lists();
        $lists_content = $this->_mci_controller->mci_list_mailchimp_lists($this->_ee_event->ID());
        foreach ($mc_lists as $list_no => $list) {
            $this->assertTrue( strpos($lists_content, $list['name']) !== false );
        }
    }

    /**
     * Test what is displayed when requesting a list of MC groups.
     */
    public function test_mailchimp_groups_content() {
        $mc_gorups = $this->_mci_controller->mci_get_users_groups($this->_list_id);
        $ok_groups_content = $this->_mci_controller->mci_list_mailchimp_groups($this->_ee_event->ID(), $this->_list_id);
        foreach ($mc_gorups as $grouping) {
            $this->assertTrue( strpos($ok_groups_content, $grouping['title']) !== false );
            $category_interests = $this->_mci_controller->mci_get_interests( $this->_list_id, $grouping['id'] );
            foreach ($category_interests as $interest) {
                $this->assertTrue( strpos($ok_groups_content, base64_encode($interest['name'])) !== false );
            }
        }
        // If no groups in the list.
        $none_groups_content = $this->_mci_controller->mci_list_mailchimp_groups($this->_ee_event->ID(), 'invalid-list-id');
        $this->assertTrue( strpos($none_groups_content, 'No groups found for this List.') !== false );
    }

    /**
     * Test what is displayed when requesting a list of MC question fields.
     */
    public function test_mailchimp_fields_content() {
        // Are we updating the post or creating a new one?
        $_GET['action'] = 'update';
        $list_fields = $this->_mci_controller->mci_get_list_merge_vars($this->_list_id);
        $ok_questions_content = $this->_mci_controller->mci_list_mailchimp_fields($this->_ee_event->ID(), $this->_list_id);
        foreach ($list_fields as $element) {
            $this->assertTrue( strpos($ok_questions_content, "value='".$element['tag']."'") !== false );
        }

        foreach ($list_fields as $l_field) {
            $this->assertTrue( strpos($ok_questions_content, base64_encode($l_field['name'])) !== false );
        }
        // If no question fields in the list.
        $none_questions_content = $this->_mci_controller->mci_list_mailchimp_fields($this->_ee_event->ID(), 'invalid-list-id');
        $this->assertTrue( strpos($none_questions_content, 'Sorry, no merge fields found!') !== false );
    }

    /**
     * Test mci_set_metabox_contents.
     */
    public function test_mci_metabox_contents() {
        $ok_metabox_contents = $this->_mci_controller->mci_set_metabox_contents(get_post($this->_ee_event->ID(), OBJECT));
        $this->assertTrue( strpos($ok_metabox_contents, 'espresso_mailchimp_integration_metabox') !== false );

        // Invalid Key so no contents in the metabox.
        $this->_mci_config->api_settings->api_key = 'b40528421d083eff83b9b6ba11sdsdd8f928-us8';
	    EED_Mailchimp::update_config( $this->_mci_config );
        $metabox_contents = $this->_mci_controller->mci_set_metabox_contents(get_post($this->_ee_event->ID(), OBJECT));
	    $this->assertFalse( empty($metabox_contents) );
    }
}

// Location:/tests/testcases/includes/EE_MCI_Controller_Tests.php