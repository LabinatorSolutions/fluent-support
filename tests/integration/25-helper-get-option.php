<?php
/**
 * Helper::getOption() returns what is stored, falsy values included (roadmap
 * 2.10). It falls back to the default only when nothing is stored, when the
 * stored string is corrupt, or when the caller expects an array and the stored
 * value isn't one.
 * The option rows are removed in the finally.
 */

use FluentSupport\App\Models\Meta;
use FluentSupport\App\Services\Helper;

return function () {

    $prefix = FsTest::uniq('fs_test_option');
    $key = function ($name) use ($prefix) {
        return $prefix . '_' . $name;
    };

    try {
        FsTest::case('get_option_returns_a_stored_zero_string', function () use ($key) {
            Helper::updateOption($key('zero'), '0');

            FsTest::assertSame('0', Helper::getOption($key('zero'), 'yes'), 'stored "0"');
        });

        FsTest::case('get_option_returns_a_stored_empty_array', function () use ($key) {
            Helper::updateOption($key('empty_array'), []);

            FsTest::assertSame([], Helper::getOption($key('empty_array'), ['enabled' => 'yes']), 'stored []');
        });

        // updateOption() stores scalars as strings, so 0 comes back as "0".
        FsTest::case('get_option_returns_a_stored_zero', function () use ($key) {
            Helper::updateOption($key('int_zero'), 0);

            FsTest::assertSame('0', Helper::getOption($key('int_zero'), 5), 'stored 0');
        });

        FsTest::case('get_option_falls_back_when_nothing_is_stored', function () use ($key) {
            FsTest::assertSame('fallback', Helper::getOption($key('missing'), 'fallback'), 'no row');
        });

        FsTest::case('get_option_falls_back_on_a_corrupt_serialized_string', function () use ($key) {
            Meta::insert(['object_type' => 'option', 'key' => $key('corrupt'), 'value' => 'a:1:{s:3:"bad"']);

            FsTest::assertSame(['default' => 1], Helper::getOption($key('corrupt'), ['default' => 1]), 'corrupt row');
        });

        FsTest::case('get_option_keeps_array_callers_on_arrays', function () use ($key) {
            Helper::updateOption($key('string_for_array'), '');

            FsTest::assertSame([], Helper::getOption($key('string_for_array'), []), 'stored "" with an array default');
        });
    } finally {
        Meta::where('object_type', 'option')->where('key', 'LIKE', $prefix . '%')->delete();
    }
};
