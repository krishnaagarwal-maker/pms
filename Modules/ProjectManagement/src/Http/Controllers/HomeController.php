<?php

namespace Modules\ProjectManagement\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function index(Request $request)
    {
        return response()->json([
            'message' => 'Welcome to the ProjectManagement module!'
        ]);
    }
} 