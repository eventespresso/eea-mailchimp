<?php

/**
 * @type string $mailchimp_api_key
 * @type string $mailchimp_key_error
 * @type string $mailchimp_api_key_class
 * @type string $mailchimp_api_key_img
 * @type string $mailchimp_double_opt_check
 * @type string $mailchimp_html_emails
 * @type string $mailchimp_text_emails
 * @type string $submit_to_mc_end
 * @type string $submit_to_mc_complete
 * @type string $submit_to_mc_approved
 */

?>
<div class='inside'>
    <table class="mailchimp_api_keybox ee-admin-two-column-layout form-table">
        <tbody>
            <tr>
                <th>
                    <label for="ee-mailchimp-api-key">
                        <?php esc_html_e('Your MailChimp API Key:', 'event_espresso'); ?>
                    </label>
                </th>
                <td>
                    <?php echo $mailchimp_key_error; ?>
                    <input type="text"
                           size="45"
                           name="mailchimp_api_key"
                           id="ee-mailchimp-api-key"
                           class="<?php echo $mailchimp_api_key_class; ?>"
                           value="<?php echo $mailchimp_api_key; ?>"
                    />
                    <?php echo $mailchimp_api_key_img; ?>
                    <p class="description">
                        <?php
                        printf(
                            esc_html__(
                                '* Please %1$sclick here to learn how to create a MailChimp API key%2$s if you do not have one already.',
                                'event_espresso'
                            ),
                            '<a href="https://kb.mailchimp.com/article/where-can-i-find-my-api-key/" target="_blank">',
                            '</a>'
                        );
                        ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th>
                    <?php esc_html_e('MailChimp API Options:', 'event_espresso'); ?>
                </th>
                <td>
                    <label for="ee-mailchimp-double-opt">
                        <input type="checkbox"
                               id="ee-mailchimp-double-opt"
                               name="mailchimp_double_opt" <?php echo $mailchimp_double_opt_check; ?>
                        />
                        <?php esc_html_e('Skip double opt-in emails.', 'event_espresso'); ?>
                    </label>

                    <p class="description">
                        <?php
                        printf(
                            esc_html__('* %1$sClick here to read about how double opt-in works%2$s.', 'event_espresso'),
                            '<a href="https://kb.mailchimp.com/article/how-does-confirmed-optin-or-double-optin-work/" target="_blank">',
                            '</a>'
                        );
                        ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th>
                    <label for='ee-mailchimp-emails_type'>
                        <?php esc_html_e('Email content type:', 'event_espresso'); ?>
                    </label>
                </th>
                <td>
                    <select id="ee-mailchimp-emails_type" name="emails_type">
                        <option value="html" <?php
                        echo $mailchimp_html_emails; ?> >HTML
                        </option>
                        <option value="text" <?php
                        echo $mailchimp_text_emails; ?> >Text
                        </option>
                    </select>

                    <p class="description">
                        <?php esc_html_e('* Type of emails to send to the subscriber.', 'event_espresso'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th>
                    <label for='ee-mailchimp-submit_to_mc_when'>
                        <?php esc_html_e('Submit to MailChimp when ...', 'event_espresso'); ?>
                    </label>
                </th>
                <td>
                    <select id="ee-mailchimp-submit_to_mc_when" name="submit_to_mc_when">
                        <option value="attendee-information-end" <?php
                        echo $submit_to_mc_end; ?> >
                            <?php esc_html_e('subscriber submits information.', 'event_espresso'); ?>
                        </option>
                        <option value="reg-step-completed" <?php
                        echo $submit_to_mc_complete; ?> >
                            <?php esc_html_e(
                                'registration is completed (payment status does not matter).',
                                'event_espresso'
                            );
                        ?>
                        </option>
                        <option value="reg-step-approved" <?php
                        echo $submit_to_mc_approved; ?> >
                            <?php esc_html_e(
                                'registration is completed with an approved status.',
                                'event_espresso'
                            );
                        ?>
                        </option>
                    </select>

                    <p class="description">
                        <?php esc_html_e(
                            '* When should the attendee data be submitted to MailChimp?',
                            'event_espresso'
                        );
?>
                    </p>
                </td>
            </tr>
        </tbody>
    </table>
</div>
