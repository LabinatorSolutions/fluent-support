<?php

namespace FluentSupport\App\Modules\TicketImport\Importers;

use FluentSupport\App\Modules\Customers\CustomerService;
use FluentSupport\App\Exceptions\ApiException;
use FluentSupport\App\Models\Agent;
use FluentSupport\App\Models\Attachment;
use FluentSupport\App\Models\Customer;
use FluentSupport\App\Models\Person;
use FluentSupport\App\Models\Ticket;
use FluentSupport\App\Services\Helper;
use FluentSupport\App\Services\Includes\FileSystem;
use FluentSupport\Framework\Support\Arr;

abstract class BaseImporter
{
    protected $db;
    protected $handler = '';
    protected $mailbox;
    protected $limit = 20;
    protected $ticketUpdatedSince;

    public function __construct()
    {
        $this->db = Helper::FluentSupport('db');
        $this->mailbox = Helper::getDefaultMailBox();
        $this->limit = apply_filters('fluent_support/ticket_import_chunk_limit', 20);
        $this->ticketUpdatedSince = apply_filters('fluent_support/fs_tickets_updated_since', '2010-01-19T02:00:00Z');
    }
    /**
     * Ticket stats from the source helpdesk.
     *
     * @return array
     */
    abstract public function stats();

    /**
     * @param int $page
     * @param string $handler
     * @return array
     */
    abstract public function doMigration($page, $handler);

    /**
     * Delete the source helpdesk's tickets after a successful migration.
     *
     * @param int $page
     * @return array
     */
    abstract public function deleteTickets($page);

    /**
     * The importer for a handler key ('freshdesk', 'zendesk', ...), set up with the
     * connection details the screen or the CLI sends.
     *
     * @param string $handler
     * @param array  $query access_token, mailbox, domain, email, cursor, next_page_url, include_archived.
     * @return BaseImporter
     */
    public static function make($handler, array $query = [])
    {
        /*
         * Importer classes by handler key. A value without a namespace is a class in
         * this folder; add-ons give the full class name of their own importer.
         *
         * @param array $classMap handler => class
         */
        $classMap = apply_filters('fluent_support/migrator_class_mapper', [
            'awesome-support' => 'AwesomeSupportTickets',
            'support-candy'   => 'SupportCandyTickets',
            'js-helpdesk'     => 'JSHelpdeskTickets',
            'helpscout'       => 'HelpScoutTickets',
            'freshdesk'       => 'FreshDeskTickets',
            'zendesk'         => 'ZendeskTickets'
        ]);

        if (empty($classMap[$handler])) {
            throw ApiException::invalid(esc_html__('Unknown ticket importer.', 'fluent-support'));
        }

        $class = $classMap[$handler];
        if (strpos($class, '\\') === false) {
            $class = __NAMESPACE__ . '\\' . $class;
        }

        $importer = new $class();

        $setters = [
            'access_token'     => 'setAccessToken',
            'mailbox'          => 'setMailboxId',
            'domain'           => 'setDomain',
            'email'            => 'setEmail',
            'cursor'           => 'setCursor',
            'next_page_url'    => 'setNextPageUrl',
            'include_archived' => 'setIncludeArchived',
        ];

        foreach ($setters as $key => $setter) {
            if (isset($query[$key]) && $query[$key] !== '' && method_exists($importer, $setter)) {
                $importer->{$setter}($query[$key]);
            }
        }

        return $importer;
    }

