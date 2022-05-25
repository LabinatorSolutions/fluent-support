<?php

namespace FluentSupport\App\Http\Requests;

use FluentSupport\Framework\Foundation\RequestGuard;

class AgentCreateRequest extends RequestGuard
{
    public function rules()
    {
        return [
        	'email' => 'required|email'
        ];
    }

    public function messages()
    {
        return [];
    }
}
