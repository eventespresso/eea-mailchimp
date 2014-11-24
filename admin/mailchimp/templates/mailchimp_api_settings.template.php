<?php
/** @type string $mailchimp_api_key */
/** @type string $mailchimp_api_key_class */
/** @type string $mailchimp_api_key_img */
/** @type string $mailchimp_double_opt_check */
?>
<div class='inside'>
	<table class="mailchimp_api_keybox form-table">
		<tbody>
			<tr valign="top">
				<th><label for="mailchimp_api_key"><?php _e( 'Your MailChimp API Key:', 'event_espresso' ); ?></th>
				<td>
					<?php echo $mailchimp_key_error; ?>
					<input size="45" type="text" name="mailchimp_api_key" id="ee-mailchimp-api-key"  class="<?php echo $mailchimp_api_key_class; ?>" value="<?php echo $mailchimp_api_key; ?>"/><?php echo $mailchimp_api_key_img; ?>
					<p class="description">
						<?php
						printf(
							__( '* Please %1$sclick here to learn how to create a MailChimp API key%2$s if you do not have one already.', 'event_espresso' ),
							'<a href="http://kb.mailchimp.com/article/where-can-i-find-my-api-key/" target="_blank">',
							'</a>'
						);
						?>
					</p>
				</td>
			</tr>
			<tr valign="top">
				<th><label><?php _e( 'MailChimp API Options:', 'event_espresso' ); ?></th>
				<td>
					<input type="checkbox" id="ee-mailchimp-double-opt" name="mailchimp_double_opt" <?php echo $mailchimp_double_opt_check; ?> />
					<label for="ee-mailchimp-double-opt"><?php _e( 'Skip double opt-in emails.', 'event_espresso' ); ?></label>

					<p class="description">
						<?php
						printf(
							__( '* %1$sClick here to read about how double opt-in works%2$s.', 'event_espresso' ),
							'<a href="http://kb.mailchimp.com/article/how-does-confirmed-optin-or-double-optin-work/" target="_blank">',
							'</a>'
						);
						?>
					</p>
				</td>
			</tr>
		</tbody>
	</table>
</div>
