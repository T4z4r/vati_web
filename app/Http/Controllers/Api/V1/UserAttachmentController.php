<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\User;
use App\Models\UserAttachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class UserAttachmentController extends ApiController
{
    public function index(User $user)
    {
        return response()->json(['success' => true, 'data' => $user->attachments()->with('uploadedBy')->latest()->get()->map(fn (UserAttachment $attachment) => $this->shape($user, $attachment))]);
    }

    public function store(Request $request, User $user)
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'max:5120', 'mimes:pdf,jpg,jpeg,png,doc,docx'],
            'title' => ['nullable', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);
        $file = $request->file('file');
        $path = $file->store('user_attachments/'.$user->id, 'public');
        $attachment = $user->attachments()->create([
            'title' => $data['title'] ?? null,
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'description' => $data['description'] ?? null,
            'uploaded_by' => $request->user()->id,
        ]);
        activity()->useLog('users')->causedBy($request->user())->performedOn($user)->withProperties(['attachment_id' => $attachment->id, 'file_name' => $attachment->file_name])->log('User attachment uploaded');

        return response()->json(['success' => true, 'message' => 'Attachment uploaded.', 'data' => $this->shape($user, $attachment->load('uploadedBy'))], 201);
    }

    public function download(User $user, UserAttachment $userAttachment)
    {
        $this->belongsTo($user, $userAttachment);

        return Storage::disk('public')->download($userAttachment->file_path, $userAttachment->file_name);
    }

    public function destroy(Request $request, User $user, UserAttachment $userAttachment)
    {
        $this->belongsTo($user, $userAttachment);
        $path = $userAttachment->file_path;
        $fileName = $userAttachment->file_name;
        $force = $request->boolean('force');
        $force ? $userAttachment->forceDelete() : $userAttachment->delete();
        Storage::disk('public')->delete($path);
        activity()->useLog('users')->causedBy($request->user())->performedOn($user)->withProperties(['forced' => $force, 'file_name' => $fileName])->log('User attachment ' . ($force ? 'permanently ' : '') . 'deleted');

        return response()->noContent();
    }

    private function belongsTo(User $user, UserAttachment $attachment): void
    {
        abort_unless((int) $attachment->user_id === (int) $user->id, 404);
    }

    public static function shape(User $user, UserAttachment $attachment): array
    {
        return [
            'id' => $attachment->id,
            'title' => $attachment->title,
            'file_name' => $attachment->file_name,
            'mime_type' => $attachment->mime_type,
            'file_size' => $attachment->file_size,
            'human_readable_size' => $attachment->human_readable_size,
            'description' => $attachment->description,
            'download_url' => route('api.users.attachments.download', [$user, $attachment]),
            'uploaded_by' => $attachment->uploadedBy ? ['id' => $attachment->uploadedBy->id, 'name' => $attachment->uploadedBy->name] : null,
            'uploaded_at' => $attachment->created_at?->toIso8601String(),
        ];
    }
}
