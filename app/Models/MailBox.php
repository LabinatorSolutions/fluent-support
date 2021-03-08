<?php

namespace FluentSupport\App\Models;

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
        static::creating(function ($model) {
            $model->slug = static::slugify($model->name);
            $model->settings = $model->settings ?: [
                'hosted_page_id'        => '',
                'non_logged_in_message' => '',
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
        return \maybe_unserialize($this->attributes['settings']);
    }

    public static function slugify($title)
    {
        $slug = sanitize_title($title, 'business', 'display');
        if (MailBox::where('slug', $slug)->first()) {
            $slug .= '-' . time();
        }
        return $slug;
    }

    public function getMailerSettings()
    {
        return [
            'from_email' => $this->email,
            'from_name' => $this->name,
            'reply_to_email' => $this->email,
        ];
    }

    public function getMeta($key, $default = '')
    {
        $class = __NAMESPACE__ . '\MailBox';

        $meta = Meta::where('object_type', $class)
            ->where('object_id', $this->id)
            ->where('key', $key)
            ->first();

        if($meta) {
            return maybe_unserialize($meta->value);
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
}
