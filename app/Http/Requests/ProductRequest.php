<?php

namespace FluentSupport\App\Http\Requests;

use FluentSupport\Framework\Foundation\RequestGuard;

class ProductRequest extends RequestGuard
{
    /**
     * @return Array
     */
    public function rules()
    {
        return [
            'title' => 'required'
        ];
    }

    /**
     * @return Array
     */
    public function messages()
    {
        return [
            'title.required' => 'Product Title is required'
        ];
    }

    public function sanitize()
    {
        $data = $this->all();

        $data['title'] = sanitize_text_field($data['title']);
        $data['description'] = sanitize_text_field($data['description']);

        return $data;
    }
}
