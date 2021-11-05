<?php

namespace FluentSupport\App\Models;

class Activity extends Model
{
    protected $table = 'fs_activities';

    protected $fillable = ['person_id', 'person_type', 'event_type', 'object_id', 'object_type', 'description'];
}
