<?php
namespace FluentSupport\App\Services\Includes;

use FluentSupport\App\Models\Meta;
use FluentSupport\App\Services\Helper;
use FluentSupport\Framework\Request\File;

class UploadService
{
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
    public function _handleFileUpload($file){
        return $this->uploadFileToTempLocation($file);
    }

    /**
     * Handle email attachments
     * @param array $file file data from request
     * @param int $ticketId ticket id
     * @param array $acceptedMimes accepted mime types for file upload
     * @return array $uploadedFiles uploaded file data
     */
    public function _handleEmailAttachments($file, $ticketId, $acceptedMimes=[]){
        $fileContent =  $this->requestContent($file['url'], $acceptedMimes);
        $_filter = true;
        add_filter( 'upload_dir', function( $arr ) use( &$_filter ){
            if ( $_filter ) {
                $folder = '/' . FLUENT_SUPPORT_UPLOAD_DIR . DIRECTORY_SEPARATOR. '_temp';
                $arr['path'] = $arr['basedir'] . $folder;
                $arr['url'] = $arr['baseurl'] . $folder;
                $arr['subdir'] = $folder;
            }
            return $arr;
        } );
        $uploadToLocalDriver = wp_upload_bits($file['filename'], null, $fileContent);
        remove_filter( 'upload_dir', $_filter );

        // this is the file data from email attachment and required for upload to cloud
        return [
            'type' => $uploadToLocalDriver['type'],
            'name' => $file['filename'],
            'file' => $uploadToLocalDriver['file'],
            'url' => $uploadToLocalDriver['url'],
            'from' => 'email'
        ];
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

    /**
     * Handle file upload to local
     * @param int $ticketId ticket id
     * @param array $file file data from request
     */
    private function uploadFileToTempLocation( $file )
    {
        $uploadInfo = FileSystem::setSubDir('_temp')->put($file);
        if(!empty($uploadInfo)){
            return $uploadInfo;
        }else {
            return $this->getEmptyFileData();
        }
    }

    private function getEmptyFileData($drive = 'temp')
    {
        return [[
                    'file_path' => '',
                    'url' => '',
                    'name' => '',
                    'type' => '',
                    'size' => '',
                    'driver' => $drive
                ]];
    }

    /**
     * Upload files to integrated cloud storage
     * @param $file
     * @param $ticketId
     * @return array | boolean
     */
    public function _copyFromTempToOriginal($tempSource, $ticketId)
    {
        if (defined('FLUENTSUPPORTPRO') && $this->enabledDriver != 'local_upload'){
            //Move to cloud
            try {
                $driverClass = \FluentSupportPro\App\Services\FileUploadIntegration\Drivers::getDriverInstance($this->enabledDriver);
                return $driverClass->copyFromTempToOriginal($tempSource, $ticketId);
            } catch (\Exception $e) {
                //Move to local
                return FileSystem::copy($tempSource, $ticketId);
            }
        }else {
            //Move to local
            return FileSystem::copy($tempSource, $ticketId);
        }
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
