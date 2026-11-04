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
     * @param $query
     * @return mixed
     */
    public function scopeOrderedByTitle($query)
    {
        return $query->orderBy('title', 'ASC');
    }

    /**
     * @param string $search
     * @return array
     */
    public function getProducts ( $search )
    {
        return [
          'products' => $this->orderedByTitle()->searchBy($search)->paginate()
        ];
    }

    /**
     * @param int $productId
     * @return array
     */
    public function getProduct ( $productId )
    {
        return [
          'product' => $this->findOrFail($productId)
        ];
    }

    /**
     * @param array $data
     * @return array
     */
    public function createProduct ( $data )
    {
        $data = wp_unslash($data);
        $product = $this->create($data);

        return [
            'message' => __('Product has been successfully created', 'fluent-support'),
            'product' => $product
        ];
    }

    /**
     * @param int $productId
     * @param array $data
     * @return array
     */
    public function updateProduct ( $productId, $data )
    {
        $data = wp_unslash($data);
        $product = $this->findOrFail($productId);
        $product->fill($data);
        $product->save();

        return [
            'message' => __('Product has been updated', 'fluent-support'),
            'product' => $product
        ];
    }

    /**
     * @param int $productId
     * @return array
     */
    public function deleteProduct ( $productId )
    {
        $this->where('id', $productId)->delete();

        return [
            'message' => __('Product has been deleted', 'fluent-support')
        ];
    }

}
