<?php

namespace FluentSupport\App\Hooks\Handlers;

use FluentSupport\App\Models\Conversation;
use FluentSupport\App\Services\Includes\UploadService;
class FileUploadHandler
{
    public function init()
    {
        add_action('fluent_support/ticket_created', function ($ticket, $customer) {
            if ( $ticket->attachments->count() > 0 ) {
                $files = $ticket->attachments;
                $uploadInfo = UploadService::copyFromTempToOriginal($files, $ticket->id);

                if( $uploadInfo->hasError ){
                    do_action('fluent_support/file_upload_failed', $uploadInfo, $customer);
                }
            }
        }, 20, 2);

        add_action('fluent_support/file_upload_failed', function ($uploadInfo, $customer) {
            $internalNote = $this->getFileUploadErrorNoteMessage($uploadInfo->driver);
            Conversation::create([
                'ticket_id'         => $uploadInfo->ticket_id,
                'person_id'         => $customer->id,
                'conversation_type' => 'internal_error',
                'content'           => $internalNote
            ]);

        }, 20, 2);
    }

    public static function getFileUploadErrorNoteMessage($driver){
        $errorMessages = [
            'google_drive_settings' => 'Google Drive',
            'dropbox_settings' => 'Dropbox',
            'local_upload' => 'local',
        ];

        $noteMessage = 'File Upload failed to' . $errorMessages[$driver]. ', Please check your';

        if ('local_upload' == $driver) {
            $noteMessage .= ' folder permission';
        } else {
            $noteMessage .= ' ' . $errorMessages[$driver]. ' settings';
        }

        return $noteMessage;
    }
}