    /**
     * One page of an import from another WordPress helpdesk plugin: the shared loop
     * of Awesome Support, SupportCandy and JS Help Desk.
     *
     * @param int    $page
     * @param int    $total     The source's ticket count.
     * @param array  $tickets   This page's tickets.
     * @param string $optionKey Where the finish time is stored.
     * @return array
     */
    protected function importPage($page, $total, $tickets, $optionKey)
    {
        if (!$total) {
            return [
                'message'     => __('Sorry, no tickets available for import.', 'fluent-support'),
                'had_tickets' => 'no',
            ];
        }

        $results = $this->migrateTickets($tickets);
        $hasMore = $page * $this->limit <= $total;

        $response = [
            'handler'       => $this->handler,
            'insert_ids'    => $results['inserts'],
            'skips'         => count($results['skips']),
            'has_more'      => $hasMore,
            // Percent done, for the progress bar.
            'completed'     => min(100, (int) round($page * $this->limit / $total * 100)),
            'imported_page' => $page,
            'total_pages'   => ceil($total / $this->limit),
            'next_page'     => $page + 1,
            'total_tickets' => $total,
            'remaining'     => $total - ($page * $this->limit)
        ];

        if (!$hasMore) {
            $response['message'] = __('All tickets have been imported successfully', 'fluent-support');
            update_option($optionKey, current_time('mysql'), 'no');
        }

        return $response;
    }

    /**
     * @param array $tickets
     * @return array
     */
    public function migrateTickets($tickets)
    {
        $insertIds = [];
        $skips = [];
        foreach ($tickets as $ticket) {
            $createdTicket = $this->migrateTicket($ticket);
            if ($createdTicket) {
                $insertIds[] = $createdTicket->id;
            } else {
                $skips[] = $ticket;
            }
        }

        return [
            'inserts' => $insertIds,
            'skips'   => $skips
        ];
    }

