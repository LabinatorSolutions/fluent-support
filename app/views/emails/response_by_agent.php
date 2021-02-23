<p>Hi <strong><em><?php echo $customer->full_name; ?></em>,</strong></p>

<p>
    An agent just replied to your ticket "<strong><?php echo $ticket->title; ?></strong>" (<a href="<?php echo $ticket->view_url; ?>">#<?php echo $ticket->id; ?></a>). To view his reply or add additional comments, click the button below:
</p>
<h4><a href="<?php echo $ticket->view_url; ?>">View Ticket</a></h4>
<p>or follow this link: <?php echo $ticket->view_url; ?></p>

<hr />

<p>
    Regards,<br />
    <?php echo $settings['business_name']; ?>
</p>
