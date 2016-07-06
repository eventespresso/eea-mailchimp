<?php
/**
 * Contains test class for EE_Mailchimp_Config.php
 *
 * @since       2.1.0
 * @package     EE4 MailCHimp
 * @subpackage  Tests
 */


/**
 * Test class for EE_Mailchimp_Config
 */
class EE_Mailchimp_Config_Tests extends EE_UnitTestCase {

    /**
     * Test MailChimp Config and props.
     */
    function test_loading_mailchimp_config() {
        $mc_config = new EE_Mailchimp_Config();
        $this->assertTrue( $mc_config->api_settings instanceof EE_Mailchimp_Config_Api_Settings );
        $array_comfig = $mc_config->to_flat_array();
        $this->assertTrue( is_array($array_comfig) );
        $this->assertEquals( $array_comfig['api_settings_api_key'], '' );
        $this->assertEquals( $array_comfig['api_settings_skip_double_optin'], true );
        $this->assertEquals( $array_comfig['api_settings_mc_active'], false );
    }
}