    /**
     * @param array $ticketData
     * @return \FluentSupport\App\Models\Ticket|false
     */
    public function migrateTicket($ticketData)
    {
        if ($this->isMigrated($ticketData['origin_id'])) {
            return false;
        }

        $fillables = (new Ticket())->getFillable();
        $fillables[] = 'created_at';
        $fillables[] = 'updated_at';

        $data = array_filter(Arr::only($ticketData, $fillables));

        if (!isset($data['content'])) {
            $data['content'] = '';
        }

        $data['customer_id'] = $ticketData['customer']['id'];

        if (!empty($ticketData['agent'])) {
            $data['agent_id'] = $ticketData['agent']['id'];
        }

        if (empty($ticketData['mailbox_id']) && $this->mailbox) {
            $data['mailbox_id'] = $this->mailbox->id;
        }

        $data = $this->addMetaData($data);

        $createdTicketId = $this->db->table('fs_tickets')
            ->insertGetId($data);

        if (!$createdTicketId) {
            return false;
        }

        $createdTicket = Ticket::find($createdTicketId);


        if (!empty($ticketData['attachments'])) {
            foreach ($ticketData['attachments'] as $attachmentData) {
                $attachmentData['person_id'] = $createdTicket->customer_id;
                $attachmentData['ticket_id'] = $createdTicket->id;
                Attachment::create($attachmentData);
            }
        }

        if (!$createdTicket) {
            return false;
        }

        Helper::updateTicketMeta($createdTicket->id, '_' . $this->handler . '_origin_id', $ticketData['origin_id']);

        if (empty($ticketData['replies'])) {
            return $createdTicket;
        }

        // Worked out from the replies alone. Some sources pre-fill the final
        // times, and replaying against those would stop waiting_since moving.
        $lastAgentResponseTime = null;
        $lastCustomerResponseTime = null;
        $waitingSince = $createdTicket->waiting_since;
        $responseCount = 0;
        $firstResponseTimestamp = false;

        $defualtAgentId = false;

        foreach ($ticketData['replies'] as $reply) {

            $replyData = Arr::only($reply, [
                'content',
                'conversation_type',
                'created_at',
                'updated_at'
            ]);

            if (!isset($replyData['content']) || empty($replyData['content'])) {
                continue;
            }

            $replyData['ticket_id'] = $createdTicket->id;
            $replyData['source'] = $this->handler;
            $replyData['content_hash'] = md5($replyData['content']);

            $person = ($reply['is_customer_reply']) ? $this->getPerson($reply['user']) : $this->getPerson($reply['user'], 'agent');

            if (!$person) {
                continue;
            }

            $replyData['person_id'] = intval($person->id);

            if ($replyData['conversation_type'] == 'response') {
                $responseCount++;
            }

            // Same bookkeeping as ResponseService: waiting_since starts when the
            // other side replied last and this reply changes whose turn it is.
            $replyCreatedAt = (string) ($replyData['created_at'] ?? '');

            if (!$replyCreatedAt) {
                // No time to place it by.
            } else if ($reply['is_customer_reply']) {
                if ($lastAgentResponseTime && strtotime($lastAgentResponseTime) > strtotime((string) $lastCustomerResponseTime)) {
                    $waitingSince = $replyCreatedAt;
                }
                if (strtotime((string) $lastCustomerResponseTime) < strtotime($replyCreatedAt)) {
                    $lastCustomerResponseTime = $replyCreatedAt;
                }
            } else if ($replyData['conversation_type'] == 'response') {
                if (!$firstResponseTimestamp) {
                    $firstResponseTimestamp = $replyCreatedAt;
                }

                if (strtotime((string) $lastAgentResponseTime) < strtotime($replyCreatedAt)) {
                    $lastAgentResponseTime = $replyCreatedAt;
                    $waitingSince = $replyCreatedAt;
                }

                $defualtAgentId  = $person->id;
            }

            $conversionId = $this->db->table('fs_conversations')
                ->insertGetId($replyData);

            if (!empty($reply['attachments'])) {
                foreach ($reply['attachments'] as $attachmentData) {
                    $attachmentData['ticket_id'] = $createdTicket->id;
                    $attachmentData['person_id'] = $person->id;
                    $attachmentData['conversation_id'] = $conversionId;
                    Attachment::create($attachmentData);
                }
            }
        }

        $ticketUpdateData = array_filter([
            'last_agent_response'    => $lastAgentResponseTime ?: $createdTicket->last_agent_response,
            'last_customer_response' => $lastCustomerResponseTime ?: $createdTicket->last_customer_response,
            'waiting_since'          => $waitingSince,
            'response_count'         => $responseCount
        ]);

        $createdAt = (string) $createdTicket->created_at;

        if(!$createdTicket->agent_id && $defualtAgentId) {
            $ticketUpdateData['agent_id'] = $defualtAgentId;
        }

        if ($firstResponseTimestamp && $createdAt) {
            $ticketUpdateData['first_response_time'] = $this->siteSeconds($firstResponseTimestamp) - $this->siteSeconds($createdAt);
        }

        if ($createdTicket->status == 'closed' && !empty($createdTicket->resolved_at) && $createdAt) {
            $ticketUpdateData['total_close_time'] = $this->siteSeconds((string) $createdTicket->resolved_at) - $this->siteSeconds($createdAt);
        }

        if ($ticketUpdateData) {
            $createdTicket->fill($ticketUpdateData);
            $createdTicket->save();
        }

        return $createdTicket;
    }

