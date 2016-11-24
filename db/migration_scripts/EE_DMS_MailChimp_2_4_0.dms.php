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

$stages = glob( ESPRESSO_MAILCHIMP_DIR . 'db/migration_scripts/2_4_0_stages/*' );
$class_to_filepath = array();
foreach( $stages as $filepath ) {
	$matches = array();
	preg_match( '~2_4_0_stages/(.*).dmsstage.php~', $filepath, $matches );
	$class_to_filepath[$matches[1]] = $filepath;
}

// Give add-ons a chance to autoload their stages too.
$class_to_filepath = apply_filters( 'FHEE__EE_DMS_MailChimp_2_4_0_stages__autoloaded_stages', $class_to_filepath );
EEH_Autoloader::register_autoloader( $class_to_filepath );


class EE_DMS_MailChimp_2_4_0 extends EE_Data_Migration_Script_Base {

	public function __construct() {
		$this->_pretty_name = __("EE4 MailChimp data Migration to 2.4.0", "event_espresso");
		$this->_migration_stages = array(
			new EE_DMS_2_4_0_mc_list_group()
		);
		parent::__construct();
	}


	public function can_migrate_from_version($version_array) {
		global $wpdb;
		$version_string = false;
		// Get versions.
		$core_version = $version_array['Core'];
		if ( isset($version_array['MailChimp']) ) {
			$version_string = $version_array['MailChimp'];
		}
		// Try to get the API Key.
		$key_ok = false;
		$config = EE_Config::instance()->get_config( 'addons', 'EE_Mailchimp', 'EE_Mailchimp_Config' );
		if ( $config instanceof EE_Mailchimp_Config ) {
			$the_api_key = $config->api_settings->api_key;
			if ( $the_api_key && ! empty($the_api_key) ) {
				$key_ok = $this->_mc_api_key_valid($the_api_key);
			}
		}
		// Is there anything on the Table we want to update?
		$table_name = $wpdb->prefix . "esp_event_mailchimp_list_group";
		$count = 0;
		// Table exists ?
		if ( $this->_get_table_analysis()->tableExists( $table_name ) ) {
			$count = $wpdb->get_var( "SELECT COUNT(EMC_ID) FROM $table_name" );
		}

		if ( version_compare($version_string, '2.3.0', '>=')
			&& version_compare($version_string, '2.4.0', '<')
			&& version_compare($core_version, '4.4.0', '>=')
			&& $count > 0 && $key_ok ) {
			// Can be migrated.
			return true;
		} elseif ( ! $version_string ) {
			// No version string provided.
			return false;
		} else {
			// Version doesn't apply for this migration.
			return false;
		}
	}


	public function schema_changes_before_migration() {
		 require_once( EE_HELPERS . 'EEH_Activation.helper.php' );

        $table_name = 'esp_event_mailchimp_list_group';
        $sql = " EMC_ID int unsigned NOT NULL AUTO_INCREMENT,
                EVT_ID int unsigned NOT NULL,
                AMC_mailchimp_list_id TEXT NOT NULL,
                AMC_mailchimp_group_id TEXT NOT NULL,
                PRIMARY KEY (EMC_ID)";
        $this->_table_should_exist_previously($table_name,$sql, 'ENGINE=InnoDB');

        $table_name = 'esp_event_question_mailchimp_field';
        $sql = " QMC_ID int unsigned NOT NULL AUTO_INCREMENT,
                EVT_ID int unsigned NOT NULL,
                QST_ID TEXT NOT NULL,
                QMC_mailchimp_field_id TEXT NOT NULL,
                PRIMARY KEY (QMC_ID)";
        $this->_table_should_exist_previously($table_name,$sql, 'ENGINE=InnoDB');

		 // Setting up the config wp option pretty well counts as a 'schema change', or at least should happen here.
        EE_Config::instance()->update_espresso_config(false, true);
        
        return true;
    }


    /**
	 * Validate the MailChimp API key.
	 *
	 * @access public
	 * @param string $api_key MailChimp API Key.
	 * @return mixed  If key valid then return the key. If not valid - return FALSE.
	 */
	public function _mc_api_key_valid( $api_key = NULL ) {
		require_once( ESPRESSO_MAILCHIMP_DIR . 'includes' . DS . 'MailChimp.class.php' );
		// Make sure API key only has one '-'
		$exp_key = explode( '-', $api_key );
		if ( ! is_array( $exp_key ) || count( $exp_key ) != 2 ) {
			return FALSE;
		}

		// Check if key is live/acceptable by API.
		try {
			$MailChimp = new EEA_MC\MailChimp( $api_key );
			$reply = $MailChimp->get('');
		} catch ( Exception $e ) {
			return FALSE;
		}

		// If a reply is present, then let's process that.
		if ( ! $MailChimp->success() || ! isset($reply['account_id']) ) {
			return FALSE;
		}
		return $api_key;
	}


	public function pretty_name() {
		return __("EE4 MailChimp data Migration to 2.4.0", "event_espresso");
	}


    public function schema_changes_after_migration() {
        return true;
    }
}