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

    protected $searchable = ['title', 'description'];

    /**
     * Local scope to filter products by search/query string
     * @param ModelQueryBuilder $query
     * @param string $search
     * @return ModelQueryBuilder
     */

    public function scopeSearchBy($query, $search)
    {
        if ($search) {
            $fields = $this->searchable;
            $query->where(function ($query) use ($fields, $search) {
                $query->where(array_shift($fields), 'LIKE', "%$search%");

                foreach ($fields as $field) {
                    $query->orWhere($field, 'LIKE', "$search%");
                }
            });
        }

        return $query;
    }


}
