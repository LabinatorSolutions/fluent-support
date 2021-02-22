<?php

namespace FluentSupport\App\Http\Controllers;

use FluentSupport\App\Models\Attachment;
use FluentSupport\App\Services\Helper;
use FluentSupport\App\Services\Includes\FileSystem;
use FluentSupport\Framework\Request\Request;

class UploaderController extends Controller
{
    public function uploadTicketFiles(Request $request)
    {
        $files = $this->validate($this->request->files(), [
            'file' => 'mimetypes:' . implode(',', Helper::ticketAcceptedFileMiles())
        ], ['file.mimetypes' => 'Only image and couments are allowed']);


        $ticketId=  $request->get('ticket_id');

        $person = Helper::getAgentByUserId(get_current_user_id());

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
                'driver' => 'local'
            ];

            $attachment = Attachment::create($fileData);
            $attachments[] = $attachment->file_hash;
        }

        return [
            'attachments' => $attachments
        ];

    }
}
