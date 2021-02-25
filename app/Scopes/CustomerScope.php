<?php

namespace FluentSupport\App\Scopes;

use FluentSupport\Framework\Database\Orm\Builder;
use FluentSupport\Framework\Database\Orm\ScopeInterface;

class CustomerScope implements ScopeInterface
{

    public function apply(Builder $builder)
    {
        $builder->where('person_type', 'customer');
    }

    public function remove(Builder $builder)
    {
        // TODO: Implement remove() method.
    }
}
