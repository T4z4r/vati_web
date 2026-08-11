<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Member;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MemberPhotoController extends ApiController
{
    public function store(Request $request, Member $member)
    {
        $request->validate(['photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:min_width=200,min_height=200']]);
        $oldPath = $member->photo_path;
        $path = $request->file('photo')->store('members/photos', 'public');
        $member->update(['photo_path' => $path]);
        if ($oldPath) {
            Storage::disk('public')->delete($oldPath);
        }
        activity()->performedOn($member)->causedBy($request->user())->log('Member photo updated');

        return response()->json(['success' => true, 'data' => [
            'member_id' => $member->id,
            'photo_url' => Storage::disk('public')->url($path),
            'updated_at' => $member->fresh()->updated_at->toIso8601String(),
        ]]);
    }
}
