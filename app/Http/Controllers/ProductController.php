<?php

namespace FluentSupport\App\Http\Controllers;

use FluentSupport\App\Models\Customer;
use FluentSupport\App\Models\Product;
use FluentSupport\App\Models\Response;
use FluentSupport\App\Models\Ticket;
use FluentSupport\Framework\Request\Request;
use FluentSupport\Framework\Support\Arr;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $products = Product::paginate();

        return [
            'products' => $products
        ];
    }

    public function get(Request $request, $productId)
    {
        $product = Product::findOrFail($productId);
        return [
            'product' => $product
        ];
    }

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

    public function delete(Request $request, $productId)
    {
        Product::where('id', $productId)
            ->delete();

        return [
            'message' => __('Product has been deleted', 'fluent-support')
        ];
    }

}
