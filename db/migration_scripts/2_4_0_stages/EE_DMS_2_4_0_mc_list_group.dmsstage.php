<?php

/**
 * Converts EE3 MailChimp Integration Event - List - Groups data to EE4 MCI.
 */

class EE_DMS_2_4_0_mc_list_group extends EE_Data_Migration_Script_Stage {

	/**
	 * This table name.
	 * @access protected
	 */
	protected $_table_name;

	/**
	 * Interests List.
	 * @var array  {event_id {list_id {category_id {interest_name {interest data {id, name}}}}}}
	 * @access protected
	 */
	protected $_list_interests;

	/**
	 * Count of rows.
	 * @access protected
	 */
	protected $_to_migrate_count;


	function __construct() {
		global $wpdb;
		$this->_pretty_name = __("Mailchimp List Group", "event_espresso");
		$this->_table_name = $wpdb->prefix . "esp_event_mailchimp_list_group";
		$this->_to_migrate_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$this->_table_name} WHERE AMC_mailchimp_list_id != '-1'" );

		$this->_list_interests = array();

		parent::__construct();
	}


	/**
	 * Update/Migrate "esp_event_mailchimp_list_group" Table rows.
	 *
	 * @access protected
	 */
	protected function _migration_step( $num_items = 1 ) {
		global $wpdb;
		require_once( ESPRESSO_MAILCHIMP_DIR . 'includes' . DS . 'MailChimp.class.php' );
		$items_actually_migrated = 0;

		// Get all events that belong to lists Lists.
		$query = "SELECT EVT_ID AS id, AMC_mailchimp_list_id AS list FROM {$this->_table_name} WHERE AMC_mailchimp_list_id != '-1' GROUP BY EVT_ID";
		$events = $wpdb->get_results( $query, ARRAY_A );

		// Get all from that table.
		$table_query = "SELECT * FROM {$this->_table_name} WHERE AMC_mailchimp_list_id != '-1'";
		$list_group_tbl = $wpdb->get_results( $table_query, ARRAY_A );

		// Try to get the API Key.
		$the_api_key = false;
		$config = EE_Config::instance()->get_config( 'addons', 'EE_Mailchimp', 'EE_Mailchimp_Config' );
		if ( $config instanceof EE_Mailchimp_Config ) {
			$the_api_key = $config->api_settings->api_key;
		}

		// Get all event-list-interests data.
		if ( $events && is_array($events) && isset($config) && $the_api_key ) {
			$mci_controller = new EE_MCI_Controller();
			$key_ok = $mci_controller->mci_is_api_key_valid($the_api_key);

			if ( $key_ok ) {
				// So we got here. Lets get the interest categories.
				foreach ( $events as $ulist ) {
					$categories = $mci_controller->mci_get_users_groups($ulist['list']);

					if ( empty($categories) || ! is_array($categories) ) {
						continue;
					}
					// So we try to get the interests themselves.
					foreach ($categories as $icategorie) {
						$interests = $mci_controller->mci_get_interests($ulist['list'], $icategorie['id']);
						// No interests ? Move on...
						if ( empty($interests) || ! is_array($interests) ) {
							continue;
						}
						foreach ($interests as $interest) {
							// ID's in API v2 and v3 are different so we need to go by the interest names.
							// If there are same names we need to somehow id them.
							$this->_list_interests[$ulist['id']][$ulist['list']][$interest['name']][] = $interest;
						}
					}
				}

				// Go through the the table and update each line.
				$saved_interests = array();
				if ( ! empty($this->_list_interests) ) {
					// Need to backup.
					$interesrs_list = $this->_list_interests;
					foreach ($list_group_tbl as $column) {
						if ( strval($column['AMC_mailchimp_list_id']) === '-1' ) {
							continue;
						}
						$listgroup = explode('-', $column['AMC_mailchimp_group_id']);
						$evt_id = $column['EVT_ID'];
						$list_id = $column['AMC_mailchimp_list_id'];
						$intr_name = base64_decode($listgroup[2]);
						$intr_data = array_shift($interesrs_list[$evt_id][$list_id][$intr_name]);

						if ( ! empty($intr_data) && is_array($intr_data) ) {
							// Update current row data.
							$list_group_id = $intr_data['id'] . '-' . $intr_data['category_id'] . '-' . base64_encode($intr_data['name']) . '-true';
							$success = $wpdb->update( $this->_table_name,
								array('AMC_mailchimp_group_id' => $list_group_id),
								array('EMC_ID' => intval($column['EMC_ID'])),
								array('%s','%s'),
								array('%d')
							);
							if ( ! $success ) {
								$this->add_error(sprintf( __( 'Could not update line: "%s", because %s', 'event_espresso' ), $column['EMC_ID'], $wpdb->last_error ));
							} else {
								$saved_interests[] = $intr_data['id'];
								$items_actually_migrated++;
							}
						} else {
							$this->add_error(sprintf(__("Could not migrate line: '%s' table data.", "event_espresso"), $column['EMC_ID']));
						}
					}

					// Now need to add the not selected interests as API v3 uses those also.
					foreach ($this->_list_interests as $evnt_id => $event) {
						foreach ($event as $lst_id => $list) {
							foreach ($list as $linterest) {
								foreach ($linterest as $intr) {
									$this->_insert_interest($intr, $evnt_id, $lst_id, $saved_interests);
								}
							}
						}
					}
				} else {
					$this->add_error(sprintf(__("Could not get data from MailChimp. Not able to migrate the '%s' table data.", "event_espresso"), $this->_table_name));
				}
			} else {
				$this->add_error(sprintf(__("Could not migrate the '%s' table data because the Mailchimp API Key is not valid.", "event_espresso"), $this->_table_name));
			}
		} else {
			$this->add_error(sprintf(__("Could not update the '%s' table data.", "event_espresso"), $this->_table_name));
		}

		$this->set_completed();

		return $items_actually_migrated;
	}


	/**
	 * Insert interest data.
	 *
	 * @access protected
	 * @param array $interest Interest information.
	 * @param string $evnt_id Event ID.
	 * @param string $lst_id List ID.
	 * @param array $saved_interests IDs.
	 * @return void
	 */
	protected function _insert_interest( $interest, $evnt_id, $lst_id, $saved_interests ) {
		global $wpdb;
		// If not in the DB then add as not selected interest.
		if ( in_array($interest['id'], $saved_interests) ) {
			return false;
		}
		$lg_id = $interest['id'] . '-' . $interest['category_id'] . '-' . base64_encode($interest['name']) . '-false';
		$cols_n_values = array(
			'EVT_ID' => $evnt_id,
			'AMC_mailchimp_list_id' => $lst_id,
			'AMC_mailchimp_group_id' => $lg_id
		);
		$data_types = array(
			'%d',	// EVT_ID
			'%s',	// AMC_mailchimp_list_id
			'%s',	// AMC_mailchimp_group_id
		);
		$ins_success = $wpdb->insert($this->_table_name, $cols_n_values, $data_types);
	}


	function _count_records_to_migrate() {
		return $this->_to_migrate_count;
	}
}