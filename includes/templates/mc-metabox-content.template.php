<?php
// Verify the API key.
if ( ! empty( $this->_config->api_settings->api_key ) ) {
	// Get saved list for this event (if there's one)
	$this->list_id = $this->mci_event_list( $event->ID );
	$this->category_id = $this->mci_event_list_group( $event->ID );
	?>
	<div class="espresso_mailchimp_integration_metabox">
		<div class="espresso_mci_lists_groups">
			<div id="espresso-mci-lists">
				<?php
				// Lists / Groups section
				$this->mci_list_mailchimp_lists( $this->list_id );
				?>
			</div>
			<div id="ee-mailchimp-ajax-loading-groups" class="ee-mailchimp-ajax-loading" style="display:none;">
				<span class="ee-spinner ee-spin"></span>
				<span class="ee-loading-txt small-text"><?php _e( 'loading...', 'event_espresso' );?></span>
			</div>
			<div id="espresso-mci-groups-list">
				<?php $this->mci_list_mailchimp_groups( $event->ID, $this->list_id ); ?>
			</div>
		</div>
		<div id="ee-mailchimp-ajax-loading-fields" class="ee-mailchimp-ajax-loading" style="display:none;">
			<span class="ee-spinner ee-spin"></span>
			<span class="ee-loading-txt small-text"><?php _e( 'loading...', 'event_espresso' );?></span>
		</div>
		<div id="espresso-mci-list-fields" class="espresso_mci_list_fields">
			<?php $this->mci_list_mailchimp_fields( $event->ID, $this->list_id ); ?>
		</div>
	</div>
	<?php
} else {
	?>
	<div class="espresso_mailchimp_integration_metabox">
		<p>
			<?php
			printf(
				__( '%1$sInvalid MailChimp API%2$s%3$sPlease visit the %4$sMailChimp Admin Page%5$s to correct the issue.', 'event_espresso' ),
				'<span class="important-notice">',
				'</span>',
				'<br />',
				'<a href="' . admin_url( 'admin.php?page=mailchimp' ) . '">',
				'</a>'
			);
			?>
		</p>
	</div>
	<?php
}