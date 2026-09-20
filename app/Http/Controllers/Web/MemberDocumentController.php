<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\MemberDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Gate;

class MemberDocumentController extends Controller
{
    /**
     * Store a newly uploaded document.
     */
    public function store(Request $request, Member $member)
    {
        Gate::authorize('edit-members');

        $validated = $request->validate([
            'document_type' => ['required', 'string', 'in:national_id,voter_id,address_proof,business_license,passbook_scan,signature_card,other'],
            'file' => ['required', 'file', 'max:5120', 'mimes:pdf,jpg,jpeg,png,doc,docx'],
        ]);

        $file = $request->file('file');
        $fileName = time() . '_' . $member->id . '_' . $file->getClientOriginalName();
        $filePath = $file->storeAs('member_documents/' . $member->id, $fileName, 'public');

        MemberDocument::create([
            'member_id' => $member->id,
            'document_type' => $validated['document_type'],
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $filePath,
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'uploaded_by' => $request->user()->id,
        ]);

        activity()
            ->causedBy($request->user())
            ->performedOn($member)
            ->log('Document uploaded: ' . $validated['document_type']);

        return back()->with('success', 'Document uploaded successfully.');
    }

    /**
     * Delete a document.
     */
    public function destroy(Request $request, Member $member, MemberDocument $document)
    {
        Gate::authorize('delete-members');

        if ($document->member_id !== $member->id) {
            abort(403, 'Unauthorized action.');
        }

        $filePath = $document->file_path;
        if ($document->document_type === 'signature') {
            app(\App\Services\MemberSignatureService::class)->delete($member, $document, $request->user());
            return back()->with('success', 'Signature deleted successfully.');
        }
        $force = $request->boolean('_force');
        $force ? $document->forceDelete() : $document->delete();

        // Delete the file from storage
        if ($filePath && Storage::disk('public')->exists($filePath)) {
            Storage::disk('public')->delete($filePath);
        }

        activity()
            ->causedBy($request->user())
            ->performedOn($member)
            ->withProperties(['forced' => $force])
            ->log('Document ' . ($force ? 'permanently ' : '') . 'deleted: ' . $document->document_type);

        return back()->with('success', $force ? 'Document permanently deleted.' : 'Document deleted successfully.');
    }

    /**
     * Download a document.
     */
    public function download(Request $request, Member $member, MemberDocument $document)
    {
        if ($document->member_id !== $member->id) {
            abort(403, 'Unauthorized action.');
        }

        Gate::authorize('view-members');

        return Storage::disk($document->disk)->download($document->file_path, $document->file_name, ['Cache-Control' => 'private, no-store']);
    }

    public function view(Member $member, MemberDocument $document)
    {
        Gate::authorize('view-members');
        abort_unless((int) $document->member_id === (int) $member->id, 404);
        $disk = Storage::disk($document->disk);
        abort_unless($disk->exists($document->file_path), 404, 'Document file not found.');
        $mime = $disk->mimeType($document->file_path);
        $headers = ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff'];
        if (! in_array($mime, ['image/png', 'image/jpeg', 'application/pdf'], true)) {
            return $disk->download($document->file_path, $document->file_name, $headers);
        }

        return $disk->response($document->file_path, $document->file_name, [
            ...$headers, 'Content-Type' => $mime,
        ], 'inline');
    }
}
