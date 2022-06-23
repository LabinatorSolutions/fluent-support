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
     * @param Product $product
     * @return array
     */
    public function index(Request $request, Product $product)
    {
      return $product->getProducts( $request->get('search') );
    }

    /**
     * get method will get product by id and return
     * @param Request $request
     * @param Product $product
     * @param $productId
     * @return array
     */
    public function get(Request $request, Product $product, $productId)
    {
        return $product->getProduct( $productId );
    }

    /**
     * creare method will create new product
     * @param Request $request
     * @param Product $product
     * @return array
     * @throws \FluentSupport\Framework\Validator\ValidationException
     */
    public function create( Request $request, Product $product )
    {
        $data = $request->all();
        $this->validate($data, [
            'title' => 'required'
        ]);

        return $product->createProduct( $data );
    }


    /**
     * update methd will update an exiting product by id
     * @param Request $request
     * @param Product $product
     * @param int $productId
     * @return array
     * @throws \FluentSupport\Framework\Validator\ValidationException
     */
    public function update ( Request $request, Product $product, $productId )
    {
        $data = $request->all();
        $this->validate($data, [
            'title' => 'required'
        ]);

        return $product->updateProduct( $productId, $data );
    }

    /**
     * delete method will delete an existing product by id
     * @param Request $request
     * @param Product $product
     * @param int $productId
     * @return array
     */
    public function delete(Request $request, Product $product, $productId)
    {
        return $product->deleteProduct( $productId );
    }
}
