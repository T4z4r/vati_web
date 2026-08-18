<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AuditLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
            'user_id' => 'nullable|integer|exists:users,id',
            'subject_type' => 'nullable|string',
            'search' => 'nullable|string|max:200',
            'log_name' => 'nullable|string',
        ]);

        $query = DB::table('activity_log')
            ->leftJoin('users', 'activity_log.causer_id', '=', 'users.id')
            ->select(
                'activity_log.id',
                'activity_log.log_name',
                'activity_log.description',
                'activity_log.subject_type',
                'activity_log.subject_id',
                'activity_log.event',
                'activity_log.properties',
                'activity_log.created_at',
                'users.id as user_id',
                'users.name as user_name',
                'users.email as user_email'
            );

        if ($request->filled('from')) {
            $query->where('activity_log.created_at', '>=', $request->from);
        }

        if ($request->filled('to')) {
            $query->where('activity_log.created_at', '<=', $request->to . ' 23:59:59');
        }

        if ($request->filled('user_id')) {
            $query->where('activity_log.causer_id', $request->user_id);
        }

        if ($request->filled('subject_type')) {
            $query->where('activity_log.subject_type', $request->subject_type);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('activity_log.description', 'LIKE', "%{$search}%")
                    ->orWhere('users.name', 'LIKE', "%{$search}%");
            });
        }

        if ($request->filled('log_name')) {
            $query->where('activity_log.log_name', $request->log_name);
        }

        $perPage = $request->integer('per_page', 25);
        $activities = $query->orderByDesc('activity_log.created_at')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $activities->items(),
            'pagination' => [
                'current_page' => $activities->currentPage(),
                'last_page' => $activities->lastPage(),
                'per_page' => $activities->perPage(),
                'total' => $activities->total(),
            ],
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $activity = DB::table('activity_log')
            ->leftJoin('users', 'activity_log.causer_id', '=', 'users.id')
            ->select(
                'activity_log.*',
                'users.name as user_name',
                'users.email as user_email'
            )
            ->where('activity_log.id', $id)
            ->first();

        if (!$activity) {
            return response()->json(['success' => false, 'message' => 'Activity log entry not found.'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $activity->id,
                'description' => $activity->description,
                'subject_type' => $activity->subject_type,
                'subject_id' => $activity->subject_id,
                'event' => $activity->event,
                'properties' => json_decode($activity->properties, true),
                'log_name' => $activity->log_name,
                'created_at' => $activity->created_at,
                'user' => $activity->user_id ? [
                    'id' => $activity->user_id,
                    'name' => $activity->user_name,
                    'email' => $activity->user_email,
                ] : null,
            ],
        ]);
    }
}
