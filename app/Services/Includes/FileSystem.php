<?php

namespace FluentSupport\App\Services\Includes;

use FluentSupport\Framework\Support\Arr;

class FileSystem
{

    protected $subDir = '';


    public function _setSubDir($subDir)
    {
        $this->subDir = $subDir;
        return $this;
    }

    /**
     * Read a file from the plugin's upload dir. Only the basename of $file is used.
     *
     * @param string $file
     * @return string|false
     */
    public function _get($file)
    {
        $arr = explode('/', $file);
        $fileName = end($arr);
        return file_get_contents(
            $this->getDir() . '/' . $fileName
        );
    }

    /**
     * @return string Absolute path of the plugin's upload dir.
     */
    public function _getDir()
    {
        $uploadDir = wp_upload_dir();

        return $uploadDir['basedir'] . DIRECTORY_SEPARATOR . FLUENT_SUPPORT_UPLOAD_DIR;
    }

    /**
     * @param string $file
     * @return string
     */
    public function _getAbsolutePathOfFile($file)
    {
        return $this->_getDir() . '/' . $file;
    }

    /**
     * @return array
     */
    public function _uploadFromRequest()
    {
        return $this->_put(\FluentSupport\App\Services\Helper::FluentSupport('request')->files());
    }

    /**
     * @param array $files
     * @return array
     */
    public function _put($files)
    {
        if (!function_exists('wp_handle_upload')) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
        }

        $this->overrideUploadDir();

        $uploadOverrides = ['test_form' => false];
        $uploadedFiles = [];

        $truncate_files = apply_filters('fluent_support/truncate_uploaded_file_names', false);

        foreach ((array)$files as $file) {
            $filesArray = $file->toArray();

            if ($truncate_files) {
                $filesArray['name'] = $this->truncateFileName($filesArray['name']);
            }

            $extraData = Arr::only($filesArray, ['name', 'size']);
            add_filter('upload_mimes', [self::class, 'allowTicketMimes']);
            $uploadsData = \wp_handle_upload($filesArray, $uploadOverrides);
            remove_filter('upload_mimes', [self::class, 'allowTicketMimes']);
            $uploadedFiles[] = array_merge($extraData, $uploadsData);
        }

