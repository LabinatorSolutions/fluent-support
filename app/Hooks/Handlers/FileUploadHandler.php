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
                $hasError = false;
                foreach ($files as $file){
                    $uploadInfo = UploadService::copyFromTempToOriginal($file, $ticket->id);
                    $this->updateFileInfo($file, $uploadInfo);
                    $hasError = $uploadInfo['hasError'] ?? false;
                }

                if( $hasError ){
                    $data = (object)[
                        'driver' => $uploadInfo['error_driver'],
                        'ticket_id' => $ticket->id,
                        'person_id' => $customer->id,
                    ];
                    do_action('fluent_support/file_upload_failed', $data, $customer);
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
                $hasError = false;
                foreach ($files as $file){
                    $uploadInfo = UploadService::copyFromTempToOriginal($file, $ticket->id);
                    $this->updateFileInfo($file, $uploadInfo);
                    $hasError = $uploadInfo['hasError'] ?? false;
                }

                if( $hasError ){
                    $data = (object)[
                        'driver' => $uploadInfo['error_driver'],
                        'ticket_id' => $ticket->id,
                        'person_id' => $person->id,
                    ];
                    do_action('fluent_support/file_upload_failed', $data, $person);
                }
            }
        }, 20, 3);

        add_action('fluent_support/response_added_by_agent', function ($response, $ticket, $person) {
            if ( $response->attachments->count() > 0 ) {
                $files = $response->attachments;
                $hasError = false;
                foreach ($files as $file){
                    $uploadInfo = UploadService::copyFromTempToOriginal($file, $ticket->id);
                    $this->updateFileInfo($file, $uploadInfo);
                    $hasError = $uploadInfo['hasError'] ?? false;
                }

                if( $hasError ){
                    $data = (object)[
                        'driver' => $uploadInfo['error_driver'] ?? 'local',
                        'ticket_id' => $ticket->id,
                        'person_id' => $person->id,
                    ];
                    do_action('fluent_support/file_upload_failed', $data, $person);
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

    private function updateFileInfo($file, $uploadInfo)
    {
        $file->file_path = $uploadInfo['file_path'] ?? $uploadInfo['path'] ?? null;
        $file->full_url = $uploadInfo['url'] ?? $uploadInfo['full_url'] ?? null;
        $file->title = $uploadInfo['name'] ?? $file->title;
        $file->driver = $uploadInfo['driver'] ?? 'local';
        $file->status = 'active';
        $file->save();

        return true;
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
            $noteMessage .= ' upload folder permission is writeable or not';
        } else {
            $noteMessage .= ' ' . $errorMessages[$driver]. ' settings';
        }

        return $noteMessage;
    }
}
