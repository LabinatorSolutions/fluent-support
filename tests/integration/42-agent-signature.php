<?php
/**
 * Agent signatures come only from the {{agent.signature}} smartcode in an inbox
 * email template. Replies never carry the signature themselves, so a template
 * that signs off on its own ("Regards, {{agent.full_name}}") is respected and
 * nothing is doubled. A disabled signature makes the smartcode empty.
 */

use FluentSupport\App\Hooks\Handlers\EmailNotificationHandler;
use FluentSupport\App\Models\Conversation;
use FluentSupport\App\Models\Meta;
use FluentSupport\App\Models\MailBox;
use FluentSupport\App\Services\Tickets\ResponseService;
use FluentSupport\App\Services\Tickets\TicketService;
use FluentSupport\App\Services\Helper;

return function () {

    $signature = '<p>{{agent.first_name}}, Support Lead</p>';

    $withSignature = function ($agent, $enabled = 'yes') use ($signature) {
        $agent->updateMeta('agent_signature_enabled', $enabled);
        $agent->updateMeta('agent_signature', $signature);
    };

    $replyEmail = function ($box, $agent) {
        $ticket = FsFactory::ticket(['mailbox_id' => $box->id]);
        FsTest::interceptMail();

        (new EmailNotificationHandler())->agentReplied(
            FsFactory::conversation($ticket, ['person_id' => $agent->id, 'content' => '<p>The fix is live. ' . FsFactory::marker() . '</p>']),
            $ticket,
            $agent
        );

        $mails = FsTest::sentMails();
        return $mails ? (string) $mails[0]['message'] : null;
    };

    FsTest::case('replies_no_longer_carry_the_signature', function () use ($withSignature) {
        $agent = FsFactory::agent();
        $withSignature($agent);
        $ticket = FsFactory::ticket();
        FsTest::interceptMail();

        $result = (new ResponseService())->createResponse(['content' => '<p>Answer ' . FsFactory::marker() . '</p>'], $agent, $ticket);

        FsTest::assert(strpos((string) Conversation::find($result['response']->id)->content, 'Support Lead') === false,
            'the stored reply is exactly what the agent wrote');
    });

    FsTest::case('agent_signature_smartcode_fills_in_or_stays_empty', function () use ($withSignature) {
        $agent = FsFactory::agent();
        $parse = function () use ($agent) {
            return apply_filters('fluent_support/parse_smartcode_data', 'X{{agent.signature}}Y', ['agent' => $agent]);
        };

        $withSignature($agent);
        FsTest::assertSame('X<p>' . $agent->first_name . ', Support Lead</p>Y', $parse(), 'enabled: signature with its own smartcodes filled');

        $withSignature($agent, 'no');
        FsTest::assertSame('XY', $parse(), 'disabled: empty, no fallback');

        // Customer-replied-to-admin on an unassigned ticket, closed by a workflow:
        // the email has no agent, and the placeholder must not leak as text.
        foreach ([['agent' => null], []] as $noAgent) {
            FsTest::assertSame('XY', apply_filters('fluent_support/parse_smartcode_data', 'X{{agent.signature}}Y', $noAgent),
                'no agent: empty, not the literal smartcode (data: ' . wp_json_encode($noAgent) . ')');
        }
        FsTest::assertSame('X{{agent.first_name}}Y', apply_filters('fluent_support/parse_smartcode_data', 'X{{agent.first_name}}Y', ['agent' => null]),
            'other agent smartcodes keep their existing no-agent behaviour');
    });

    FsTest::case('default_email_template_adds_the_signature_once', function () use ($withSignature, $replyEmail) {
        $agent = FsFactory::agent();
        $withSignature($agent);

        $message = $replyEmail(FsFactory::mailbox(['box_type' => 'email']), $agent);

        FsTest::assert($message !== null, 'the reply email was sent');
        FsTest::assertSame(1, substr_count((string) $message, 'Support Lead'), 'signature appears exactly once');
        FsTest::assert(stripos((string) $message, 'Regards') === false, 'no extra closing');
    });

    FsTest::case('a_saved_template_is_respected_as_written', function () use ($withSignature, $replyEmail) {
        $agent = FsFactory::agent();
        $withSignature($agent);
        $box = FsFactory::mailbox(['box_type' => 'email']);
        $box->saveMeta('_email_ticket_replied_by_agent_email_to_customer', [
            'email_subject' => 'Re: {{ticket.title}}',
            'email_body'    => '{{response.full_content}}<p>Regards,<br />{{agent.full_name}}</p>',
            'status'        => 'yes',
        ]);

        try {
            $message = $replyEmail(MailBox::find($box->id), $agent);

            FsTest::assert(strpos((string) $message, 'Regards,') !== false, 'the saved closing is sent');
            FsTest::assert(strpos((string) $message, 'Support Lead') === false, 'no signature the template did not ask for');
        } finally {
            Meta::where('object_type', MailBox::class)->where('object_id', $box->id)->delete();
        }
    });

    FsTest::case('approved_draft_is_emailed_as_its_author_not_the_approver', function () {
        $author = FsFactory::agent();
        $approver = FsFactory::agent();
        $author->updateMeta('agent_signature_enabled', 'yes');
        $author->updateMeta('agent_signature', '<p>Signed by the author</p>');
        $approver->updateMeta('agent_signature_enabled', 'yes');
        $approver->updateMeta('agent_signature', '<p>Signed by the approver</p>');

        $box = FsFactory::mailbox(['box_type' => 'email']);
        $box->settings = array_merge((array) $box->settings, ['from_name_type' => 'agent']);
        $box->save();

        $ticket = FsFactory::ticket(['mailbox_id' => $box->id]);
        $draft = FsFactory::conversation($ticket, [
            'person_id'         => $author->id,
            'conversation_type' => 'draft_response',
            'content'           => '<p>Drafted answer ' . FsFactory::marker() . '</p>',
        ]);
        FsTest::interceptMail();

        (new ResponseService())->publishDraft($ticket, $draft, $approver, $draft->content);

        $mails = FsTest::sentMails();
        FsTest::assert((bool) $mails, 'the published draft was emailed');
        $message = $mails ? (string) $mails[0]['message'] : '';
        $headers = $mails ? implode("\n", (array) $mails[0]['headers']) : '';

        FsTest::assertSame(1, substr_count($message, 'Signed by the author'), "the author's signature, once");
        FsTest::assert(strpos($message, 'Signed by the approver') === false, "not the approver's signature");
        FsTest::assert(strpos($headers, $author->full_name) !== false, "From name is the author's: " . $headers);
        FsTest::assertSame((int) $author->id, (int) Conversation::find($draft->id)->person_id, 'the thread still credits the author');
    });

    FsTest::case('outreach_stores_the_message_and_emails_the_signature', function () use ($withSignature) {
        $agent = Helper::getAgentByUserId();
        $previous = [$agent->getMeta('agent_signature_enabled', ''), $agent->getMeta('agent_signature', '')];
        $withSignature($agent);
        FsTest::interceptMail();

        try {
            $customer = FsFactory::customer();
            $ticket = (new TicketService())->storeTicket([
                'customer_id'     => $customer->id,
                'title'           => 'Outreach ' . FsFactory::marker(),
                'content'         => '<p>Checking in. ' . FsFactory::marker() . '</p>',
                'mailbox_id'      => FsFactory::mailbox()->id,
                'agent_initiated' => 'yes',
            ], $customer);
            FsFactory::adoptRestTicket($ticket);

            $first = Conversation::where('ticket_id', $ticket->id)->where('conversation_type', 'response')->first();
            FsTest::assert($first && strpos((string) $first->content, 'Support Lead') === false, 'stored message has no signature');

            $toCustomer = array_values(array_filter(FsTest::sentMails(), function ($mail) use ($customer) {
                return in_array($customer->email, (array) ($mail['to'] ?? []), true) || ($mail['to'] ?? '') === $customer->email;
            }));
            FsTest::assertSame(1, $toCustomer ? substr_count((string) $toCustomer[0]['message'], 'Support Lead') : 0, 'outreach email has the signature once');
        } finally {
            $agent->updateMeta('agent_signature_enabled', $previous[0]);
            $agent->updateMeta('agent_signature', $previous[1]);
        }
    });
};
