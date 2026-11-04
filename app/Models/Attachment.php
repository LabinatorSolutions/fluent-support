<?php

namespace FluentSupport\App\Models;

use FluentSupport\App\Services\Helper;
use FluentSupport\App\Services\Storage\StorageManager;

class Attachment extends Model
{
    protected $table = 'fs_attachments';

    protected $hidden = ['full_url', 'file_path'];

    protected $appends = ['secureUrl'];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'ticket_id',
        'person_id',
        'conversation_id',
        'file_type',
        'file_path',
        'full_url',
        'title',
        'driver',
        'file_size',
        'status',
        'settings'
    ];

    public static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $uid = wp_generate_uuid4();
            $model->file_hash = md5($uid . wp_rand(0, 1000));
        });
    }

    /**
     * Delete the stored files for these attachments. Local files go now, remote
     * objects are queued (StorageManager::purge). DB rows are left to the caller.
     *
     * @param \FluentSupport\Framework\Database\Orm\Collection|array $attachments
     * @param int|null $ticketId  When provided, removes the ticket_{id} upload directory.
     */
    public static function purgeAttachments($attachments, $ticketId = null)
    {
        StorageManager::purge($attachments, $ticketId);
    }

    public function setSettingsAttribute($settings)
    {
        $this->attributes['settings'] = \maybe_serialize($settings);
    }

    public function getSettingsAttribute($settings)
    {
        return Helper::safeUnserialize($settings);
    }

    /**
     * Signed download URL.
     *
     * @return string
     */
    public function getSecureUrlAttribute()
    {
        return $this->signedUrl(self::signedUrlExpiry());
    }

    /**
     * Download URL valid until $expires.
     *
     * @param int $expires Unix timestamp.
     * @return string
     */
    public function signedUrl($expires)
    {
        return add_query_arg([
            'fst_file'    => $this->file_hash,
            'expires'     => (int) $expires,
            'secure_sign' => self::signature($this->id, (int) $expires)
        ], site_url('/index.php'));
    }

    /**
     * Download URL to put in an email or an export, which people open days later.
     * Valid for 30 days (filter `fluent_support/attachment_email_url_ttl`) and bound
     * to the ticket hash, so rotating the hash (customer change) revokes it. After
     * it lapses the link still works for whoever can see the ticket (ExternalPages).
     *
     * @param bool $forCustomer Customer-bound email: with hash-signed ticket links on,
     *                          the ticket hash rides along so a customer without a
     *                          WordPress account can still open it later. Never for
     *                          agent emails: the hash also opens the ticket.
     * @return string
     */
    public function emailUrl($forCustomer = false)
    {
        $ttl = max(DAY_IN_SECONDS, (int) apply_filters('fluent_support/attachment_email_url_ttl', 30 * DAY_IN_SECONDS));
        $expires = (intdiv(time() + $ttl, DAY_IN_SECONDS) + 1) * DAY_IN_SECONDS;
        $ticketHash = $this->ticket_id ? (string) Ticket::where('id', $this->ticket_id)->value('hash') : '';

        $args = [
            'fst_file'    => $this->getAttribute('file_hash'),
            'expires'     => $expires,
            'sv'          => '3',
            'secure_sign' => self::ticketSignature($this->id, $expires, $ticketHash),
        ];

        if ($forCustomer && $ticketHash && Helper::isPublicSignedTicketEnabled()) {
            $args['fst_ticket'] = $ticketHash;
        }

        return add_query_arg($args, site_url('/index.php'));
    }

    /**
     * Signature of an email link: also covers the ticket hash.
     *
     * @param int $attachmentId
     * @param int $expires
     * @param string $ticketHash
     * @return string
     */
    public static function ticketSignature($attachmentId, $expires, $ticketHash)
    {
        return hash_hmac('sha256', 'v3|' . $attachmentId . '|' . (int) $expires . '|' . $ticketHash, wp_salt('secure_auth'));
    }

    /**
     * Expiry for a newly signed URL. Rounded to a TTL boundary so the URL stays
     * the same for a while (browser caching), and always at least one TTL ahead.
     * A URL signed just before a boundary used to die within seconds.
     *
     * @return int Unix timestamp.
     */
    public static function signedUrlExpiry()
    {
        $ttl = max(MINUTE_IN_SECONDS * 5, (int) apply_filters('fluent_support/attachment_signed_url_ttl', HOUR_IN_SECONDS));

        return (intdiv(time(), $ttl) + 2) * $ttl;
    }

    /**
     * Signature for an expiring URL. The 'v2|' prefix keeps it apart from legacy
     * signatures: those signed "id|YmdH", and without the prefix an old hourly
     * signature would pass as one that expires in 2034 (2026100614 read as a timestamp).
     *
     * @param int $attachmentId
     * @param int $expires Unix timestamp.
     * @return string
     */
    public static function signature($attachmentId, $expires)
    {
        return hash_hmac('sha256', 'v2|' . $attachmentId . '|' . (int) $expires, wp_salt('secure_auth'));
    }

    /**
     * Signature of a pre-2.4.1 URL, valid for one UTC hour.
     *
     * @param int $attachmentId
     * @param string $hourWindow gmdate('YmdH')
     * @return string
     */
    public static function legacySignature($attachmentId, $hourWindow)
    {
        return hash_hmac('sha256', $attachmentId . '|' . $hourWindow, wp_salt('secure_auth'));
    }
}
