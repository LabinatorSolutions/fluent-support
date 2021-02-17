<?php

namespace FluentSupport\App\Models;

use FluentSupport\Framework\Database\Orm\Model as BaseModel;

class Model extends BaseModel
{
    protected $guarded = ['id', 'ID'];
}
