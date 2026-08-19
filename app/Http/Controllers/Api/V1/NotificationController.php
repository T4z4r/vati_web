<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class NotificationController extends ApiController
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'read' => ['nullable', 'boolean'],
            'type' => ['nullable', 'string', 'max:255'],
        ]);
        $query = $request->user()->notifications();
        if (array_key_exists('read', $filters)) {
            $request->boolean('read') ? $query->whereNotNull('read_at') : $query->whereNull('read_at');
        }
        if (! empty($filters['type'])) {
            $query->where('data->type', $filters['type']);
        }
        $paginator = $query->latest()->paginate($this->perPage($request));
        $paginator->through(fn (DatabaseNotification $notification) => $this->shape($notification));

        return response()->json([
            'success' => true,
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(), 'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(), 'total' => $paginator->total(),
                'unread_count' => $request->user()->unreadNotifications()->count(),
            ],
            'links' => ['first' => $paginator->url(1), 'last' => $paginator->url($paginator->lastPage()), 'prev' => $paginator->previousPageUrl(), 'next' => $paginator->nextPageUrl()],
        ]);
    }

    public function read(Request $request, string $notification)
    {
        $item = $request->user()->notifications()->whereKey($notification)->firstOrFail();
        $item->markAsRead();

        return response()->json(['success' => true, 'data' => $this->shape($item->refresh())]);
    }

    public function readAll(Request $request)
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json(['success' => true, 'message' => 'All notifications marked as read.']);
    }

    private function shape(DatabaseNotification $notification): array
    {
        return [
            'id' => $notification->id,
            'type' => $notification->data['type'] ?? class_basename($notification->type),
            'title' => $notification->data['title'] ?? null,
            'message' => $notification->data['message'] ?? null,
            'resource_type' => $notification->data['resource_type'] ?? null,
            'resource_id' => $notification->data['resource_id'] ?? null,
            'read_at' => $notification->read_at?->toIso8601String(),
            'created_at' => $notification->created_at?->toIso8601String(),
        ];
    }
}
