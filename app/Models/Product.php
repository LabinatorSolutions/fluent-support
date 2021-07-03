<?php

namespace FluentSupport\App\Models;

class Product extends Model
{
    protected $table = 'fs_products';


    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'source_uid',
        'title',
        'description',
        'settings',
        'source',
        'created_by'
    ];


}