    /**
     * Find or create the customer/agent for an imported ticket or reply.
     *
     * @param array $personData
     * @param string $type 'customer' or 'agent'
     * @return object|false $person
     */
    protected function getPerson($personData, $type = 'customer')
    {

        static $cachedPerson = [];

        $personData = (array) $personData;

        if (!empty($personData['user_id'])) {
            if (isset($cachedPerson[$personData['user_id'] . '_' . $type])) {
                return $cachedPerson[$personData['user_id'] . '_' . $type];
            }
        }

        if ($type == 'customer') {
            if (!empty($personData['user_id']) || !empty($personData['email'])) {
                $person = CustomerService::findOrCreate($personData);
                if ($person) {
                    $cachedPerson[$person->user_id . '_' . $type] = $person;
                }
                return $person;
            }
            return false;
        }

        if (!empty($personData['user_id'])) {
            $person = Person::where('user_id', $personData['user_id'])
                ->where('person_type', $type)
                ->first();
            if ($person) {
                $cachedPerson[$person->user_id . '_' . $type] = $person;

                return $person;
            }
        }

        if (!empty($personData['email'])) {
            $person = Person::where('email', $personData['email'])
                ->where('person_type', $type)
                ->first();
            if ($person) {
                $cachedPerson[$person->user_id . '_' . $type] = $person;
                return $person;
            }
        }

        $user = false;

        if (!empty($personData['user_id'])) {
            $user = get_user_by('ID', $personData['user_id']);
        } else if (!empty($personData['email'])) {
            $user = get_user_by('email', $personData['email']);
        }

        if (!$user && 'agent' == $type) {
            $person = Agent::updateOrCreate(
                [
                    'email' => Arr::get($personData, 'email'),
                ],
                [
                    'first_name' => $personData['first_name'] ?? '',
                    'last_name'  => $personData['last_name'] ?? '',
                    'email'      => $personData['email'] ?? '',
                    'type'       => $type
                ]
            );

            return $person;
        }


        if ($user){
            $personData['user_id'] = $user->ID;
            $personData['first_name'] = $user->first_name;
            $personData['last_name'] = $user->last_name;
            $personData['last_name'] = $user->last_name;
            $personData['email'] = $user->user_email;

            if (empty($personData['full_name'])) {
                $personData['full_name'] = $user->display_name;
            }

        }

        if (empty($personData['first_name']) && $personData['last_name'] && !empty($personData['name'])) {
            $fullNameArray = explode(' ', $personData['name']);
            $personData['first_name'] = array_shift($fullNameArray);
            if ($fullNameArray) {
                $personData['last_name'] = implode(' ', $fullNameArray);
            }
            unset($personData['name']);
        }


        $personData['person_type'] = $type;

        $person = Person::create($personData);
        $cachedPerson[$person->user_id . '_' . $type] = $person;
        return $person;
    }

    protected function fallbackAgentId()
    {
        $agent = Person::where('person_type', 'agent')
            ->oldest('id')
            ->select(['id'])
            ->first();

        return (int)$agent->id;
    }

    protected function isMigrated($originId)
    {
        $exist = $this->db->table('fs_meta')
            ->where('object_type', 'ticket_meta')
            ->where('key', '_' . $this->handler . '_origin_id')
            ->where('value', $originId)
            ->first();

        return !!$exist;
    }

    /** Site-time string => the Unix timestamp the source sent, filled by siteTime(). */
    protected $sourceSeconds = [];

    /**
     * A date the source service sent, as the site-time string the helpdesk tables
     * hold. The services send ISO 8601 in UTC (a Z or an offset); a ticket created
     * here is stored in the site's timezone, so an imported one must be too, or it
     * shows hours off in the ticket view and lands on the wrong day in reports.
     *
     * @param string|null $remoteDate
     * @return string|null
     */
    protected function siteTime($remoteDate)
    {
        $timestamp = $remoteDate ? strtotime((string) $remoteDate) : false;
        if (!$timestamp) {
            return null;
        }

        // Kept for siteSeconds(): the hour that repeats when the clocks go back
        // cannot be told apart from the site-time string alone.
        $siteDate = wp_date('Y-m-d H:i:s', $timestamp);
        $this->sourceSeconds[$siteDate] = $timestamp;

        return $siteDate;
    }

    /**
     * The moment a stored site-time string stands for, as a Unix timestamp. For
     * elapsed time: two wall-clock strings on either side of a daylight-saving
     * change are an hour off when they are subtracted as they are.
     *
     * @param string $siteDate Y-m-d H:i:s in the site's timezone.
     * @return int
     */
    protected function siteSeconds($siteDate)
    {
        // The source's own moment, when this date came through siteTime().
        if (isset($this->sourceSeconds[(string) $siteDate])) {
            return $this->sourceSeconds[(string) $siteDate];
        }

        try {
            return (new \DateTime((string) $siteDate, wp_timezone()))->getTimestamp();
        } catch (\Exception $e) {
            return (int) strtotime((string) $siteDate);
        }
    }

