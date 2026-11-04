<?php

namespace FluentSupport\App\Api\Classes;

use FluentSupport\App\Models\Product;

/**
 * Products PHP API.
 *
 * Example Usage: $productApi = FluentSupportApi('products');
 */
class Products
{
    private $instance = null;

    private $allowedInstanceMethods = [
        'all',
        'get',
        'find',
        'first',
        'paginate'
    ];

    public function __construct(Product $instance)
    {
        $this->instance = $instance;
    }

    public function getProducts()
    {
        return Product::paginate();
    }

    /**
     * @param int $id
     */
    public function getProduct(int $id)
    {
        if (!$id) {
            return;
        }

        return Product::findOrFail($id);
    }

    /**
     * @param array $data
     */
    public function createProduct(array $data)
    {
        if (empty($data['title'])) {
            return;
        }
        return Product::create(wp_unslash($data));
    }

    /**
     * @param int $id
     * @param array $data
     */
    public function updateProduct(int $id, array $data)
    {
        if (!$id || !$data) {
            return;
        }
        return Product::findOrFail($id)->update($data);
    }

    /**
     * @param int $id
     */
    public function deleteProduct(int $id)
    {
        if (!$id) {
            return;
        }
        return Product::findOrFail($id)->delete();
    }

    public function getInstance()
    {
        return $this->instance;
    }

    public function __call($method, $params)
    {
        if (in_array($method, $this->allowedInstanceMethods)) {
            return call_user_func_array([$this->instance, $method], $params);
        }

        throw new \Exception(sprintf('Method %s does not exist.', esc_html($method)));
    }
}
