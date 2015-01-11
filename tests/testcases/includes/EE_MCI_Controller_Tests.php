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

    private $_mci_controller;

    private $_mci_config;

    private $_ee_event;

    private $_list_id;

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

        // Add the MCI error filter.
        add_filter('FHEE__EE_MCI_Controller__mci_throw_error__mcapi_error', array('EE_MCI_Controller_Tests', 'notatest_apply_mci_error_filter'));
    }

    public static function notatest_apply_mci_error_filter( $error ) {
        $error['code'] = '7000';
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
     */
    public function test_mc_api_key_validation($key, $result) {
        $key_valid = $this->_mci_controller->mci_is_api_key_valid($key);
        $this->assertEquals( $key_valid, $result );

        // Test how MC Controller will handle an error and what will we get in the $mcapi_error.
        if ( $result === false ) {
            $mci_error = $this->_mci_controller->mci_get_response_error();
            // Was the code changed throught the filter?
            $this->assertEquals( $mci_error['code'], '7000' );
            $this->assertEquals( $mci_error['msg'], 'Unknown MailChimp API Error' );
        }
    }

    /**
     * Data provider. MC API Test Keys.
     */
    public function mc_test_keys_provider() {
        return array(
            array('b40528421d083eff83b9b6ba11d8f928-us8', true),
            array('b40528421d083eff83b9b6ba11d8f928-us55', false),
            array('b40528421d083eff83-b9b6ba11d8f928u-s8', false),
            array('b40528421d083eff83b9b6ba11d8f928us8', false),
            array('b40528421d083eff83b9b6ba11d8f928us8-', false),
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
        $no_event_list = $this->_mci_controller->mci_event_subscriptions($this->_ee_event->ID(), 'list');
        $no_event_groups = $this->_mci_controller->mci_event_subscriptions($this->_ee_event->ID(), 'groups');
        $this->assertTrue( $no_event_list == -1 );
        $this->assertTrue( is_array($no_event_groups) );

        // Add a list and a group to the event.
        $group_id = '16-545-TWFueQ==';
        $new_list_group = EE_Event_Mailchimp_List_Group::new_instance(
            array(
                'EVT_ID' => $this->_ee_event->ID(),
                'AMC_mailchimp_list_id' => $this->_list_id,
                'AMC_mailchimp_group_id' => $group_id
            )
        );
        $new_list_group->save();
        $event_list = $this->_mci_controller->mci_event_subscriptions($this->_ee_event->ID(), 'list');
        $event_groups = $this->_mci_controller->mci_event_subscriptions($this->_ee_event->ID(), 'groups');
        $this->assertTrue( isset($event_list) );
        $this->assertTrue( $event_list === $this->_list_id );
        $this->assertTrue( is_array($event_groups) );
        $this->assertTrue( in_array($group_id, $event_groups) );

        // Test for event questions.
        $event_questions = $this->_mci_controller->mci_event_subscriptions($this->_ee_event->ID(), 'question_fields');
        $this->assertTrue( is_array($event_questions) );
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
        $list_groups = array('16-545-TWFueQ==', '64-545-Tm9uZQ==');
        $_POST['ee_mailchimp_lists'] = $this->_list_id;
        $_POST['ee_mailchimp_groups'] = $list_groups;
        // Set test data for question fields.
        $list_fields = $this->_mci_controller->mci_get_list_merge_vars( $this->_list_id );
        $list_field_tags = array();
        foreach ($list_fields as $element) {
            $list_field_tags[] = $element['tag'];
            $_POST[base64_encode($element['tag'])] = $element['tag'];
        }
        $_POST['ee_mailchimp_qfields'] = base64_encode(serialize($list_field_tags));

        $this->_mci_controller->mci_save_metabox_contents($this->_ee_event->ID());
        $list = $this->_mci_controller->mci_event_subscriptions($this->_ee_event->ID(), 'list');
        $groups = $this->_mci_controller->mci_event_subscriptions($this->_ee_event->ID(), 'groups');
        $q_fields = $this->_mci_controller->mci_event_subscriptions($this->_ee_event->ID(), 'question_fields');

        $this->assertTrue( $list === $this->_list_id );
        foreach ($list_groups as $group) {
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
        ob_start();
            $this->_mci_controller->mci_list_mailchimp_lists($this->_ee_event->ID());
            $lists_content = ob_get_contents();
        ob_end_clean();
        foreach ($mc_lists as $list_no => $list) {
            $this->assertTrue( strpos($lists_content, $list['name']) !== false );
        }
    }

    /**
     * Test what is displayed when requesting a list of MC groups.
     */
    public function test_mailchimp_groups_content() {
        $mc_gorups = $this->_mci_controller->mci_get_users_groups($this->_list_id);
        ob_start();
            $this->_mci_controller->mci_list_mailchimp_groups($this->_ee_event->ID(), $this->_list_id);
            $ok_groups_content = ob_get_contents();
        ob_end_clean();
        foreach ($mc_gorups as $grouping) {
            $this->assertTrue( strpos($ok_groups_content, $grouping['name']) !== false );
            foreach ($grouping['groups'] as $group) {
                $this->assertTrue( strpos($ok_groups_content, base64_encode($group['name'])) !== false );
            }
        }
        // If no groups in the list.
        ob_start();
            $this->_mci_controller->mci_list_mailchimp_groups($this->_ee_event->ID(), 'invalid-list-id');
            $none_groups_content = ob_get_contents();
        ob_end_clean();
        $this->assertTrue( strpos($none_groups_content, 'No groups found for this List.') !== false );
    }

    /**
     * Test what is displayed when requesting a list of MC question fields.
     */
    public function test_mailchimp_fields_content() {
        $list_fields = $this->_mci_controller->mci_get_list_merge_vars($this->_list_id);
        $list_field_tags = array();
        foreach ($list_fields as $element) {
            $list_field_tags[] = $element['tag'];
        }
        ob_start();
            $this->_mci_controller->mci_list_mailchimp_fields($this->_ee_event->ID(), $this->_list_id);
            $ok_questions_content = ob_get_contents();
        ob_end_clean();
        foreach ($list_fields as $l_field) {
            $this->assertTrue( strpos($ok_questions_content, base64_encode($l_field['name'])) !== false );
        }
        $this->assertTrue( strpos($ok_questions_content, base64_encode(serialize($list_field_tags))) !== false );
        // If no question fields in the list.
        ob_start();
            $this->_mci_controller->mci_list_mailchimp_fields($this->_ee_event->ID(), 'invalid-list-id');
            $none_questions_content = ob_get_contents();
        ob_end_clean();
        $this->assertTrue( empty($none_questions_content) );
    }

    /**
     * Test mci_set_metabox_contents.
     */
    public function test_mci_metabox_contents() {
        ob_start();
            $this->_mci_controller->mci_set_metabox_contents(get_post($this->_ee_event->ID(), 'OBJECT '));
            $ok_metabox_contents = ob_get_contents();
        ob_end_clean();
        $this->assertTrue( strpos($ok_metabox_contents, 'espresso_mailchimp_integration_metabox') !== false );

        // Invalid Key so no contents in the metabox.
        $this->_mci_config->api_settings->api_key = 'b40528421d083eff83b9b6ba11sdsdd8f928-us8';
        EE_Config::instance()->update_config( 'addons', 'EE_Mailchimp', $this->_mci_config );
        ob_start();
            $this->_mci_controller->mci_set_metabox_contents(get_post($this->_ee_event->ID(), 'OBJECT '));
            $metabox_contents = ob_get_contents();
        ob_end_clean();
        $this->assertTrue( empty($metabox_contents) );
    }
}