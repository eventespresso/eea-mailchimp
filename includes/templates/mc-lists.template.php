<label for="ee-mailchimp-lists">
	<?php _e( 'Please select a List:', 'event_espresso' );?>
</label><br />
<?php
$mc_lists = $this->mci_get_users_lists();
array_push( $mc_lists, array( 'id' => '-1', 'name' => __( 'Do not send to MailChimp', 'event_espresso' )));
if ( ! empty( $mc_lists ) ) {
	$selected_found = false;
	?>
	<select id="ee-mailchimp-lists" name="ee_mailchimp_lists" class="ee_mailchimp_dropdowns">
		<?php foreach ( $mc_lists as $list ) { 
			$selected = '';
			// If settings saved then default to "Do not send" option.
			if ( $list_id === $list['id'] || ( ! $selected_found && $list['id'] === '-1') ) {
				$selected = 'selected="selected"';
				$selected_found = true;
			}
			?>
			<option value="<?php echo $list['id']; ?>" <?php echo $selected; ?>><?php echo $list['name']; ?></option>
		<?php } ?>
	</select>
	<?php
} else {
	?>
	<p class="important-notice"><?php _e( 'No lists found! Please log into your MailChimp account and create at least one mailing list.', 'event_espresso' );?></p>
	<?php
}