<?php

namespace FluentSupport\App\Http\Controllers;

use FluentSupport\App\Models\Attachment;
use FluentSupport\App\Models\Ticket;
use FluentSupport\App\Services\EmailNotification\Settings;
use FluentSupport\App\Services\Helper;
use FluentSupport\App\Services\Includes\FileSystem;
use FluentSupport\Framework\Request\Request;

class UploaderController extends Controller
{
    public function uploadTicketFiles(Request $request)
    {
        $settings = (new Settings())->globalBusinessSettings();
        $maxFileSize = absint($settings['max_file_size']);
        $mimeHeadings = Helper::getAcceptedMimeHeadings();

        $maxSizeBytes = $maxFileSize * 1024;

        $files = $this->validate($this->request->files(), [
            'file' => 'max:' . $maxSizeBytes . '|mimetypes:' . implode(',', Helper::ticketAcceptedFileMiles())
        ], [
            'file.mimetypes' => sprintf(__('Only %s files are allowed.', 'fluent-support'), implode(', ', $mimeHeadings)),
            'file.max'       => sprintf(__('The file can not be more than %dMB. Please upload somewhere like dropbox/google drive and paste the link in the response', 'fluent-support'), $maxFileSize)
        ]);

        $ticketId = $request->get('ticket_id');

        if ($ticketId == 'undefined') {
            $ticketId = NULL;
        }

        if ($ticketId && $request->get('intended_ticket_hash') && Helper::isPublicSignedTicketEnabled()) {
            $ticket = Ticket::with(['customer'])->findOrFail($ticketId);
            $person = $ticket->customer;
        } else {
            $person = Helper::getCurrentPerson();
        }

        if ($person->person_type == 'customer') {
            $disabledFields = apply_filters('fluent_support/disabled_ticket_fields', []);
            if (in_array('file_upload', $disabledFields)) {
                return $this->sendError([
                    'message' => 'You do not have permission to upload a file'
                ]);
            }
        }

        $uploadedFiles = FileSystem::setSubDir('ticket_' . $ticketId)->put($files);

        $attachments = [];
        foreach ($uploadedFiles as $file) {

            $fileData = [
                'ticket_id' => $ticketId,
                'person_id' => $person->id,
                'file_type' => $file['type'],
                'file_path' => $file['file_path'],
                'full_url'  => $file['url'],
                'title'     => $file['name'],
                'driver'    => 'local',
                'status'    => 'in-active'
            ];

            $attachment = Attachment::create($fileData);
            $attachments[] = $attachment->file_hash;
        }

        return [
            'attachments' => $attachments
        ];

    }
}
