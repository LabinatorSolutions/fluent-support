<?php

namespace FluentSupport\App\Models;

use FluentSupport\App\Scopes\AgentScope;

class Agent extends Person
{
    protected static $type = 'agent';


    public static function boot()
    {
        static::creating(function ($model) {
            $model->person_type = static::$type;
        });

        static::addGlobalScope(new AgentScope());
    }

    /**
     * One2Many: Customer has to many Click Tickets
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

}