        return $uploadedFiles;
    }

    private function truncateFileName($fileName)
    {
        $file_extension = pathinfo($fileName, PATHINFO_EXTENSION);
        $file_base_name = pathinfo($fileName, PATHINFO_FILENAME);

        $max_base_length = 50 - strlen($file_extension) - 1;
        if (strlen($file_base_name) > $max_base_length) {
            $file_base_name = substr($file_base_name, 0, $max_base_length);
        }

        return $file_base_name . '.' . $file_extension;
    }

    public function _putAsContent($fileName, $fileContent)
    {
        add_filter('upload_dir', [$this, '_setCustomUploadDir']);
        add_filter('upload_mimes', [self::class, 'allowTicketMimes']);
        $item = wp_upload_bits($fileName, null, $fileContent);
        remove_filter('upload_mimes', [self::class, 'allowTicketMimes']);
        remove_filter('upload_dir', [$this, '_setCustomUploadDir']);
        return $item;
    }

    /**
     * upload_mimes callback, added only around the plugin's own writes so the rest of
     * the site (Media Library, other plugins) keeps core's list. Core has no JSON or
     * Markdown entry. Markdown maps to text/plain because wp_check_filetype_and_ext()
     * reads a Markdown file's real type as text/plain and refuses any other mapping.
     * Whether a ticket accepts the file is still decided by the accepted file types setting.
     *
     * @param array $mimes
     * @return array
     */
    public static function allowTicketMimes($mimes)
    {
        if (!isset($mimes['json'])) {
            $mimes['json'] = 'application/json';
        }

        if (!isset($mimes['md|markdown'])) {
            $mimes['md|markdown'] = 'text/plain';
        }

        return $mimes;
    }

    /**
     * The types the plugin's own writes accept for the current user: WordPress's
     * list plus allowTicketMimes(). For checks made before or after a write.
     *
     * @return array
     */
    public static function ticketMimeTypes()
    {
        return self::allowTicketMimes(get_allowed_mime_types());
    }

    /**
     * Copy a file into the current sub dir of the plugin's upload dir.
     *
     * @param string $source Absolute file path
     * @return array|false
     */
    public function _copy($source)
    {
        if (!file_exists($source)) {
            return false;
        }

        [$destDir, $folderName] = $this->_getCustomDir();
        $fileName = basename($source);
        $destFile = $destDir . DIRECTORY_SEPARATOR . $fileName;

        $indexFile = $destDir.DIRECTORY_SEPARATOR.'index.html';
        if(!is_file($indexFile)) {
            @file_put_contents($indexFile, '');
        }
        
        if (@copy($source, $destFile)) {
            return $this->_updateUploadDirForCopy($fileName, $folderName);
        }

        return false;
    }

    private function _getCustomDir()
    {
        $folderName = '';
        $directory = $this->_getDir();

        if ($this->subDir) {
            $folderName .= DIRECTORY_SEPARATOR . $this->subDir;
            $directory .= $folderName;
            if (!is_dir($directory)) {
                @mkdir($directory, 0755);
                // Blank index.html prevents directory listing.
                @file_put_contents($directory . DIRECTORY_SEPARATOR . 'index.html', '');
            }
        }

        return [$directory, $folderName];
    }

    private function _updateUploadDirForCopy($fileName, $folderName)
    {
        $uploadDir = wp_upload_dir();
        $destDir = $this->_getDir() . $folderName;
        $uploadDir['file_path'] = $destDir . DIRECTORY_SEPARATOR . $fileName;
        $uploadDir['basedir'] = $destDir;
        $uploadDir['subdir'] = $folderName;
        $uploadDir['url'] = $uploadDir['baseurl'] . DIRECTORY_SEPARATOR . FLUENT_SUPPORT_UPLOAD_DIR . $folderName . DIRECTORY_SEPARATOR . $fileName;
        $uploadDir['baseurl'] = $uploadDir['baseurl'] . DIRECTORY_SEPARATOR . FLUENT_SUPPORT_UPLOAD_DIR . $folderName;



        return $uploadDir;
    }

    /**
     * Only each file's basename is used.
     *
     * @param array $files
     * @return void
     */
    public function _delete($files)
    {
        $files = (array)$files;

        foreach ($files as $file) {
            $arr = explode('/', $file);
            $fileName = end($arr);
            @unlink($this->getDir() . '/' . $fileName);
        }
    }

    /**
     * Route the next wp_handle_upload() into the plugin's upload dir.
     */
    public function _overrideUploadDir()
    {
        add_filter('wp_handle_upload_prefilter', function ($file) {
            add_filter('upload_dir', [$this, '_setCustomUploadDir']);

            add_filter('wp_handle_upload', function ($fileinfo) {
                remove_filter('upload_dir', [$this, '_setCustomUploadDir']);
                $filePath = $fileinfo['file'];
                $fileinfo['file'] = basename($filePath);
                $fileinfo['file_path'] = $filePath;
                return $fileinfo;
            });

            return $this->_renameFileName($file);
        });
    }

    /**
     * upload_dir filter callback.
     *
     * @param array $param
     * @return array $param
     */
    public function _setCustomUploadDir($param)
    {
        $folderName = '/' . FLUENT_SUPPORT_UPLOAD_DIR;

        if ($this->subDir) {
            $folderName .= '/' . $this->subDir;
            $temFile = $param['basedir'] . $folderName . '/index.html';
            if(!is_file($temFile)) {
                if (!is_dir($param['basedir'] . $folderName)) {
                    @mkdir($param['basedir'] . $folderName, 0755);
                }
                @file_put_contents($temFile, '');
            }
        }

        $param['url'] = $param['baseurl'] . $folderName;

        $param['path'] = $param['basedir'] . $folderName;

        if (!is_dir($param['path'])) {
            @mkdir($param['path'], 0755);
            @file_put_contents($param['basedir'] . $folderName . '/index.html', '');
        }

        return $param;
    }

    /**
     * Prefix uploaded file names with a random token.
     *
     * @param array $file
     * @return array $file
     */
    public function _renameFileName($file)
    {
        $prefix = 'fluent_support-' . md5(uniqid(wp_rand())) . '___';
        $prefix = apply_filters('fluent_support/uploaded_file_name_prefix', $prefix);
        $file['name'] = $prefix . $file['name'];
        return $file;
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
