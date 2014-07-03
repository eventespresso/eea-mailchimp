<?php
/**
 *  Meant to convert DBs between MailChimp for EE3 to MC for EE4.
 */

/**
 * Make sure we have all the stages loaded too
 * unfortunately, this needs to be done upon INCLUSION of this file,
 * instead of construction, because it only gets constructed on first page load
 * (all other times it gets resurrected from a wordpress option).
 */
$stages = glob( ESPRESSO_MAILCHIMP_DIR . 'db/migration_scripts/2_0_0_stages/*' );
$class_to_filepath = array();
foreach( $stages as $filepath ) {
    $matches = array();
    preg_match( '~2_0_0_stages/(.*).dmsstage.php~', $filepath, $matches );
    $class_to_filepath[$matches[1]] = $filepath;
}
//give addons a chance to autoload their stages too
$class_to_filepath = apply_filters( 'FHEE__EE_DMS_MailChimp_2_0_0_stages__autoloaded_stages', $class_to_filepath );
EEH_Autoloader::register_autoloader( $class_to_filepath );


class EE_DMS_MailChimp_2_0_0 extends EE_Data_Migration_Script_Base {

    public function __construct() {
        $this->_pretty_name = __("Data Migration to EE4 MailChimp.", "event_espresso");
        $this->_migration_stages = array(
            new EE_DMS_2_0_0_mc_list_group()
        );
        parent::__construct();
    }

    public function can_migrate_from_version($version_array) {
        $version_string = '0';
        if ( get_option('ee4_mailchimp_db_update') ) {
            $version_string = get_option('ee4_mailchimp_db_update');
        }
        if ( $version_string < '2.0.0' ) {
            // Can be migrated.
            return true;
        } elseif ( $version_string === '0' ) {
            // If '0' the db for mailchimp was not yet updated.
            return false;
        } else {
            // Version doesnt apply for this migration.
            return false;
        }
    }

    public function pretty_name() {
        return __("Data Migration to EE4 MailChimp.", "event_espresso");
    }

    public function schema_changes_before_migration() {
        // Relies on 4.1's EEH_Activation::create_table.
        require_once( EE_HELPERS . 'EEH_Activation.helper.php' );

        $table_name = 'esp_event_mailchimp_list_group';
        $sql = " EMC_ID INT UNSIGNED NOT NULL AUTO_INCREMENT ,
                EVT_ID INT UNSIGNED NOT NULL ,
                AMC_mailchimp_list_id TEXT NOT NULL ,
                AMC_mailchimp_group_id TEXT NOT NULL ,
                PRIMARY KEY  (EMC_ID)";
        $this->_table_is_new_in_this_version($table_name,$sql, 'ENGINE=InnoDB');

        $table_name = 'esp_event_question_mailchimp_field';
        $sql = " QMC_ID INT UNSIGNED NOT NULL AUTO_INCREMENT ,
                EVT_ID INT UNSIGNED NOT NULL ,
                QST_ID TEXT NOT NULL ,
                QMC_mailchimp_field_id TEXT NOT NULL ,
                PRIMARY KEY  (QMC_ID)";
        $this->_table_is_new_in_this_version($table_name,$sql, 'ENGINE=InnoDB');

        // Setting up the config wp option pretty well counts as a 'schema change', or at least should happen here.
        EE_Config::instance()->update_espresso_config(false, true);
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
        // but still will update the mailchimp db version here.
        update_option('ee4_mailchimp_db_update', '2.0.0');
        return true;
    }

    public function migration_page_hooks() {

    }
}