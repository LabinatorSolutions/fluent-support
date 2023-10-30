<?php
namespace FluentSupport\App\Services\Includes;

use FluentSupport\App\Models\Meta;

class UploadService
{
    protected $uploadedFiles;
    private $isLocalUploadDisable;
    private $integratedDrivers;


    public function __construct()
    {
        $this->isLocalUploadDisable = static::isLocalUploadDisable() ?: false;
        $this->integratedDrivers = static::isIntegratedDriversEnable() ?: false;

    }

    /**
     * Upload files to integrated cloud storage
     * @param $file
     * @param $ticketId
     * @return array
     */
    public function _uploadToCloud($files, $ticketId)
    {
        $enabledDrivers = \FluentSupportPro\App\Services\FileUploadIntegration\Drivers::enabledDrivers();

        if (!is_array($enabledDrivers)) {
            $enabledDrivers = [$enabledDrivers];
        }

        $results = [];

        foreach ($enabledDrivers as $driver) {
            $driverClass = \FluentSupportPro\App\Services\FileUploadIntegration\Drivers::getDriverInstance($driver['key']);
            $results[] = $driverClass->upload($files, $ticketId);
        }

        return $results;
    }

    /**
     *
     *
     * @param array $file file data from request
     * @param int $ticketId ticket id
     * @return array $uploadedFiles uploaded file data
     */
    public function _handleFileUpload($file, $ticketId)
    {
        if (defined('FLUENTSUPPORTPRO') && $this->isLocalUploadDisable && $this->integratedDrivers) {
            $this->uploadedFiles = $this->_uploadToCloud($file, $ticketId);
        } elseif (defined('FLUENTSUPPORTPRO') && !$this->isLocalUploadDisable && $this->integratedDrivers) {
            $this->uploadedFiles = FileSystem::setSubDir('ticket_' . $ticketId)->put($file);
            $this->_uploadToCloud($file, $ticketId);
        } else {
            $this->uploadedFiles = FileSystem::setSubDir('ticket_' . $ticketId)->put($file);
        }

        return $this->uploadedFiles;
    }

    /**
     * Handle email attachments
     * @param array $file file data from request
     * @param int $ticketId ticket id
     * @param array $acceptedMimes accepted mime types for file upload
     * @return array $uploadedFiles uploaded file data
     */
    public function _handleEmailAttachments($file, $ticketId, $acceptedMimes=[])
    {
        $fileContent =  $this->requestContent($file['url'], $acceptedMimes);
        $uploadToLocalDriver = wp_upload_bits($file['filename'], null, $fileContent);

        // this is the file data from email attachment and required for upload to cloud
        $fileData = [
            'name' => $file['filename'],
            'type' =>   $uploadToLocalDriver['type'],
            'tmp_name' => $uploadToLocalDriver['file'],
            'from' => 'email'
        ];

        if($this->isLocalUploadDisable && $this->integratedDrivers) {
            $uplodedToCloud = $this->_uploadToCloud($fileData, $ticketId);
            $this->uploadedFiles = $uplodedToCloud[0];
        } elseif(!$this->isLocalUploadDisable && $this->integratedDrivers) {
            $this->uploadedFiles = $uploadToLocalDriver;
            $this->_uploadToCloud($fileData, $ticketId);
        } else {
            $this->uploadedFiles = $uploadToLocalDriver;
        }

        if($this->isLocalUploadDisable) {
            wp_delete_file($uploadToLocalDriver['file']);
        }

        return $this->uploadedFiles;
    }

    /**
     * Get file content from url
     * @param array $attachment file data from request
     * @param array $acceptedMimes accepted mime types for file upload
     * @return array $uploadedFiles uploaded file data
     */
    public function requestContent($contentUrl, $acceptedMimes= [])
    {
        $response = wp_remote_request($contentUrl, [
            'sslverify' => false,
            'method' => 'GET'
        ]);

        if (is_wp_error($response)) {
            return;
        }

        $contentType = wp_remote_retrieve_header($response, 'content-type');

        if ($acceptedMimes && !in_array($contentType, $acceptedMimes)) {
            return;
        }

        if (wp_remote_retrieve_response_code($response) >= 300) {
            return;
        }

        $responseBody = wp_remote_retrieve_body($response);

        return $responseBody;
    }


    // Verify if local upload is enable or not
    public static function isLocalUploadDisable()
    {
        return Meta::where('key', 'disable_local_upload')
            ->where('object_type', 'enabled_upload_drivers')
            ->exists();
    }

    // Verify if there's any integrated drivers enable
    public static function isIntegratedDriversEnable()
    {
        return Meta::where('key', '!=' ,'disable_local_upload')
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
