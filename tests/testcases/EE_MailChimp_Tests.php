<?php
/**
 * Contains test class for EE_MailChimp.class.php
 *
 * @since       2.1.0
 * @package     EE4 MailCHimp
 * @subpackage  Tests
 */


/**
 * Test class for EE_MailChimp
 */
class EE_MailChimp_Tests extends EE_UnitTestCase {

    /**
     * Tests the loading of the main MC file.
     */
    function test_loading_MailChimp() {
        $this->assertEquals( has_action('AHEE__EE_System__load_espresso_addons', 'load_ee4_espresso_mailchimp_class'), 11 );
        $this->assertTrue( class_exists( 'EE_MailChimp' ) );
    }

    /**
     * Tests the loading of all of the MC add-on files throught EE Addon API.
     * 
     */
    function test_all_the_autoloader_paths_loaded() {
        // autoloader_paths
        $this->assertTrue( class_exists( 'EE_MCI_Controller' ) );
    }

    function test_all_the_module_paths_loaded() {
        // module_paths
        $this->assertTrue( class_exists( 'EED_Mailchimp' ) );
    }

    function test_all_the_config_class_paths_loaded() {
        // config_class
        $this->assertTrue( class_exists( 'EE_Mailchimp_Config' ) );
    }

    /**
     * Check if MailChimp API loaded.
     */
    function test_if_the_mailchimp_api_loaded() {
        // MailChimp API.
        $this->assertTrue( class_exists( '\EEA_MC\MailChimp' ) );
    }

    function test_all_the_dms_paths_loaded() {
        // dms_paths
        $this->assertTrue( class_exists( 'EE_DMS_MailChimp_2_0_0' ) );
        $this->assertTrue( class_exists( 'EE_DMS_2_0_0_mc_list_group' ) );
        $this->assertTrue( class_exists( 'EE_DMS_2_0_0_mc_options' ) );
    }

    /**
     * Tests the return value of name() method (that had to override parent).
     */
    function test_mc_returns_correvt_addon_name() {
        $mc = new EE_MailChimp();
        $this->assertEquals( 'MailChimp', $mc->name() );
    }
}