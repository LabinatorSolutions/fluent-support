<?php
namespace FluentSupport\App\Services\Includes;

use FluentSupport\App\Models\Meta;
use FluentSupport\App\Services\Helper;

class UploadService
{
    protected $uploadedFiles;
    private $enabledDriver;

    public function __construct()
    {
        $this->enabledDriver = Helper::getEnabledDriver();
    }

    /**
     * Handle file upload
     * @param array $file file data from request
     * @param int $ticketId ticket id
     * @return array $uploadedFiles uploaded file data
     */
    public function _handleFileUpload($file, $ticketId)
    {
        try {
            //Upload file to local
            $this->uploadedFiles = $this->handleUploadToLocal($ticketId, $file);
        }catch (\Exception $e){
            throw new \Exception($e->getMessage());
        }

        //If cloud integration is enabled then upload file to cloud
        if (defined('FLUENTSUPPORTPRO') && $this->enabledDriver != 'local_upload'){
            $cloudFile = $this->_uploadToCloud($file, $ticketId);
            if(!empty($cloudFile['file_path'])){
                $localFile = array_pop($this->uploadedFiles);
                $cloudFile['file_path'] = $localFile['file_path'];
                $this->uploadedFiles[] = $cloudFile;
            }else {
                $this->uploadedFiles[] = $cloudFile;
            }
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

        try {
            //Upload file to local
            $this->uploadedFiles = $this->handleUploadToLocal($ticketId, $fileData);
        }catch (\Exception $e){
            throw new \Exception($e->getMessage());
        }

        //If cloud integration is enabled then upload file to cloud
        if($this->enabledDriver != 'local_upload'){
            $cloudFile = $this->_uploadToCloud($file, $ticketId);
            if(!empty($cloudFile['file_path'])){
                $cloudFile['file_path'] = $this->uploadedFiles['file_path'];
                $this->uploadedFiles = $cloudFile;
            }else {
                $this->uploadedFiles[] = $cloudFile;
            }
        }

        return $this->uploadedFiles;
    }

    /**
     * Handle file upload to local
     * @param int $ticketId ticket id
     * @param array $file file data from request
     */
    private function handleUploadToLocal($ticketId, $file){
        $uploadInfo = FileSystem::setSubDir('ticket_' . $ticketId)->put($file);
        if(!empty($uploadInfo) && is_array($uploadInfo)){
            return $uploadInfo;
        }else {
            throw new \Exception('File upload failed');
        }
    }

    /**
     * Upload files to integrated cloud storage
     * @param $file
     * @param $ticketId
     * @return array
     */
    public function _uploadToCloud($files, $ticketId)
    {
        $driverClass = \FluentSupportPro\App\Services\FileUploadIntegration\Drivers::getDriverInstance($this->enabledDriver);

        try {
            $results = $driverClass->upload($files, $ticketId);
        } catch (\Exception $e) {
            return [
                'file_path' => '',
                'url' => '',
                'name' => '',
                'type' => '',
                'size' => '',
            ];
        }

        return $results;
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

    private static function getEnabledDriver()
    {
        $enabledDriver = 'local_upload';
        if ( defined('FLUENTSUPPORTPRO') ) {
            $driversKey = \FluentSupportPro\App\Services\FileUploadIntegration\Drivers::getDriversKey();

            if( empty($driversKey) ) {
                return $enabledDriver;
            }
        }
        else {
            return $enabledDriver;
        }

        $rows = Meta::where('object_type', 'integration_settings')
            ->whereIn('key', $driversKey)
            ->get()
            ->toArray();

        if( !$rows ) {
            return $enabledDriver;
        }

        foreach ($rows as $row) {
            $rowValue = maybe_unserialize($row['value']);
            if( $rowValue['status'] == 'yes' ) {
                $enabledDriver =  $row['key'];
                break;
            }
        }

        return $enabledDriver;
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