    protected function addMetaData($ticketData)
    {
        if (empty($ticketData['slug'])) {
            $ticketData['slug'] = Ticket::slugify($ticketData['title']);
        }

        // Same 128-bit bearer hash as Ticket::creating(); insertGetId() skips that hook.
        $ticketData['hash'] = bin2hex(random_bytes(16));

        $ticketData['content_hash'] = md5($ticketData['content']);

        if (empty($ticketData['created_at'])) {
            $ticketData['created_at'] = current_time('mysql');
        }
        if (empty($ticketData['updated_at'])) {
            $ticketData['updated_at'] = current_time('mysql');
        }

        // The ticket itself is the customer's first message: the import's
        // times, not the moment of importing, which made every imported ticket
        // look freshly answered by the customer.
        if (empty($ticketData['last_customer_response'])) {
            $ticketData['last_customer_response'] = $ticketData['created_at'];
        }

        if (empty($ticketData['waiting_since'])) {
            $ticketData['waiting_since'] = $ticketData['created_at'];
        }

        return $ticketData;
    }


    public function resolvePerson($personUserId)
    {
        $person = get_user_by('ID', $personUserId);

        if (!$person) {
            return false;
        }

        return [
            'user_id'   => $personUserId,
            'full_name' => $person->first_name . ' ' . $person->last_name,
            'email'     => $person->user_email
        ];
    }

    public function getUnknownCustomerData($authorId): array
    {
        return [
            'user_id'   => 0,
            'full_name' => 'Unknown User',
            'email'     => 'unknown-' . $authorId . '@example.com',
        ];
    }

    // Helpers for the cloud importers (Help Scout, Freshdesk, Zendesk).

    public static function updateOrCreatePerson($personData)
    {
        $emailArray = [
            'email' => $personData['email'],
            'person_type' => $personData['person_type']
        ];

        $person = Person::updateOrCreate($emailArray, $personData);
        return $person->toArray();
    }

    public static function personFromApi($personData, $type)
    {
        if(!$personData) {
            return [];
        }

        $name = explode(' ', $personData->name);

        return [
            'first_name' => $name[0] ?? '',
            'last_name' => $name[1] ?? '',
            'email' => $personData->email ?? $personData->address ?? null,
            'person_type' => $type
        ];
    }

    // Reduce a remote-supplied attachment name to a safe, traversal-free basename.
    public static function sanitizeAttachmentFilename($fileName)
    {
        $fileName = wp_basename((string) $fileName);
        $fileName = sanitize_file_name($fileName);
        $fileName = str_replace("\0", '', $fileName);
        $fileName = ltrim($fileName, '.');

        if ($fileName === '') {
            $fileName = 'attachment-' . wp_generate_password(8, false, false);
        }

        return $fileName;
    }

    // Reject anything that isn't a plain https:// hostname resolving to a public,
    // non-reserved IP — blocks loopback, RFC1918, link-local/cloud-metadata
    // (169.254.0.0/16), and raw IP-literal hosts (real vendor tenants are never IPs).
    public static function isSafeRemoteUrl($url)
    {
        if (!is_string($url) || $url === '') {
            return false;
        }

        $parts = wp_parse_url($url);
        if (empty($parts['scheme']) || strtolower($parts['scheme']) !== 'https' || empty($parts['host'])) {
            return false;
        }

        if (!empty($parts['user']) || !empty($parts['pass'])) {
            return false;
        }

        $host = trim($parts['host'], '.');

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return false;
        }

