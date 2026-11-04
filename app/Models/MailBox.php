<?php

namespace FluentSupport\App\Models;

use Exception;
use FluentSupport\App\Modules\PermissionManager;
use FluentSupport\App\Services\EmailNotification\Settings;
use FluentSupport\App\Services\Helper;
use FluentSupport\Framework\Support\Arr;

class MailBox extends Model
{
    protected $table = 'fs_mail_boxes';

    protected $appends = ['settings'];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'slug',
        'email',
        'box_type',
        'mapped_email',
        'email_footer',
        'settings',
        'avatar',
        'created_by',
        'is_default'
    ];

    public static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->slug = static::slugify($model->name);
            $model->settings = $model->settings ?: [
                'admin_email_address' => ''
            ];
        });
    }

    public function setSettingsAttribute($settings)
    {
        $this->attributes['settings'] = \maybe_serialize($settings);
    }

    public function getSettingsAttribute($value)
    {
        if (array_key_exists('settings', $this->attributes)) {
            return Helper::safeUnserialize($this->attributes['settings']);
        }

        return [];
    }

    /**
     * Mailboxes the current agent isn't restricted from. The restriction list is
     * the only gate, so an unrestricted agent gets `settings` (incl.
     * admin_email_address) for every box.
     *
     * @param bool $withEmail Include the inbox address. Only pass true once the caller
     *                        has confirmed the current user holds `fst_sensitive_data`.
     * @return \FluentSupport\Framework\Database\Orm\Collection
     */
    public static function getAccessibleBoxes($withEmail = false)
    {
        $columns = ['id', 'name', 'box_type', 'settings'];

        if ($withEmail) {
            $columns[] = 'email';
        }

        $query = MailBox::select($columns);

        $restrictedBoxes = PermissionManager::getRestrictedMailboxIds();

        if ($restrictedBoxes) {
            $query->whereNotIn('id', $restrictedBoxes);
        }

        return $query->get();
    }

    public static function slugify($title)
    {
        $slug = sanitize_title($title, 'business', 'display');
        if (MailBox::where('slug', $slug)->first()) {
            $slug .= '-' . time();
        }
        return $slug;
    }

    /** From-name choices for email an agent writes (settings.from_name_type). */
    const FROM_NAME_MAILBOX = 'mailbox';
    const FROM_NAME_AGENT = 'agent';
    const FROM_NAME_CUSTOM = 'custom';

    /**
     * @param \FluentSupport\App\Models\Agent|null $agent The agent who wrote the email.
     *        Only agent-written email passes one; automatic email keeps the inbox name.
     * @return array
     */
    public function getMailerHeader($agent = null)
    {
        $headers = [];
        $fromString = $this->email;
        $fromName = $this->getFromName($agent);
        if ($fromName) {
            $fromString = $fromName . ' <' . $fromString . '>';
        }

        if ($fromString) {
            $headers[] = 'From: '. $fromString;
        }

        $headers[] = 'Reply-To: '. $fromString;

        return $headers;
    }

    /**
     * Display name for the From header. The address is always the inbox email, so
     * replies thread back; only the name follows the inbox's From name setting.
     *
     * @param \FluentSupport\App\Models\Agent|null $agent
     * @return string
     */
    public function getFromName($agent = null)
    {
        $settings = is_array($this->settings) ? $this->settings : [];
        $type = isset($settings['from_name_type']) ? $settings['from_name_type'] : self::FROM_NAME_MAILBOX;
        $name = '';

        if ($agent && $type === self::FROM_NAME_AGENT) {
            $name = $agent->full_name;
        } elseif ($agent && $type === self::FROM_NAME_CUSTOM && !empty($settings['from_name_template'])) {
            $name = apply_filters('fluent_support/parse_smartcode_data', $settings['from_name_template'], [
                'agent'    => $agent,
                'business' => $this,
            ]);
        }

        $name = self::cleanFromName($name) ?: self::cleanFromName($this->name);

        /*
         * Filter the From name of an email sent from this inbox.
         *
         * @since v2.4.1
         * @param string $name
         * @param MailBox $mailbox
         * @param \FluentSupport\App\Models\Agent|null $agent Set for email an agent wrote.
         */
        return (string) apply_filters('fluent_support/mailbox_from_name', $name, $this, $agent);
    }

    // A header value: no line breaks, quotes, angle brackets or address separators, no
    // unparsed smartcodes. The name also goes into Reply-To, which wp_mail() splits on commas.
    private static function cleanFromName($name)
    {
        $name = preg_replace('/{{[^}]*}}/', '', (string) $name);
        $name = wp_strip_all_tags($name, true);
        $name = preg_replace('/[\x00-\x1F\x7F",;<>\\\\]+/u', ' ', $name);
        $name = trim(preg_replace('/\s+/u', ' ', $name));

        return mb_substr($name, 0, 120);
    }

    public function getMeta($key, $default = '')
    {
        $class = __NAMESPACE__ . '\MailBox';

        $meta = Meta::where('object_type', $class)
            ->where('object_id', $this->id)
            ->where('key', $key)
            ->first();

        if($meta) {
            return Helper::safeUnserialize($meta->value);
        }

        return $default;
    }

    public function saveMeta($key, $value)
    {
        $class = __NAMESPACE__ . '\MailBox';

        $meta = Meta::where('object_type', $class)
            ->where('object_id', $this->id)
            ->where('key', $key)
            ->first();

        if($meta) {
            $meta->value = maybe_serialize($value);
            $meta->save();
            return true;
        }

        Meta::insert([
            'object_type' => $class,
            'object_id' => $this->id,
            'key' => $key,
            'value' => maybe_serialize($value)
        ]);
        return true;
    }

    public function deleteMeta($key)
    {
        $class = __NAMESPACE__ . '\MailBox';

        $meta = Meta::where('object_type', $class)
            ->where('object_id', $this->id)
            ->where('key', $key)
            ->delete();
    }

    public function deleteAllMeta()
    {
        $class = __NAMESPACE__ . '\MailBox';

        $meta = Meta::where('object_type', $class)
            ->where('object_id', $this->id)
            ->delete();
    }

}
