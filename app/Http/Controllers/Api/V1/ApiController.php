<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

abstract class ApiController extends Controller
{
    protected function perPage(Request $request): int
    {
        return min(100, max(1, $request->integer('per_page', 20)));
    }
}
