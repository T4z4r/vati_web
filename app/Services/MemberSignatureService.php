<?php

namespace App\Services;

use App\Models\Member;
use App\Models\MemberDocument;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class MemberSignatureService
{
    public function store(Member $member, UploadedFile $file, ?string $description, User $user): MemberDocument
    {
        $info = @getimagesize($file->getRealPath());
        if (! $info || $info[2] !== IMAGETYPE_PNG || $info[0] < 1 || $info[1] < 1
            || $info[0] * $info[1] > config('signatures.max_pixels')
            || $file->getSize() > config('signatures.max_kilobytes') * 1024) {
            throw ValidationException::withMessages(['file' => 'Upload a valid PNG within the signature size and pixel limits.']);
        }
        $decoded = @imagecreatefrompng($file->getRealPath());
        if ($decoded === false) {
            throw ValidationException::withMessages(['file' => 'The signature PNG is corrupt.']);
        }
        imagedestroy($decoded);

        $bytes = file_get_contents($file->getRealPath());
        $hash = hash('sha256', $bytes);
        $path = 'members/'.$member->id.'/signatures/'.Str::uuid().'.png';
        $disk = Storage::disk('signatures');
        try {
            if (! $disk->put($path, $bytes)) {
                throw new \RuntimeException('Signature storage failed.');
            }
            $document = DB::transaction(function () use ($member, $user, $description, $hash, $path, $bytes) {
                Member::whereKey($member->id)->lockForUpdate()->firstOrFail();
                $existing = $member->documents()->where('document_type', 'signature')->first();
                if ($existing) {
                    abort_unless(hash_equals((string) $existing->sha256, $hash), 409, 'A different signature already exists for this member.');
                    return $existing;
                }
                $document = $member->documents()->create([
                    'document_type' => 'signature', 'disk' => 'signatures', 'file_path' => $path,
                    'file_name' => 'sahihi-'.$member->id.'.png', 'mime_type' => 'image/png',
                    'file_size' => strlen($bytes), 'sha256' => $hash, 'description' => $description,
                    'uploaded_by' => $user->id, 'status' => 'uploaded', 'active_signature_member_id' => $member->id,
                ]);
                activity()->causedBy($user)->performedOn($member)->withProperties(['document_id' => $document->id])->log('Member signature uploaded');
                return $document;
            });
        } catch (Throwable $e) {
            $this->discard($path);
            throw $e;
        }
        if (! $document->wasRecentlyCreated) {
            $this->discard($path);
        }
        return $document;
    }

    public function delete(Member $member, MemberDocument $document, User $user): void
    {
        DB::transaction(function () use ($member, $document, $user) {
            Member::whereKey($member->id)->lockForUpdate()->firstOrFail();
            abort_if(DB::table('loan_group_witnesses')->where('signature_document_id', $document->id)->exists(), 409, 'This signature is retained as loan signing evidence.');
            $document->update(['active_signature_member_id' => null, 'status' => 'deleted', 'file_cleanup_pending' => true]);
            $document->delete();
            activity()->causedBy($user)->performedOn($member)->withProperties(['document_id' => $document->id])->log('Member signature deleted');
        });
        $this->cleanup($document);
    }

    public function cleanup(MemberDocument $document): void
    {
        try {
            $disk = Storage::disk($document->disk);
            if (! $disk->exists($document->file_path) || $disk->delete($document->file_path)) {
                $document->update(['file_cleanup_pending' => false]);
            }
        } catch (Throwable $e) {
            report($e); // The retained row allows the scheduled task to retry.
        }
    }

    private function discard(string $path): void
    {
        try {
            Storage::disk('signatures')->delete($path);
        } catch (Throwable $e) {
            report($e); // Stale untracked files are removed by scheduled cleanup.
        }
    }
}
