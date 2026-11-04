<?php

namespace FluentSupport\App\Http\Controllers;

use FluentSupport\App\Models\Attachment;
use FluentSupport\App\Models\Ticket;
use FluentSupport\App\Modules\PermissionManager;
use FluentSupport\App\Modules\CustomerPortal\PortalService;
use FluentSupport\App\Services\EmailNotification\Settings;
use FluentSupport\App\Services\Helper;
use FluentSupport\App\Services\Includes\FileSystem;
use FluentSupport\Framework\Http\Request\Request;
use FluentSupport\App\Services\Includes\UploadService;
use FluentSupport\App\Services\Storage\StorageManager;

/**
 * UploaderController class is responsible for uploading file
 * @package FluentSupport\App\Http\Controllers
 *
 * @version 1.0.0
 */
class UploaderController extends Controller
{
    /**
     * Uploads sent with on_behalf belong to that customer, and their ticket_id is the
     * public ticket number. Remote portals check for this constant to know which
     * ticket reference to send. Don't remove it.
     */
    const ON_BEHALF_UPLOADS = true;

    /**
     * uploadTicketFiles method will upload all the attached file in a ticket
     * @param Request $request
     * @return array[]
     * @throws \FluentSupport\Framework\Validator\ValidationException
     */
    public function uploadTicketFiles(Request $request)
    {
        $settings = (new Settings())->globalBusinessSettings();
        $maxFileSize = floatval($settings['max_file_size']);
        $maxFileUpload = intval($settings['max_file_upload']);
        $mimeHeadings = Helper::getAcceptedMimeHeadings();
        $maxSizeBytes = $maxFileSize * 1024;
        $imageType = $request->type ? $request->type : null;

        $files = $request->files();

        if ($partsError = $this->rejectUnexpectedFileParts($files)) {
            return $partsError;
        }

        $onBehalf = PortalService::sanitizeOnBehalf($request->get('on_behalf', []));

        if ($onBehalf) {
            try {
                list($ticketId, $person) = $this->resolveOnBehalfUploader($onBehalf, $request);
            } catch (\Exception $e) {
                return $this->sendError([
                    'message' => Helper::getSafeErrorMessage($e),
                ]);
            }
        } else {
            $ticketId = $this->resolveTicketId($request);
            $person = $this->resolvePerson($ticketId, $request);
        }

        if ($permissionError = $this->checkPermissionToUploadFile($person)) {
            return $permissionError;
        }

        if ($accessError = $this->checkTicketAccess($ticketId)) {
            return $accessError;
        }

        if ($quotaError = $this->checkAttachmentQuota($files, $person, $ticketId, $maxFileUpload)) {
            return $quotaError;
        }

        $this->validateUploadedFiles($files, $maxSizeBytes, $mimeHeadings, $maxFileSize);

        try {
            $uploadedFiles = UploadService::handleTempFileUpload($files);
        } catch (\Exception $e) {
            return $this->sendError([
                'message' => Helper::getSafeErrorMessage($e),
            ]);
        }

        if (is_wp_error($uploadedFiles)) {
            return $this->sendError([
                'message' => $uploadedFiles->get_error_message(),
            ]);
        }

        $attachmentHashes = $this->createAttachmentRecords($uploadedFiles, $ticketId, $person, $imageType);

        return [
            'attachments' => $attachmentHashes,
        ];
    }

    /**
     * Only the "file" multipart part is validated and processed downstream
     * (UploadService/FileSystem::put() loops every top-level part it is given), so
     * any other part name must be rejected here rather than silently passed through.
     */
    private function rejectUnexpectedFileParts($files)
    {
        $files = (array) $files;
        $unexpectedKeys = array_diff(array_keys($files), ['file']);

        if ($unexpectedKeys || empty($files['file'])) {
            return $this->sendError([
                'message' => __('Invalid file upload request.', 'fluent-support'),
            ]);
        }

        return null;
    }

    /**
     * resolveTicketId() passes an agent's ticket_id through unchecked, so authorize it
     * before anything is written. No ticket id is legitimate — the Add Ticket form
     * uploads before the ticket exists.
     */
    private function checkTicketAccess($ticketId)
    {
        if (!$ticketId || !Helper::getCurrentAgent()) {
            return null;
        }

        $ticket = Ticket::find($ticketId);

        if (!$ticket || !PermissionManager::canAccessTicket($ticket)) {
            return $this->sendError([
                'message' => __('You do not have permission to upload a file to this ticket', 'fluent-support'),
            ], 403);
        }

        return null;
    }

    private function checkAttachmentQuota($files, $person, $ticketId, $maxFileUpload)
    {
        if ($maxFileUpload <= 0) {
            return null;
        }

        $newFiles = isset($files['file']) ? $files['file'] : null;
        $newFilesCount = is_array($newFiles) ? count($newFiles) : 1;

        $existingCount = Attachment::where('person_id', $person->id)
            ->where('ticket_id', $ticketId)
            ->where('status', 'in-active')
            ->count();

        if (($existingCount + $newFilesCount) > $maxFileUpload) {
            return $this->sendError([
                // translators: %d is the maximum number of files allowed per ticket
                'message' => sprintf(__('You can upload a maximum of %d files.', 'fluent-support'), $maxFileUpload),
            ]);
        }

        return null;
    }

