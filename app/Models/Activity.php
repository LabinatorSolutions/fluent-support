<?php

namespace FluentSupport\App\Models;

class Activity extends Model
{
    protected $table = 'fs_activities';

    protected $fillable = ['person_id', 'person_type', 'event_type', 'object_id', 'object_type', 'description'];

    public function person()
    {
        $class = __NAMESPACE__ . '\Person';

        return $this->belongsTo(
            $class, 'person_id', 'id'
        );
    }
}
