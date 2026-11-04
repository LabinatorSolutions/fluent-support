<?php

namespace FluentSupport\App\Services\Includes;

use FluentSupport\App\Models\Meta;
use FluentSupport\Framework\Support\Arr;

class UploadService
{

    public static function copyFileTicketFolder($tempPath, $ticketId)
    {
        if (!file_exists($tempPath)) {
            return false;
        }

        return FileSystem::setSubDir('ticket_' . $ticketId)->copy($tempPath);
    }

    public static function handleTempFileUpload($file)
    {
        self::ensureProtectedUploadDir();


        $uploadInfo = FileSystem::setSubDir('temp_files')->put($file);
        if (!empty($uploadInfo) && is_array($uploadInfo)) {
            return $uploadInfo;
        }

        return new \WP_Error('file_upload_error', __('File upload failed', 'fluent-support'));
    }

    /**
     * Create the plugin's upload folder with a blank index and a deny-all .htaccess.
     */
    public static function ensureProtectedUploadDir()
    {
        $uploadDir = wp_upload_dir();
        $fluentSupportFolder = $uploadDir['basedir'] . DIRECTORY_SEPARATOR . FLUENT_SUPPORT_UPLOAD_DIR;
        if (!file_exists($fluentSupportFolder . DIRECTORY_SEPARATOR . 'index.html')) {
            if (!is_dir($fluentSupportFolder)) {
                @mkdir($fluentSupportFolder, 0755);
            }

            @file_put_contents($fluentSupportFolder . DIRECTORY_SEPARATOR . 'index.html', '');
            @file_put_contents($fluentSupportFolder . DIRECTORY_SEPARATOR . '.htaccess', 'deny from all');
        }
    }

    private function handleUploadToLocal($ticketId, $file)
    {
        $uploadInfo = FileSystem::setSubDir('ticket_' . $ticketId)->put($file);
        if (!empty($uploadInfo) && is_array($uploadInfo)) {
            return $uploadInfo;
        } else {
            return [
                'file_path' => '',
                'url'       => '',
                'name'      => '',
                'type'      => '',
                'size'      => '',
            ];
        }
    }

    /**
     * @param array $file file data from request
     * @param int $ticketId ticket id
     * @return array $uploadedFiles uploaded file data
     */
    public function _handleFileUpload($file, $ticketId)
    {
        $this->uploadedFiles = $this->handleUploadToLocal($ticketId, $file);

        return $this->uploadedFiles;
    }

    /**
     * Download an email attachment into the ticket folder (or temp_files when there's no ticket).
     *
     * @param array $file file data from request
     * @param int $ticketId ticket id
     * @param array $acceptedMimes accepted mime types for file upload
     * @return array|null $uploadedFiles uploaded file data
     */
    public function _handleEmailAttachments($file, $ticketId = null, $acceptedMimes = [])
    {
        $fileContent = $this->requestContent($file['url'], $acceptedMimes, $file['filename']);

        if (!$fileContent) {
            return null;
        }

        $targetFolder = 'temp_files';

        if ($ticketId) {
            $targetFolder = 'ticket_' . $ticketId;
        }

        $fileData = FileSystem::setSubDir($targetFolder)->putAsContent($file['filename'], $fileContent);

        if (!$fileData || empty($fileData['file'])) {
            return null;
        }

        $contentType = $fileData['type'];
        if (!$contentType) {
            $contentType = $file['contentType'];
        }

        // Shape expected by the cloud upload drivers.
        return [
            'name' => $file['filename'],
            'type' => $contentType,
            'file' => $fileData['file'],
            'url'  => $fileData['url'],
        ];
    }

    /**
     * @param string $contentUrl
     * @param array $acceptedMimes accepted mime types for file upload
     * @param string $filename the attachment's file name, to judge a text/plain file by its extension
     * @return string|null Response body, or null on error, rejected type or non-2xx status.
     */
    public function requestContent($contentUrl, $acceptedMimes = [], $filename = '')
    {
        $response = wp_safe_remote_request($contentUrl, [
            'method'  => 'GET',
            'timeout' => 30
        ]);

        if (is_wp_error($response)) {
            return;
        }

        // A text type usually arrives with a charset (`text/markdown; charset=utf-8`).
        $contentType = strtolower(trim(explode(';', (string) wp_remote_retrieve_header($response, 'content-type'))[0]));

        // Mail clients often declare a CSV as text/plain. Judge such a file by its extension;
        // FileSystem::putAsContent() still checks the content against that extension.
        if ($contentType === 'text/plain' && $filename) {
            $contentType = wp_check_filetype($filename, FileSystem::ticketMimeTypes())['type'] ?: $contentType;
        }

        if ($acceptedMimes && !in_array($contentType, $acceptedMimes)) {
            return;
        }

        if (wp_remote_retrieve_response_code($response) >= 300) {
            return;
        }

        return wp_remote_retrieve_body($response);
    }


    public static function isLocalUploadDisable()
    {
        return Meta::where('key', 'disable_local_upload')
            ->where('object_type', 'enabled_upload_drivers')
            ->exists();
    }

    public static function isIntegratedDriversEnable()
    {
        return Meta::where('key', '!=', 'disable_local_upload')
            ->where('object_type', 'enabled_upload_drivers')
            ->exists();
    }


    public static function __callStatic($method, $params)
    {
        $instance = new static;

        return call_user_func_array([$instance, $method], $params);
    }

    public function __call($method, $params)
    {
        $hiddenMethod = "_" . $method;

        $method = method_exists($this, $hiddenMethod) ? $hiddenMethod : $method;

        return call_user_func_array([$this, $method], $params);
    }
}
