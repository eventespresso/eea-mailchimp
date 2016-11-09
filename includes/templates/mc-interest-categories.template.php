<?php
// If no list selected, just move on.
if ( $list_id === '-1' || $list_id === NULL ) {
	return '';
}
?>
<div id="ee-mailchimp-groups-list">
	<label for="ee-mailchimp-groups"><?php _e( 'Please select a Group:', 'event_espresso' );?></label>
	<dl id="ee-mailchimp-groups" class="ee_mailchimp_dropdowns">
		<?php
		if ( ! empty( $user_groups ) ) {
			$all_interests = array();
			foreach ( $user_groups as $category ) {
				$category_interests = $this->mci_get_interests( $list_id, $category['id'] );
				$type = $category['type'];
				// Do not display if set as hidden.
				if ( $type === 'hidden' ) {
					continue;
				}
				?>
				<dt><b><?php echo $category['title']; ?></b></dt>
				<?php
				// Is this a drop-down ?
				if ( $type === 'dropdown' ) {
					?> <dd> <select id="<?php echo $category['id']; ?>" name="ee_mailchimp_groups[]"> <?php
				}
				foreach ( $category_interests as $interest ) {
					$all_interests[] = $interest_id = $interest['id'] . '-' . $interest['category_id'] . '-' . base64_encode($interest['name']);
					switch ( $type ) {
						case 'checkboxes':
							?><dd>
								<input type="checkbox" id="<?php echo $interest_id; ?>" value="<?php echo $interest_id; ?>" name="ee_mailchimp_groups[]" <?php echo ( in_array( $interest_id, $event_list_group )) ? 'checked' : ''; ?>>
								<label for="<?php echo $interest_id; ?>"><?php echo $interest['name']; ?></label>
							</dd><?php
							break;
						case 'radio':
							?><dd>
								<input type="radio" id="<?php echo $interest_id; ?>" value="<?php echo $interest_id; ?>" name="ee_mailchimp_groups[<?php echo $category['id']; ?>]" <?php echo ( in_array( $interest_id, $event_list_group )) ? 'checked' : ''; ?>>
								<label for="<?php echo $interest_id; ?>"><?php echo $interest['name']; ?></label>
							</dd><?php
							break;
						case 'dropdown':
							?>
								<option id="<?php echo $interest_id; ?>" value="<?php echo $interest_id; ?>" <?php echo ( in_array( $interest_id, $event_list_group )) ? 'selected' : ''; ?>>
								<label for="<?php echo $interest_id; ?>"><?php echo $interest['name']; ?></label>
							<?php
							break;
						default:
							?><dd>
								<input type="checkbox" id="<?php echo $interest_id; ?>" value="<?php echo $interest_id; ?>" name="ee_mailchimp_groups[]" <?php echo ( in_array( $interest_id, $event_list_group )) ? 'checked' : ''; ?>>
								<label for="<?php echo $interest_id; ?>"><?php echo $interest['name']; ?></label>
							</dd><?php
							break;
					}
				}
				if ( $type === 'dropdown' ) {
					?> </select> </dd> <?php
				}
			}
			foreach ( $all_interests as $key => $intr ) {
				?>
				<input id="ee-mc-intr-list-<?php echo $key;?>" name="ee_mc_list_all_interests[]" value="<?php echo $intr; ?>" type="hidden" >
				<?php
			}
		} else {
			?>
			<p class="important-notice"><?php _e( 'No groups found for this List.', 'event_espresso' );?></p>
			<?php
		}
		?>
	</dl>
</div>