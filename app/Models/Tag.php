<?php

namespace FluentSupport\App\Models;

class Tag extends Model
{
    protected $table = 'fs_taggables';

    protected $fillable = ['tag_type', 'title', 'slug', 'description', 'settings', 'created_by'];

    public function setSettingsAttribute($settings)
    {
        $this->attributes['settings'] = \maybe_serialize($settings);
    }

    public function getSettingsAttribute($value)
    {
        return \maybe_unserialize($this->attributes['settings']);
    }
}
