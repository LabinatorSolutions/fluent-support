<?php

namespace FluentSupport\App\Http\Controllers;

use FluentSupport\Framework\Request\Request;

class TicketController extends Controller
{
    public function index(Request $request)
    {
        return $request->all();
    }
}
