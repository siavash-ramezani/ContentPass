<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class AdminController extends Controller
{
    public function ping(): JsonResponse
    {
        return response()->json(['status' => 'ok', 'role' => 'admin']);
    }
}
