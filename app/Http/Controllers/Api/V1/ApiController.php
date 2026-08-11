<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

abstract class ApiController extends Controller
{
    protected function branchScope(Builder $query, Request $request): Builder
    {
        $user = $request->user();
        if ($user && ! $user->hasAnyRole(['super_admin', 'head_office_admin']) && $user->branch_id) {
            $query->where($query->getModel()->qualifyColumn('branch_id'), $user->branch_id);
        }

        return $query;
    }

    protected function perPage(Request $request): int
    {
        return min(100, max(1, $request->integer('per_page', 20)));
    }
}
