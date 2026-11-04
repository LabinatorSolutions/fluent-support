<?php

namespace FluentSupport\App\Services\Storage;

/**
 * Turns ticket attachments into email attachments.
 *
 * Remote files are fetched to a temporary local copy when the upload's own temp
 * file is gone. Files that can't be fetched, or that would push the email over the
 * size limit, are sent as links instead (valid 30 days, see Attachment::emailUrl()).
 *
 * One reply sends several emails, so fetched copies are shared for the rest of the
 * request and deleted at shutdown. Fetching stops after a time budget, so a slow
 * bucket can't hold up the reply; the remaining files go as links.
 */
class MailAttachments
{
    /**
     * @var string[]
     */
    private $paths = [];

    /**
     * @var array[] ['title' => string, 'url' => string]
     */
    private $links = [];

    /**
     * @var string[]
     */
    private $temporary = [];

    /**
     * Copies fetched in this request, by attachment id.
     *
     * @var string[]
     */
    private static $fetched = [];

    /**
     * Seconds spent fetching remote files in this request.
     *
     * @var float
     */
    private static $fetchSeconds = 0;

    /**
     * @var bool
     */
    private static $hooked = false;

    /**
     * @var bool
     */
    private $forCustomer = false;

    /**
     * @param iterable|null $files Attachment models.
     * @param bool $forCustomer The email goes to the customer (see Attachment::emailUrl()).
     */
    public function __construct($files, $forCustomer = false)
    {
        $this->forCustomer = (bool) $forCustomer;

        if (!$files) {
            return;
        }

        /**
         * Largest total size of files attached to one email, in bytes. Larger files go as links.
         *
         * @param int $maxBytes
         */
        $maxBytes = (int) apply_filters('fluent_support/mail_attachments_max_bytes', 10 * MB_IN_BYTES);
        /**
         * Most seconds one request spends fetching remote files for emails. Files
         * not fetched by then are sent as links.
         *
         * @param int|float $seconds 0 = no limit.
         */
        $fetchBudget = (float) apply_filters('fluent_support/mail_attachments_fetch_seconds', 15);
        $total = 0;

        foreach ($files as $file) {
            $size = (int) $file->file_size;

            if ($size && $maxBytes && ($total + $size) > $maxBytes) {
                $this->addLink($file);
                continue;
            }

            $local = $this->localFile($file, $maxBytes ? $maxBytes - $total : 0, $fetchBudget);

            if (!$local) {
                $this->addLink($file);
                continue;
            }

            $size = $size ?: (int) @filesize($local['path']);

            if ($maxBytes && ($total + $size) > $maxBytes) {
                $this->addLink($file);
                continue;
            }

            $total += $size;
            $this->paths[] = $local['path'];
        }
    }

    private function localFile($file, $maxBytes, $fetchBudget)
    {
        $id = (int) $file->id;

        if ($id && isset(self::$fetched[$id]) && file_exists(self::$fetched[$id])) {
            return ['path' => self::$fetched[$id], 'temporary' => true];
        }

        if (!self::$hooked) {
            self::$hooked = true;
            add_action('shutdown', [self::class, 'deleteFetched']);
            // One Action Scheduler run handles many actions: each gets its own copies and budget.
            add_action('action_scheduler_after_execute', [self::class, 'deleteFetched']);
        }

        $canFetch = !$fetchBudget || self::$fetchSeconds < $fetchBudget;
        $started = microtime(true);

        // A download already started can't be stopped, so no HTTP call made for this
        // fetch (download, token refresh) may wait longer than the time left.
        $capTimeout = null;
        if ($canFetch && $fetchBudget) {
            $left = max(1, (int) ceil($fetchBudget - self::$fetchSeconds));
            $capTimeout = function ($args) use ($left) {
                $args['timeout'] = isset($args['timeout']) ? min((float) $args['timeout'], $left) : $left;
                return $args;
            };
            add_filter('http_request_args', $capTimeout, PHP_INT_MAX);
        }

        try {
            $local = StorageManager::getLocalFile($file, $maxBytes, $canFetch);
        } finally {
            if ($capTimeout) {
                remove_filter('http_request_args', $capTimeout, PHP_INT_MAX);
            }
        }

        if ($canFetch) {
            self::$fetchSeconds += microtime(true) - $started;
        }

        if (!$local || !$local['temporary']) {
            return $local;
        }

        if (!$id) {
            $this->temporary[] = $local['path'];
            return $local;
        }

        self::$fetched[$id] = $local['path'];

        return $local;
    }

    /**
     * Delete the copies fetched in this request and reset the time budget. Runs at
     * shutdown and after each Action Scheduler action.
     */
    public static function deleteFetched()
    {
        foreach (self::$fetched as $path) {
            StorageManager::deleteTempFile($path);
        }

        self::$fetched = [];
        self::$fetchSeconds = 0;
    }

    private function addLink($file)
    {
        $this->links[] = [
            'title' => (string) $file->title,
            'url'   => $file->emailUrl($this->forCustomer),
        ];
    }

    /**
     * @return string[]
     */
    public function getPaths()
    {
        return $this->paths;
    }

    /**
     * @return array[]
     */
    public function getLinks()
    {
        return $this->links;
    }

    /**
     * The email body with a list of linked files added before </body>.
     *
     * @param string $body
     * @return string
     */
    public function addLinksToBody($body)
    {
        if (!$this->links) {
            return $body;
        }

        $html = '<div class="fs_email_file_links" style="margin-top: 20px;"><p><strong>' . esc_html__('Attachments', 'fluent-support') . '</strong></p><ul>';
        foreach ($this->links as $link) {
            $html .= '<li><a href="' . esc_url($link['url']) . '">' . esc_html($link['title']) . '</a></li>';
        }
        $html .= '</ul></div>';

        $pos = stripos($body, '</body>');

        if ($pos === false) {
            return $body . $html;
        }

        return substr_replace($body, $html, $pos, 0);
    }

    /**
     * Delete this email's own temporary copies. Call after the email is sent. Copies
     * shared with other emails go at shutdown (see deleteFetched()).
     */
    public function cleanup()
    {
        foreach ($this->temporary as $path) {
            StorageManager::deleteTempFile($path);
        }

        $this->temporary = [];
    }
}
