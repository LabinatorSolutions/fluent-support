<?php

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 ***** DO NOT CALL ANY FUNCTIONS DIRECTLY FROM THIS FILE ******
 *
 * This file will be loaded even before the framework is loaded
 * so the $app is not available here, only declare functions here.
 */


// Dev-only helpers (dd(), query log). build.sh leaves the file out of releases.
is_readable(__DIR__ . '/globals_dev.php') && include 'globals_dev.php';
