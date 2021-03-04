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
    <?php echo $response->content; ?>
    <hr />
    <p>
        To add additional comments, <a href="<?php echo $ticket->view_url; ?>">Please click here</a>
        <br />or follow this link: <?php echo $ticket->view_url; ?>
    </p>
</div>

<div style="color: #9e9e9e; margin: 10px 0 14px 0; padding-top: 10px;border-top: 1px solid #eeeeee;">
    This email is a service from <?php echo $settings['business_name'] ?>. Support Plugin is Powered by <a href="https://fluentsupport.com" style="color:black" target="_new">FluentSupport</a>.
</div>
</body>
</html>
