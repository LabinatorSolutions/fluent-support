<?php
/**
 * Regression test for the email 2FA login record storing the browser
 * user-agent string in its ip_address column instead of the client's IP.
 *
 * The 2FA code issuer (now AuthService::issueCode()) used to write $_SERVER['HTTP_USER_AGENT']
 * into the 'ip_address' field of the issued login_hash record, so the
 * stored "IP" was actually the user agent string.
 *
 * @package Fluent_Support
 */

use FluentSupport\App\Modules\Auth\AuthService;
use FluentSupport\App\Models\Meta;
use FluentSupport\App\Services\Helper;

class TwoFaLoginIpAddressTest extends WP_UnitTestCase
{
    public function tear_down()
    {
        Meta::where('object_type', 'fs_2fa')->delete();

        parent::tear_down();
    }

    public function test_issued_2fa_record_stores_the_client_ip_not_the_user_agent()
    {
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (RegressionTest-UA) Test/1.0';

        $userId = self::factory()->user->create([
            'role'       => 'subscriber',
            'user_email' => 'two-fa-ip-' . wp_generate_password(8, false) . '@example.test',
        ]);
        $user = get_user_by('id', $userId);

        $issued = AuthService::issueCode(AuthService::LOGIN_CODE, ['user_id' => $user->ID, 'user_email' => $user->user_email]);

        $this->assertNotFalse($issued, 'Issuing a 2FA code must succeed.');

        $meta = Meta::where('key', $issued[0])->first();
        $this->assertNotNull($meta, 'The issued 2FA code must be persisted in fs_meta.');

        $record = Helper::safeUnserialize($meta->value);

        $this->assertSame(
            Helper::getIp(),
            $record['ip_address'],
            'ip_address must hold the client IP that Helper::getIp() resolves.'
        );

        $this->assertNotSame(
            $_SERVER['HTTP_USER_AGENT'],
            $record['ip_address'],
            'ip_address must never hold the browser user-agent string.'
        );
    }
}
