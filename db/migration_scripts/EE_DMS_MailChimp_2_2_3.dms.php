<?php
/**
 *  Meant to convert DBs between MailChimp 2.1.0 and MC 2.2.3.
 */
/**
 * Make sure we have all the stages loaded too
 * unfortunately, this needs to be done upon INCLUSION of this file,
 * instead of construction, because it only gets constructed on first page load
 * (all other times it gets resurrected from a wordpress option).
 */

$stages = glob( ESPRESSO_MAILCHIMP_DIR . 'db/migration_scripts/2_2_3_stages/*' );
$class_to_filepath = array();
foreach( $stages as $filepath ) {
    $matches = array();
    preg_match( '~2_2_3_stages/(.*).dmsstage.php~', $filepath, $matches );
    $class_to_filepath[$matches[1]] = $filepath;
}
//give addons a chance to autoload their stages too
$class_to_filepath = apply_filters( 'FHEE__EE_DMS_MailChimp_2_2_3_stages__autoloaded_stages', $class_to_filepath );
EEH_Autoloader::register_autoloader( $class_to_filepath );


class EE_DMS_MailChimp_2_2_3 extends EE_Data_Migration_Script_Base {

    public function __construct() {
        $this->_pretty_name = __("EE4 MailChimp data Migration to 2.2.0", "event_espresso");
        $this->_migration_stages = array(
            new EE_DMS_2_2_3_mc_options()
        );
        parent::__construct();
    }

    public function can_migrate_from_version($version_array) {
        $version_string = false;

        $core_version = $version_array['Core'];
        if ( isset($version_array['MailChimp']) ) {
            $version_string = $version_array['MailChimp'];
        }

        $mc_new_config = EE_Config::instance()->get_config( 'addons', 'Mailchimp', 'EE_Mailchimp_Config' );
        $mc_old_config = EE_Config::instance()->get_config( 'addons', 'EE_Mailchimp', 'EE_Mailchimp_Config' );

        if ( ( ( version_compare( $version_string, '2.1.0', '>=' ) && version_compare( $version_string, '2.2.0', '<' ) )
            || ( version_compare( $version_string, '2.2.0', '>=' ) && version_compare( $version_string, '2.2.3', '<' ) && empty($mc_new_config->api_settings->api_key) ) )
            && version_compare( $core_version, '4.4.0', '>=' ) && isset($mc_old_config->api_settings) && ! empty($mc_old_config->api_settings->api_key) ) {
            // Can be migrated.
            return true;
        } elseif ( ! $version_string ) {
            // No version string provided.
            return false;
        } else {
            // Version doesnt apply for this migration.
            return false;
        }
    }

    public function pretty_name() {
        return __("EE4 MailChimp data Migration to 2.2.3", "event_espresso");
    }

    public function schema_changes_before_migration() {
        return true;
    }

    /**
     * Yes we could have cleaned up the EE3 MailChimp tables here. But just in case someone
     * didn't backup their DB, and decides they want ot keep using EE3, we'll
     * leave them for now.
     *
     * @return boolean
     */
    public function schema_changes_after_migration() {
        return true;
    }

    public function migration_page_hooks() {

    }
}