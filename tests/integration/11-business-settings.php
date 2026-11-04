<?php
/**
 * Helper::getBusinessSettings() returns the caller's default only for a key
 * that is neither stored nor in the built-in defaults.
 */

use FluentSupport\App\Services\EmailNotification\Settings;
use FluentSupport\App\Services\Helper;

return function () {

    FsTest::case('business settings: an unknown key falls back to the caller default', function () {
        FsTest::assertSame('fallback', Helper::getBusinessSettings('fs_test_no_such_setting', 'fallback'), 'caller default');
        FsTest::assertSame(null, Helper::getBusinessSettings('fs_test_no_such_setting'), 'no default given');
    });

    FsTest::case('business settings: a known key ignores the caller default', function () {
        $expected = (new Settings())->globalBusinessSettings(false)['keyboard_shortcuts'];

        FsTest::assertSame($expected, Helper::getBusinessSettings('keyboard_shortcuts', 'not-a-real-value'), 'stored or built-in value wins');
    });
};
