<?php

namespace FluentSupport\App\Http\Controllers;

use FluentSupport\App\Models\Customer;
use FluentSupport\App\Models\Product;
use FluentSupport\App\Models\Conversation;
use FluentSupport\App\Models\Ticket;
use FluentSupport\Framework\Request\Request;
use FluentSupport\Framework\Support\Arr;

class ProductController extends Controller
{
    /**
     * index method will return the list of product
     * @param Request $request
     * @return array
     */
    public function index(Request $request)
    {
        $products = Product::orderBy('id', 'DESC')->searchBy($request->get('search'))->paginate();

        return [
            'products' => $products
        ];
    }

    /**
     * get method will get product by id and return
     * @param Request $request
     * @param $productId
     * @return array
     */
    public function get(Request $request, $productId)
    {
        $product = Product::findOrFail($productId);
        return [
            'product' => $product
        ];
    }

    /**
     * creare method will create new product
     * @param Request $request
     * @return array
     * @throws \FluentSupport\Framework\Validator\ValidationException
     */
    public function create(Request $request)
    {
        $data = $request->all();
        $this->validate($data, [
            'title' => 'required'
        ]);

        $data = wp_unslash($data);
        $product = Product::create($data);

        return [
            'message' => __('Product has been successfully created', 'fluent-support'),
            'product' => $product
        ];
    }


    /**
     * update methd will update an exiting product by id
     * @param Request $request
     * @param $productId
     * @return array
     * @throws \FluentSupport\Framework\Validator\ValidationException
     */
    public function update(Request $request, $productId)
    {
        $data = $request->all();
        $this->validate($data, [
            'title' => 'required'
        ]);

        $product = Product::findOrFail($productId);
        $product->fill($data);
        $product->save();

        return [
            'message' => __('Product has been updated', 'fluent-support'),
            'product' => Product::find($productId)
        ];
    }

    /**
     * delete method will delete an existing product by id
     * @param Request $request
     * @param $productId
     * @return array
     */
    public function delete(Request $request, $productId)
    {
        Product::where('id', $productId)
            ->delete();

        return [
            'message' => __('Product has been deleted', 'fluent-support')
        ];
    }

}
