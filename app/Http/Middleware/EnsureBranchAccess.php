<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class EnsureBranchAccess
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (! $user || $user->hasAnyRole(['super_admin', 'head_office_admin']) || ! $user->branch_id) {
            return $next($request);
        }

        foreach ($request->route()?->parameters() ?? [] as $parameter) {
            if ($parameter instanceof Model) {
                $branchId = $parameter->branch_id ?? $parameter->loan?->branch_id ?? $parameter->member?->branch_id;
                abort_if($branchId && (int) $branchId !== (int) $user->branch_id, 403, 'You cannot access another branch.');
            }
        }
        if ($request->filled('branch_id')) {
            abort_if((int) $request->integer('branch_id') !== (int) $user->branch_id, 403, 'You cannot access another branch.');
        }

        return $next($request);
    }
}
