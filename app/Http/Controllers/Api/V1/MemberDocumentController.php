<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Member;
use App\Models\MemberDocument;
use App\Services\MemberSignatureService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class MemberDocumentController extends ApiController
{
    private const TYPES = ['national_id', 'voter_id', 'address_proof', 'business_license', 'passbook_scan', 'signature_card', 'signature', 'other'];

    public function index(Member $member)
    {
        return response()->json(['success' => true, 'data' => $member->documents()->with('uploadedBy')->get()->map(fn (MemberDocument $document) => $this->shape($member, $document))]);
    }

    public function show(Member $member, MemberDocument $memberDocument)
    {
        $this->belongsTo($member, $memberDocument);

        return response()->json(['success' => true, 'data' => $this->shape($member, $memberDocument)]);
    }

    public function store(Request $request, Member $member)
    {
        $signature = $request->input('document_type') === 'signature';
        $data = $request->validate([
            'document_type' => ['required', Rule::in(self::TYPES)],
            'file' => $signature
                ? ['required', 'file', 'max:'.config('signatures.max_kilobytes'), 'mimes:png']
                : ['required', 'file', 'max:5120', 'mimes:pdf,jpg,jpeg,png,doc,docx'],
            'description' => ['nullable', 'string', $signature ? 'max:1000' : 'max:2000'],
        ]);
        $file = $request->file('file');
        if ($signature) {
            $document = app(MemberSignatureService::class)->store($member, $file, $data['description'] ?? null, $request->user());
            return response()->json(['success' => true, 'message' => 'Signature saved successfully.', 'data' => $this->shape($member, $document->load('uploadedBy'))], $document->wasRecentlyCreated ? 201 : 200);
        }
        $path = $file->store('member_documents/'.$member->id, 'public');
        $document = $member->documents()->create([
            'document_type' => $data['document_type'],
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'description' => $data['description'] ?? null,
            'uploaded_by' => $request->user()->id,
        ]);
        activity()->causedBy($request->user())->performedOn($member)->log('Member document uploaded');

        return response()->json(['success' => true, 'message' => 'Member document uploaded.', 'data' => $this->shape($member, $document->load('uploadedBy'))], 201);
    }

    public function download(Member $member, MemberDocument $memberDocument)
    {
        $this->belongsTo($member, $memberDocument);

        $disk = Storage::disk($memberDocument->disk);
        abort_unless($disk->exists($memberDocument->file_path), 404, 'Document file not found.');

        return $disk->download($memberDocument->file_path, $memberDocument->file_name, [
            'Content-Type' => $memberDocument->mime_type ?? 'application/octet-stream',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function view(Member $member, MemberDocument $memberDocument)
    {
        $this->belongsTo($member, $memberDocument);

        $disk = Storage::disk($memberDocument->disk);
        abort_unless($disk->exists($memberDocument->file_path), 404, 'Document file not found.');

        $mime = $disk->mimeType($memberDocument->file_path) ?: $memberDocument->mime_type ?: 'application/octet-stream';

        if (! in_array($mime, ['application/pdf', 'image/jpeg', 'image/png'], true)) {
            return $disk->download($memberDocument->file_path, $memberDocument->file_name, [
                'Cache-Control' => 'private, no-store',
            ]);
        }

        return $disk->response($memberDocument->file_path, $memberDocument->file_name, [
            'Content-Type' => $mime,
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ], 'inline');
    }

    public function destroy(Request $request, Member $member, MemberDocument $memberDocument)
    {
        $this->belongsTo($member, $memberDocument);
        if ($memberDocument->document_type === 'signature') {
            app(MemberSignatureService::class)->delete($member, $memberDocument, $request->user());
            return response()->noContent();
        }
        $path = $memberDocument->file_path;
        $force = $request->boolean('force');
        $force ? $memberDocument->forceDelete() : $memberDocument->delete();
        Storage::disk('public')->delete($path);
        activity()->causedBy($request->user())->performedOn($member)->withProperties(['forced' => $force, 'file_name' => $memberDocument->file_name])->log('Member document ' . ($force ? 'permanently ' : '') . 'deleted');

        return response()->noContent();
    }

    private function belongsTo(Member $member, MemberDocument $document): void
    {
        abort_unless((int) $document->member_id === (int) $member->id, 404);
    }

    private function shape(Member $member, MemberDocument $document): array
    {
        return [
            'id' => $document->id,
            'document_type' => $document->document_type,
            'document_type_label' => $document->getDocumentTypeLabel(),
            'file_name' => $document->file_name,
            'mime_type' => $document->mime_type,
            'file_size' => $document->file_size,
            'size_bytes' => $document->file_size,
            'status' => $document->status ?? 'uploaded',
            'created_at' => $document->created_at?->toIso8601String(),
            'description' => $document->description,
            'view_url' => route('api.members.documents.view', [$member, $document]),
            'download_url' => route('api.members.documents.download', [$member, $document]),
            'delete_url' => route('api.members.documents.destroy', [$member, $document]),
            'uploaded_by' => $document->uploadedBy ? ['id' => $document->uploadedBy->id, 'name' => $document->uploadedBy->name] : null,
            'uploaded_at' => $document->created_at?->toIso8601String(),
        ];
    }
}