    private function validateUploadedFiles($files, $maxSizeBytes, $mimeHeadings, $maxFileSize)
    {
        $validationRules = [
            'file' => 'max:' . $maxSizeBytes . '|mimetypes:' . implode(',', Helper::ticketAcceptedFileMiles()),
        ];

        $validationMessages = [
            // translators: %s is a comma-separated list of allowed file types (e.g., "jpg, png, pdf")
            'file.mimetypes' => sprintf(__('Only %s files are allowed.', 'fluent-support'), implode(', ', $mimeHeadings)),
            // translators: %.01f is the maximum file size in megabytes
            'file.max'       => sprintf(__('The file cannot be more than %.01fMB. Please upload somewhere like Dropbox/Google Drive and paste the link in the response', 'fluent-support'), $maxFileSize),
        ];

        // The mimetypes rule reads the file's type through wp_check_filetype_and_ext(),
        // which checks core's list, so the plugin's types (.md, .json) must apply here too.
        add_filter('upload_mimes', [FileSystem::class, 'allowTicketMimes']);
        try {
            $this->validate($files, $validationRules, $validationMessages);
        } finally {
            remove_filter('upload_mimes', [FileSystem::class, 'allowTicketMimes']);
        }
    }

    private function resolveTicketId($request)
    {
        $ticketId = $request->getSafe('ticket_id', 'intval');

        if ($ticketId == 'undefined' || !$ticketId) {
            return null;
        }

        if (Helper::getCurrentAgent()) {
            return $ticketId;
        }

        $ticket = Ticket::wherePublicIdentifier($ticketId)->first();

        return $ticket ? $ticket->id : null;
    }

    /**
     * A remote portal (PortalPolicy has allowed on_behalf) uploads as the customer it
     * acts for, so the file belongs to that customer and counts toward their quota only.
     * ticket_id is the public ticket number, as on every other portal endpoint.
     *
     * @return array [ticket id or null, customer]
     * @throws \Exception
     */
    private function resolveOnBehalfUploader($onBehalf, Request $request)
    {
        $identifier = $request->getSafe('ticket_id', 'intval');

        if (!$identifier) {
            return [null, PortalService::findCustomer($onBehalf, $request->getIp(), true)];
        }

        $customer = PortalService::findCustomer($onBehalf, $request->getIp());
        $ticket = $customer ? Ticket::where('customer_id', $customer->id)->wherePublicIdentifier($identifier)->first() : null;

        if (!$ticket) {
            throw PortalService::notFound();
        }

        // The portal's own check, which also holds an agent to their mailboxes.
        PortalService::assertCanAccess($customer, $ticket, 'response', true);

        return [$ticket->id, $customer];
    }

    private function resolvePerson($ticketId, Request $request)
    {
        $agent = Helper::getCurrentAgent();
        if ($agent) {
            return $agent;
        }

        if ($ticketId && Helper::isPublicSignedTicketEnabled()) {
            $intendedTicketHash = $request->getSafe('intended_ticket_hash', 'sanitize_text_field');
            if ($intendedTicketHash && $intendedTicketHash != 'undefined') {
                $ticket = Ticket::with(['customer'])
                    ->where('hash', $intendedTicketHash)
                    ->wherePublicIdentifier($ticketId)
                    ->first();

                if ($ticket && $ticket->customer) {
                    return $ticket->customer;
                }
            }
        }

        return Helper::getCurrentPerson();
    }

    private function checkPermissionToUploadFile($person)
    {
        if (!$person) {
            return $this->sendError([
                'message' => __('You do not have permission to upload a file', 'fluent-support'),
            ]);
        }

        if ($person->person_type === 'customer') {
            $disabledFields = apply_filters('fluent_support/disabled_ticket_fields', []);
            if (in_array('file_upload', $disabledFields)) {
                return $this->sendError([
                    'message' => __('You do not have permission to upload a file', 'fluent-support'),
                ]);
            }
        }
    }

    private function createAttachmentRecords($uploadedFiles, $ticketId, $person, $imageType)
    {
        $attachments = [];
        $directPasteUrl = null;

        foreach ($uploadedFiles as $file) {
            if (empty($file['file_path'])) continue;

            $fileData = [
                'ticket_id' => intval($ticketId) ?: NULL,
                'person_id' => intval($person->id),
                'file_type' => $file['type'],
                'file_path' => $file['file_path'],
                'full_url'  => esc_url($file['url']),
                'title'     => sanitize_file_name($file['name']),
                'file_size' => (int) @filesize($file['file_path']),
                'driver'    => 'local',
                'status'    => 'in-active',
                'settings'  => [
                    'local_temp_path' => $file['file_path'],
                ]
            ];

            try {
                $attachment = Attachment::create($fileData);
                $attachments[] = $attachment->file_hash;

                if ($imageType == 'direct_paste') {
                    $directPasteUrl = $attachment->secureUrl;
                }

                do_action('fluent_support/attachment_uploaded_as_temp', $attachment, $ticketId);
                StorageManager::afterTempUpload($attachment, $ticketId);
            } catch (\Exception $exception) {
                continue;
            }
        }

        return $imageType == 'direct_paste' ? $directPasteUrl : $attachments;
    }

}
