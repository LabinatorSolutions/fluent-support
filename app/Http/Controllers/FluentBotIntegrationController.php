<?php

namespace FluentSupport\App\Http\Controllers;


use FluentSupport\App\Models\Meta;
use FluentSupport\App\Models\Product;
use FluentSupport\Framework\Request\Request;

class FluentBotIntegrationController extends Controller
{

    public function getSettings()
    {
        $meta = Meta::where([
            'object_type' => 'fluent_bot_settings',
            'object_id'   => 1,
            'key'         => '_fs_fluent_bot_config'
        ])->first();

        $settings = $meta ? maybe_unserialize($meta->value) : [];

        $productItems = Product::all()->map(function ($product) {
            return [
                'id'    => $product->id,
                'title' => $product->title
            ];
        })->values()->all();

        return array_merge([
            'generalApiKey'    => '',
            'generalBotId'     => '',
            'isEnabled'        => false,
            'productMappings'  => [],
            'products'         => $productItems
        ], $settings, [
            'products' => $productItems
        ]);
    }

}