        $ip = gethostbyname($host);
        if ($ip === $host) {
            return false;
        }

        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }

    // Download a file from a remote URL and create a new directory for this if not exists
    // Then save the file to the new directory and move this directory to a new given directory
    public static function downloadFile($remoteUrl, $baseDir, $fileName)
    {
        if (!self::isSafeRemoteUrl($remoteUrl)) {
            return new \WP_Error('import_unsafe_url', __('Attachment URL is not allowed.', 'fluent-support'));
        }

        $baseDir = trailingslashit($baseDir);

        if (!wp_mkdir_p($baseDir)) {
            return new \WP_Error('import_dir_error', __('Could not create the attachment import directory.', 'fluent-support'));
        }

        $realBaseDir = realpath($baseDir);
        if ($realBaseDir === false) {
            return new \WP_Error('import_dir_error', __('Could not resolve the attachment import directory.', 'fluent-support'));
        }
        $realBaseDir = rtrim($realBaseDir, '/\\') . DIRECTORY_SEPARATOR;

        $safeFileName = self::sanitizeAttachmentFilename($fileName);
        $existingPath = $baseDir . $safeFileName;

        // Preserve the original dedupe behaviour: if this exact attachment was
        // already imported for this ticket, reuse it instead of re-downloading
        // (wp_unique_filename() would otherwise mint a "-1" copy every re-import).
        if (file_exists($existingPath)) {
            $filePath = $existingPath;
            $uniqueFileName = $safeFileName;
        } else {
            $uniqueFileName = wp_unique_filename($baseDir, $safeFileName);
            $filePath = $baseDir . $uniqueFileName;
        }

        // Belt-and-braces: the resolved parent directory must still be the base dir.
        $resolvedParent = realpath(dirname($filePath));
        if ($resolvedParent === false || strpos($resolvedParent . DIRECTORY_SEPARATOR, $realBaseDir) !== 0) {
            return new \WP_Error('import_path_error', __('Resolved attachment path escapes the import directory.', 'fluent-support'));
        }

        if (file_exists($filePath)) {
            return $filePath;
        }

        $maxBytes = 25 * MB_IN_BYTES;

        // Cap the transfer itself (not just the assembled body) so an oversized
        // remote response can't be fully pulled into memory before we reject it.
        // Ask for one byte over the limit so a truncated response (real size > cap)
        // can still be told apart from a response that legitimately ends at the cap.
        $response = wp_safe_remote_get($remoteUrl, [
            'timeout'              => 60,
            'stream'               => false,
            'limit_response_size'  => $maxBytes + 1,
            'redirection'          => 0,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        if ($status < 200 || $status >= 300) {
            return new \WP_Error('import_download_error', sprintf(__('Attachment download failed with HTTP status %d.', 'fluent-support'), $status));
        }

        $file_contents = wp_remote_retrieve_body($response);
        if (empty($file_contents)) {
            return new \WP_Error('import_download_error', __('Attachment download returned an empty response.', 'fluent-support'));
        }

        if (strlen($file_contents) > $maxBytes) {
            return new \WP_Error('import_download_error', __('Attachment exceeds the maximum allowed download size.', 'fluent-support'));
        }

        $tmpPath = $baseDir . '.' . $uniqueFileName . '-' . uniqid('', true) . '.part';
        if (file_put_contents($tmpPath, $file_contents) === false) {
            return new \WP_Error('import_write_error', __('Could not write the downloaded attachment.', 'fluent-support'));
        }

        // Core checks the result against get_allowed_mime_types(); the plugin's types apply too.
        add_filter('upload_mimes', [FileSystem::class, 'allowTicketMimes']);
        $fileType = wp_check_filetype_and_ext($tmpPath, $uniqueFileName);
        remove_filter('upload_mimes', [FileSystem::class, 'allowTicketMimes']);
        if (empty($fileType['ext']) || empty($fileType['type'])) {
            @unlink($tmpPath);
            return new \WP_Error('import_filetype_error', __('Attachment file type is not allowed.', 'fluent-support'));
        }

        if (!@rename($tmpPath, $filePath)) {
            @unlink($tmpPath);
            return new \WP_Error('import_write_error', __('Could not save the downloaded attachment.', 'fluent-support'));
        }

        return $filePath;
    }
}
