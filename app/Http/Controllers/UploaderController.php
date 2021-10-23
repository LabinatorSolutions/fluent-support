<?php

namespace FluentSupport\App\Http\Controllers;

use FluentSupport\App\Models\Attachment;
use FluentSupport\App\Models\Ticket;
use FluentSupport\App\Services\Helper;
use FluentSupport\App\Services\Includes\FileSystem;
use FluentSupport\Framework\Request\Request;

class UploaderController extends Controller
{
    public function uploadTicketFiles(Request $request)
    {
        $files = $this->validate($this->request->files(), [
            'file' => 'max:2000|mimetypes:' . implode(',', Helper::ticketAcceptedFileMiles())
        ], [
            'file.mimetypes' => __('Only images, documents or zip files are allowed. Please upload as ZIP File', 'fluent-support'),
            'file.max' => __('The file can not be more than 2MB. Please upload somewhere like dropbox/google drive and paste the link in the response', 'fluent-support')
        ]);


        $ticketId = $request->get('ticket_id');

        if ($ticketId == 'undefined') {
            $ticketId = NULL;
        }

        if($ticketId && $request->get('intended_ticket_hash') && Helper::isPublicSignedTicketEnabled()) {
            $ticket = Ticket::with(['customer'])->findOrFail($ticketId);
            $person = $ticket->customer;
        } else {
            $person = Helper::getAgentByUserId(get_current_user_id());
        }

        $uploadedFiles = FileSystem::setSubDir('ticket_'.$ticketId)->put($files);

        $attachments = [];
        foreach ($uploadedFiles as $file) {

            $fileData = [
                'ticket_id' => $ticketId,
                'person_id' => $person->id,
                'file_type' => $file['type'],
                'file_path' => $file['file_path'],
                'full_url' => $file['url'],
                'title' => $file['name'],
                'driver' => 'local',
                'status' => 'in-active'
            ];

            $attachment = Attachment::create($fileData);
            $attachments[] = $attachment->file_hash;
        }

        return [
            'attachments' => $attachments
        ];

    }
}
