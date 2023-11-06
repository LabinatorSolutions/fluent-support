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

        add_action('fluent_support/file_upload_failed_before_store', function ($uploadInfo) {
            $attachments = $uploadInfo->attachments;
            foreach ($attachments as $attachment) {
                isset($error[$attachment->driver]) && ($error[$attachment->driver] = true);
                $attachment->delete();
            }
            $internalNote = $this->getFileUploadErrorNoteMessage();
            Conversation::create([
                'ticket_id'         => $uploadInfo->ticket_id,
                'person_id'         => $uploadInfo->person_id,
                'conversation_type' => 'internal_error',
                'content'           => $internalNote
            ]);

        }, 20);

        add_action('fluent_support/response_added_by_customer', function ($response, $ticket, $person) {
            if ( $response->attachments->count() > 0 ) {
                $files = $response->attachments;
                $uploadInfo = UploadService::copyFromTempToOriginal($files, $ticket->id);

                if( $uploadInfo->hasError ){
                    do_action('fluent_support/file_upload_failed', $uploadInfo, $person);
                }
            }
        }, 20, 3);

        add_action('fluent_support/response_added_by_agent', function ($response, $ticket, $person) {
            if ( $response->attachments->count() > 0 ) {
                $files = $response->attachments;
                $uploadInfo = UploadService::copyFromTempToOriginal($files, $ticket->id);

                if( $uploadInfo->hasError ){
                    do_action('fluent_support/file_upload_failed', $uploadInfo, $person);
                }
            }
        }, 20, 3);

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

    public static function getFileUploadErrorNoteMessage($driver = 'local', $type = 'response'){
        $errorMessages = [
            'google_drive_settings' => 'Google Drive',
            'dropbox_settings' => 'Dropbox',
            'local' => 'Local Server',
        ];

        if( 'response' == $type ) {
            $noteMessage = 'Attached file in this conversation is failed to upload to ' . $errorMessages[$driver]. ', Please check your';
        }else {
            $noteMessage = 'Attached file during creation this ticket is failed to upload to ' . $errorMessages[$driver]. ', Please check your';
        }

        if ('local' == $driver) {
            $noteMessage .= ' folder permission';
        } else {
            $noteMessage .= ' ' . $errorMessages[$driver]. ' settings';
        }

        return $noteMessage;
    }
}
