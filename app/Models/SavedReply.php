<?php

namespace FluentSupport\App\Models;

class SavedReply extends Model
{
    protected $table = 'fs_saved_replies';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'created_by',
        'product_id',
        'title',
        'content'
    ];

    /**
     * @var array
     */
    protected $searchable = [
        'title',
        'content'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'product_id' => 'integer'
    ];

    /**
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

    /**
     * @param ModelQueryBuilder $query
     * @param int $productId
     * @return ModelQueryBuilder
     */
    public function scopeProductBy($query, $productId)
    {
        if ($productId) {
            $query->where('product_id', $productId);
        }

        return $query;
    }

    /**
     * @return Model Collection
     */
    public function person()
    {
        $class = __NAMESPACE__ . '\Person';

        return $this->belongsTo(
            $class, 'created_by', 'id'
        );
    }

    /**
     * @return Model Collection
     */
    public function product()
    {
        $class = __NAMESPACE__ . '\Product';

        return $this->belongsTo(
            $class, 'product_id', 'id'
        );
    }

}
