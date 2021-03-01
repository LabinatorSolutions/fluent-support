<p>Hi <strong><em><?php echo $customer->full_name; ?></em>,</strong></p>

<p>Your ticket - <?php echo $ticket->title; ?></p>

<p>We hope that the ticket was resolved to your satisfaction. If you feel that the ticket should not be closed or if the ticket has not been resolved, please reopen the ticket (<a href="<?php echo $ticket->view_url; ?>">#<?php echo $ticket->id; ?></a>)</p>

<hr />

<p>Regards,<br /><?php echo $settings['business_name']; ?></p>
