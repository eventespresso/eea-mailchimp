<?php
/**
 *  Meant to convert DBs between MailChimp 2.1.0 and MC 2.3.0.
 */
/**
 * Make sure we have all the stages loaded too
 * unfortunately, this needs to be done upon INCLUSION of this file,
 * instead of construction, because it only gets constructed on first page load
 * (all other times it gets resurrected from a wordpress option).
 */

$stages = glob(ESPRESSO_MAILCHIMP_DIR . 'db/migration_scripts/2_3_0_stages/*');
$class_to_filepath = array();
foreach ($stages as $filepath) {
    $matches = array();
    preg_match('~2_3_0_stages/(.*).dmsstage.php~', $filepath, $matches);
    $class_to_filepath[ $matches[1] ] = $filepath;
}
// give addons a chance to autoload their stages too
$class_to_filepath = apply_filters('FHEE__EE_DMS_MailChimp_2_3_0_stages__autoloaded_stages', $class_to_filepath);
EEH_Autoloader::register_autoloader($class_to_filepath);


class EE_DMS_MailChimp_2_3_0 extends EE_Data_Migration_Script_Base
{

    public function __construct()
    {
        $this->_pretty_name = __("EE4 MailChimp data Migration to 2.2.0", "event_espresso");
        $this->_migration_stages = array(
            new EE_DMS_2_3_0_mc_options()
        );
        parent::__construct();
    }

    public function can_migrate_from_version($version_array)
    {
        $version_string = false;

        $core_version = $version_array['Core'];
        if (isset($version_array['MailChimp'])) {
            $version_string = $version_array['MailChimp'];
        }
        EE_Registry::instance()->load_core('Config');
        $mc_new_config = EE_Config::instance()->get_config('addons', 'Mailchimp', 'EE_Mailchimp_Config');
        $mc_old_config = null;
        if (isset(EE_Config::instance()->addons->EE_Mailchimp)) {
            $mc_old_config = EE_Config::instance()->get_config('addons', 'EE_Mailchimp', 'EE_Mailchimp_Config');
        }

        if (( ( version_compare($version_string, '2.1.0', '>=') && version_compare($version_string, '2.2.0', '<') )
            || ( version_compare($version_string, '2.2.0', '>=') && version_compare($version_string, '2.3.0', '<') && empty($mc_new_config->api_settings->api_key) ) )
            && version_compare($core_version, '4.4.0', '>=') && isset($mc_old_config->api_settings) && ! empty($mc_old_config->api_settings->api_key) ) {
            // Can be migrated.
            return true;
        } elseif (! $version_string) {
            // No version string provided.
            return false;
        } else {
            // Version doesnt apply for this migration.
            return false;
        }
    }

    public function pretty_name()
    {
        return __("EE4 MailChimp data Migration to 2.3.0", "event_espresso");
    }

    public function schema_changes_before_migration()
    {
         require_once(EE_HELPERS . 'EEH_Activation.helper.php');

        $table_name = 'esp_event_mailchimp_list_group';
        $sql = " EMC_ID INT UNSIGNED NOT NULL AUTO_INCREMENT ,
                EVT_ID INT UNSIGNED NOT NULL ,
                AMC_mailchimp_list_id TEXT NOT NULL ,
                AMC_mailchimp_group_id TEXT NOT NULL ,
                PRIMARY KEY  (EMC_ID)";
        $this->_table_should_exist_previously($table_name, $sql, 'ENGINE=InnoDB');

        $table_name = 'esp_event_question_mailchimp_field';
        $sql = " QMC_ID INT UNSIGNED NOT NULL AUTO_INCREMENT ,
                EVT_ID INT UNSIGNED NOT NULL ,
                QST_ID TEXT NOT NULL ,
                QMC_mailchimp_field_id TEXT NOT NULL ,
                PRIMARY KEY  (QMC_ID)";
        $this->_table_should_exist_previously($table_name, $sql, 'ENGINE=InnoDB');

        return true;
    }

    /**
     * Yes we could have cleaned up the EE3 MailChimp tables here. But just in case someone
     * didn't backup their DB, and decides they want ot keep using EE3, we'll
     * leave them for now.
     *
     * @return boolean
     */
    public function schema_changes_after_migration()
    {
        // totally remove the old mailchimp config object. kill it! kill it!
        EE_Config::instance()->addons->EE_Mailchimp = null;
        unset(EE_Config::instance()->addons->EE_Mailchimp);
        EE_Config::instance()->update_espresso_config(false, true);
        return true;
    }

    public function migration_page_hooks()
    {
    }
}
