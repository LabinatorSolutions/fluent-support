<?php
/**
 * The reply box autosaves to POST /tickets/{id}/draft and keeps the returned
 * draftID so it can delete the draft after the reply is sent. A box that was
 * remounted before its first save returned sends an empty draftID; the server
 * must hand back the stored ID so the box can recover it.
 */

use FluentSupport\App\Models\Meta;

return function () {

    $saveDraft = function ($ticket, $content, $draftId) {
        return FsTest::rest('POST', '/tickets/' . $ticket->id . '/draft', [
            'content'           => '<p>' . $content . '</p>',
            'draftID'           => $draftId,
            'conversation_type' => 'response',
        ]);
    };

    $storedDraft = function ($ticket) {
        return Meta::where('object_type', '_fs_auto_draft')
            ->where('object_id', $ticket->id)
            ->first();
    };

    $newTicket = function () {
        $customer = FsFactory::customer();
        return FsFactory::ticket(['customer_id' => $customer->id, 'status' => 'active']);
    };

    FsTest::case('first save creates the draft and returns its ID', function () use ($saveDraft, $storedDraft, $newTicket) {
        $ticket = $newTicket();

        $result = $saveDraft($ticket, 'First draft ' . FsFactory::marker(), '');

        FsTest::assertHealthy($result, 'first save');
        $draft = $storedDraft($ticket);
        FsTest::assert((bool) $draft, 'draft row created');
        FsTest::assertSame((string) $draft->id, (string) ($result['data']['draftID'] ?? ''), 'returned ID is the stored ID');
    });

    FsTest::case('a save with an empty draftID returns the stored draft ID', function () use ($saveDraft, $storedDraft, $newTicket) {
        $ticket = $newTicket();
        $saveDraft($ticket, 'First draft', '');
        $draft = $storedDraft($ticket);

        // The remounted reply box never received the first save's response.
        $result = $saveDraft($ticket, 'Edited after remount', '');

        FsTest::assertHealthy($result, 'second save');
        FsTest::assertSame((string) $draft->id, (string) ($result['data']['draftID'] ?? ''), 'stored ID handed back');
        FsTest::assertSame(1, Meta::where('object_type', '_fs_auto_draft')->where('object_id', $ticket->id)->count(), 'still one draft row');
        FsTest::assert(strpos((string) $storedDraft($ticket)->value, 'Edited after remount') !== false, 'content updated');
    });

    FsTest::case('a save with the known draftID updates the same draft', function () use ($saveDraft, $storedDraft, $newTicket) {
        $ticket = $newTicket();
        $saveDraft($ticket, 'First draft', '');
        $draft = $storedDraft($ticket);

        $result = $saveDraft($ticket, 'Second edit', (string) $draft->id);

        FsTest::assertHealthy($result, 'update');
        FsTest::assertSame((string) $draft->id, (string) ($result['data']['draftID'] ?? ''), 'same ID returned');
        FsTest::assert(strpos((string) $storedDraft($ticket)->value, 'Second edit') !== false, 'content updated');
    });

    FsTest::case('a save with a stale draftID and no stored draft creates a new draft', function () use ($saveDraft, $storedDraft, $newTicket) {
        $ticketA = $newTicket();
        $ticketB = $newTicket();
        $saveDraft($ticketA, 'Draft on A', '');
        $draftA = $storedDraft($ticketA);

        // A late first-save response from ticket A handed its ID to a new draft on ticket B.
        $result = $saveDraft($ticketB, 'Draft on B', (string) $draftA->id);

        FsTest::assertHealthy($result, 'save on B');
        $draftB = $storedDraft($ticketB);
        FsTest::assert((bool) $draftB, 'draft row created for B');
        FsTest::assertSame((string) $draftB->id, (string) ($result['data']['draftID'] ?? ''), 'B gets its own draft ID');
        FsTest::assert(strpos((string) $storedDraft($ticketA)->value, 'Draft on A') !== false, "A's draft untouched");
    });
};
