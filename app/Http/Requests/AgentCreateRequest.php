<?php

namespace FluentSupport\App\Http\Requests;

use FluentSupport\Framework\Foundation\RequestGuard;

class AgentCreateRequest extends RequestGuard
{
    public function updateAgent()
    {
        return [
            'email' => 'required|email',
            'first_name' => 'required',
            'last_name'  => 'required',
        ];
    }

    public function rules()
    {
        return [
            'email' => 'required|email',
        ];
    }

    public function messages()
    {
        return [
            'email.required' => 'Email is required',
            'first_name.required' => 'First name is required',
            'last_name.required' => 'Last name is required',
        ];
    }
}
