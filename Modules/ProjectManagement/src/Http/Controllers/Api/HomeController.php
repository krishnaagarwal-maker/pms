<?php

namespace Modules\ProjectManagement\Http\Controllers\Api;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function index(Request $request)
    {
        return response()->json([
            'message' => 'Welcome to the ProjectManagement API!'
        ]);
    }
} 