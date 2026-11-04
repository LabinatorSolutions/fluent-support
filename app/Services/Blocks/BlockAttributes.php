<?php

namespace FluentSupport\App\Services\Blocks;

class BlockAttributes
{
    /**
     * Built block directory holding block.json, the single source of the block's metadata.
     *
     * @return string
     */
    public static function blockDir()
    {
        return FLUENT_SUPPORT_PLUGIN_PATH . 'assets/block-editor/customer-portal';
    }

    /**
     * @return array Customer portal block attributes, as declared in block.json.
     */
    public static function CustomerPortalAttributes()
    {
        static $attributes = null;

        if ($attributes === null) {
            $file = self::blockDir() . '/block.json';
            $metadata = is_readable($file) ? json_decode((string) file_get_contents($file), true) : null;
            $attributes = isset($metadata['attributes']) && is_array($metadata['attributes']) ? $metadata['attributes'] : [];
        }

        return $attributes;
    }
}
