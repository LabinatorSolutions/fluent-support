<!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <!--[if gte mso 15]>
    <xml>
    <o:OfficeDocumentSettings>
        <o:AllowPNG/>
        <o:PixelsPerInch>96</o:PixelsPerInch>
    </o:OfficeDocumentSettings>
    </xml>
    <![endif]-->
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0" >
    <link rel="profile" href="https://gmpg.org/xfn/11">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
</head>
<body>
<div class="fs_comment">
    <p>Hi <strong><em><?php echo $customer->full_name; ?></em>,</strong></p>
    <p>Your ticket - <?php echo $ticket->title; ?></p>
    <p>We hope that the ticket was resolved to your satisfaction. If you feel that the ticket should not be closed or if the ticket has not been resolved, please reopen the ticket (<a href="<?php echo $ticket->view_url; ?>">#<?php echo $ticket->id; ?></a>)</p>
    <p>Regards,<br /><?php echo $settings['business_name']; ?></p>
</div>

<div style="color: #9e9e9e; margin: 10px 0 14px 0; padding-top: 10px;border-top: 1px solid #eeeeee;">
    This email is a service from <?php echo $settings['business_name'] ?>. Support Plugin is Powered by <a href="https://fluentsupport.com" style="color:black" target="_new">FluentSupport</a>.
</div>
</body>
</html>
