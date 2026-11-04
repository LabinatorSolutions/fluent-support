<?php
/**
 * Inbox From name: email an agent writes can carry the agent's name or a custom
 * template; automatic email always keeps the inbox name; the address never changes.
 */

use FluentSupport\App\Hooks\Handlers\EmailNotificationHandler;
use FluentSupport\App\Models\MailBox;

return function () {

    $boxWith = function (array $settings) {
        $box = FsFactory::mailbox(['name' => 'Acme Support ' . FsFactory::marker()]);
        $box->settings = array_merge((array) $box->settings, $settings);
        $box->save();

        return MailBox::find($box->id);
    };

    $fromHeader = function (array $headers) {
        foreach ($headers as $header) {
            if (stripos((string) $header, 'From:') === 0) {
                return trim(substr($header, 5));
            }
        }
        return null;
    };

    FsTest::case('from_name_defaults_to_the_inbox_name', function () use ($boxWith) {
        $box = $boxWith([]);
        $agent = FsFactory::agent();

        FsTest::assertSame($box->name, $box->getFromName($agent), 'no setting = inbox name');
    });

    FsTest::case('from_name_can_be_the_replying_agent', function () use ($boxWith, $fromHeader) {
        $box = $boxWith(['from_name_type' => 'agent']);
        $agent = FsFactory::agent();

        FsTest::assertSame($agent->full_name, $box->getFromName($agent), 'agent name');
        FsTest::assertSame($box->name, $box->getFromName(null), 'automatic email keeps the inbox name');
        FsTest::assertSame($agent->full_name . ' <' . $box->email . '>', $fromHeader($box->getMailerHeader($agent)), 'address unchanged');
    });

    FsTest::case('from_name_custom_template_is_parsed_and_cleaned', function () use ($boxWith) {
        $agent = FsFactory::agent();

        $custom = $boxWith(['from_name_type' => 'custom', 'from_name_template' => '{{agent.first_name}} from {{business.name}}']);
        FsTest::assertSame($agent->first_name . ' from ' . $custom->name, $custom->getFromName($agent), 'template');

        $hostile = $boxWith(['from_name_type' => 'custom', 'from_name_template' => "Evil \"x\" <a@b>\r\nBcc: c@d {{unknown.code}}"]);
        $name = $hostile->getFromName($agent);
        FsTest::assert(!preg_match('/[\r\n"<>]|{{/', $name), 'no header breaks, quotes, brackets or raw codes, got: ' . $name);

        $empty = $boxWith(['from_name_type' => 'custom', 'from_name_template' => '{{unknown.code}}']);
        FsTest::assertSame($empty->name, $empty->getFromName($agent), 'empty result falls back to the inbox name');
    });

    FsTest::case('agent_name_cannot_add_a_reply_to_address', function () use ($boxWith) {
        $box = $boxWith(['from_name_type' => 'agent']);
        $agent = FsFactory::agent();
        $agent->first_name = 'x,attacker@example.invalid,y';
        $agent->last_name = 'Agent';

        $replyTo = '';
        foreach ($box->getMailerHeader($agent) as $header) {
            if (stripos((string) $header, 'Reply-To:') === 0) {
                $replyTo = trim(substr($header, 9));
            }
        }

        // wp_mail() explodes Reply-To on commas, then reads "Name <email>" from each piece.
        $addresses = [];
        foreach (explode(',', $replyTo) as $piece) {
            if (preg_match('/(.*)<(.+)>/', $piece, $matches)) {
                $piece = $matches[2];
            }
            if (is_email(trim($piece))) {
                $addresses[] = trim($piece);
            }
        }

        FsTest::assertSame([$box->email], $addresses, 'the inbox is the only Reply-To address, header was: ' . $replyTo);
    });

    FsTest::case('agent_reply_email_uses_the_from_name_setting', function () use ($boxWith, $fromHeader) {
        $box = $boxWith(['from_name_type' => 'agent']);
        $agent = FsFactory::agent();
        $ticket = FsFactory::ticket(['mailbox_id' => $box->id]);
        FsTest::interceptMail();

        (new EmailNotificationHandler())->agentReplied(
            FsFactory::conversation($ticket, ['person_id' => $agent->id, 'content' => '<p>Reply ' . FsFactory::marker() . '</p>']),
            $ticket,
            $agent
        );

        $mails = FsTest::sentMails();
        FsTest::assert(count($mails) > 0, 'the reply email was sent');
        FsTest::assertSame($agent->full_name . ' <' . $box->email . '>', $mails ? $fromHeader((array) $mails[0]['headers']) : null, 'From header');
    });
};
