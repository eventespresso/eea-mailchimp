<div id="espresso-mci-list-merge-fields">
	<table class="espresso_mci_merge_fields_tb">
		<thead>
			<tr class="ee_mailchimp_field_heads">
				<th><b><?php _e( 'List Fields', 'event_espresso' );?></b></th>
				<th><b><?php _e( 'Form Fields', 'event_espresso' );?></b></th>
			</tr>
		</thead>
		<tbody>
			<?php
			foreach ($list_fields as $l_field) {
				$starred = '*';
				// Skip if field is not public.
				if ( $l_field['public'] === false ) continue;
				?>
				<tr>
					<td>
						<p id="mci-field-<?php echo base64_encode($l_field['name']); ?>" class="ee_mci_list_fields">
							<?php echo $l_field['name']; echo ( $l_field['required'] ) ? '<span class="nci_asterisk">' . $starred . '</span>' : ''; ?>
						</p>
					</td>
					<td>
						<select id="event-question-<?php echo base64_encode($l_field['name']); ?>" name="<?php echo base64_encode($l_field['tag']); ?>" class="ee_event_fields_selects" >
							<option value="-1"><?php _e( 'none', 'event_espresso' );?></option>
								<?php foreach ($evt_questions as $q_field) { ?>
									<option value="<?php echo $q_field['QST_ID']; ?>"
										<?php
										// Default to main fields if exist:
										if (
											( isset( $l_field['tag'], $selected_fields[ $l_field['tag'] ] )
											&& ( $selected_fields[ $l_field['tag'] ] == $q_field['QST_ID'] || ( isset($this->_question_list_id[$q_field['QST_ID']]) && $selected_fields[ $l_field['tag'] ] == $this->_question_list_id[$q_field['QST_ID']] ) ) )
											|| ( ($q_field['QST_ID'] == 3 || $q_field['QST_ID'] == 'email') && $l_field['tag'] == 'EMAIL' && ! array_key_exists( 'EMAIL', $selected_fields ))
											|| ( ($q_field['QST_ID'] == 2 || $q_field['QST_ID'] == 'lname') && $l_field['tag'] == 'LNAME' && ! array_key_exists( 'LNAME', $selected_fields ))
											|| ( ($q_field['QST_ID'] == 1 || $q_field['QST_ID'] == 'fname') && $l_field['tag'] == 'FNAME' && ! array_key_exists( 'FNAME', $selected_fields ))
										) {
											echo 'selected';
										}
										?>>
										<?php echo $q_field['QST_Name']; ?>
									</option>
								<?php } ?>
						</select>
					</td>
				</tr>
				<?php
				$hide_fields[] = $l_field['tag'];
			}
			?>
		</tbody>
	</table>
	<input type="hidden" name="ee_mailchimp_qfields" value="<?php echo base64_encode(serialize($hide_fields)); ?>" />
</div>