<?php

namespace FluentSupport\App\Http\Controllers;

use FluentSupport\App\Models\TicketTag;
use FluentSupport\Framework\Request\Request;

class TicketTagsController extends Controller
{
    public function index(Request $request)
    {
        $products = TicketTag::paginate();

        return [
            'tags' => $products
        ];
    }

    public function get(Request $request, $tagId)
    {
        $product = TicketTag::findOrFail($tagId);
        return [
            'tags' => $product
        ];
    }

    public function create(Request $request)
    {
        $data = $request->all();
        $this->validate($data, [
            'title' => 'required'
        ]);

        $data = wp_unslash($data);
        $product = TicketTag::create($data);

        return [
            'message' => __('Tag has been successfully created', 'fluent-support'),
            'tag' => $product
        ];
    }

    public function update(Request $request, $tagId)
    {
        $data = $request->all();
        $this->validate($data, [
            'title' => 'required'
        ]);

        $product = TicketTag::findOrFail($tagId);
        $product->fill($data);
        $product->save();

        return [
            'message' => __('Tag has been updated', 'fluent-support'),
            'tag' => TicketTag::find($tagId)
        ];
    }

    public function delete(Request $request, $tagId)
    {
        TicketTag::where('id', $tagId)
            ->delete();

        return [
            'message' => __('Tag has been deleted', 'fluent-support')
        ];
    }

}
