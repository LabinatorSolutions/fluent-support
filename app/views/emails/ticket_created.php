<p>Hi <strong><em><?php echo $customer->full_name; ?></em>,</strong></p>

<p>Your request (<a href="<?php echo $ticket->view_url; ?>">#<?php echo $ticket->id; ?></a>) has been received, and is being reviewed by our support staff.</p>

<p>To add additional comments, follow the link below:</p>

<h4><a href="<?php echo $ticket->view_url; ?>">View Ticket</a></h4>

<p>or follow this link: <?php echo $ticket->view_url; ?></p>

<hr />

<p>Regards,<br /><?php echo $settings['business_name']; ?></p>
