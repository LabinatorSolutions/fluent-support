<?php


namespace FluentSupport\App\Models;

class TicketType extends Tag
{
    protected static $type = 'ticket_type';

    public static function boot()
    {
        static::creating(function ($model) {
            $model->tag_type = static::$type;
            if (empty($model->created_by) && $userId = get_current_user_id()) {
                $model->created_by = $userId;
            }

            $model->slug = static::slugify($model->title);
        });

        static::addGlobalScope(function ($builder) {
            $builder->where('tag_type', static::$type);
        });
    }


    public static function slugify($title)
    {
        $slug = sanitize_title($title, 'ticket-tag', 'display');
        if (TicketType::where('slug', $slug)->first()) {
            $slug .= '-' . time();
        }
        return $slug;
    }

}
