<?php

namespace FluentSupport\App\Models;

class Agent extends Person
{
    protected static $type = 'agent';

    protected $searchable = [
        'id',
        'first_name',
        'last_name',
        'email',
        'address_line_1',
        'address_line_2',
        'country'
    ];

    /**
     * @return array
     */
    public function getSearchableFields(){
        return $this->searchable;
    }

    /**
     * Agent ids, cached per request. Reporting filters on these ids because
     * whereHas('person') hits fs_persons, which has no person_type index.
     *
     * @return array
     */
    public static function getAgentIds()
    {
        static $agentIds = null;

        if (is_null($agentIds)) {
            $agentIds = array_map('intval', static::query()->pluck('id')->all());
        }

        return $agentIds;
    }

    public static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->person_type = static::$type;
            $model->hash = md5(time().wp_generate_uuid4());
        });

        static::addGlobalScope(function ($builder) {
            $builder->where('person_type', 'agent');
        });
    }


    /**
     * @return Model Collection
     */
    public function tickets()
    {
        $foreign_key = self::$type . '_id';

        $class = __NAMESPACE__ . '\Ticket';

        return $this->hasMany(
            $class, $foreign_key, 'id'
        );
    }

    public function groups()
    {
        return $this->belongsToMany(
            AgentGroup::class, 'fs_tag_pivot', 'source_id', 'tag_id'
        )->wherePivot('source_type', 'agent_group');
    }

    public function scopeMentionBy($query, $search)
    {
        if (!$search) {
            return $query;
        }

        return $query->where(function ($q) use ($search) {
            $q->where('first_name', 'LIKE', '%' . $search . '%')
              ->orWhere('last_name', 'LIKE', '%' . $search . '%')
              ->orWhere('email', 'LIKE', '%' . $search . '%');
        });
    }

}
