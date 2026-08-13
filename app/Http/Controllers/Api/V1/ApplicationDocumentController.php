<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\LoanApplication;
use App\Models\LoanDocument;
use App\Services\ApplicationComplianceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ApplicationDocumentController extends ApiController
{
    private const TYPES = ['member_identity', 'guarantor_identity', 'local_government_letter', 'business_license', 'house_lease', 'other'];

    public function index(LoanApplication $loanApplication)
    {
        return response()->json(['success' => true, 'data' => $loanApplication->documents()->with(['uploader', 'verifier'])->latest()->get()->map(
            fn (LoanDocument $document) => $this->shape($loanApplication, $document)
        )]);
    }

    public function store(Request $request, LoanApplication $loanApplication, ApplicationComplianceService $service)
    {
        $data = $request->validate([
            'document_type' => ['required', Rule::in(self::TYPES)],
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'is_required' => ['sometimes', 'boolean'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ]);
        $document = $service->addDocument($loanApplication, $data['document_type'], $request->file('file'), $request->user(), $request->boolean('is_required'), $data['remarks'] ?? null);

        return response()->json(['success' => true, 'data' => $this->shape($loanApplication, $document->load(['uploader', 'verifier']))], 201);
    }

    public function verify(Request $request, LoanApplication $loanApplication, LoanDocument $loanDocument, ApplicationComplianceService $service)
    {
        $this->belongsTo($loanApplication, $loanDocument);
        $data = $request->validate([
            'decision' => ['required', Rule::in(['verified', 'rejected'])],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ]);
        $document = $service->verifyDocument($loanDocument, $request->user(), $data['decision'], $data['remarks'] ?? null);

        return response()->json(['success' => true, 'data' => $this->shape($loanApplication, $document->load(['uploader', 'verifier']))]);
    }

    public function download(LoanApplication $loanApplication, LoanDocument $loanDocument)
    {
        $this->belongsTo($loanApplication, $loanDocument);
        abort_unless(Storage::disk('local')->exists($loanDocument->file_path), 404, 'Document file not found.');

        return Storage::disk('local')->download($loanDocument->file_path, $loanDocument->original_name ?: basename($loanDocument->file_path));
    }

    private function belongsTo(LoanApplication $application, LoanDocument $document): void
    {
        abort_unless($document->loan_application_id === $application->id, 404);
    }

    private function shape(LoanApplication $application, LoanDocument $document): array
    {
        return [
            'id' => $document->id,
            'document_type' => $document->document_type,
            'file_name' => $document->original_name ?: basename($document->file_path),
            'mime_type' => $document->mime_type,
            'size_bytes' => $document->size_bytes,
            'is_required' => $document->is_required,
            'verification_status' => $document->verification_status,
            'remarks' => $document->remarks,
            'verification_remarks' => $document->verification_remarks,
            'download_url' => route('api.loan-applications.documents.download', [$application, $document]),
            'uploaded_by' => $document->uploader ? ['id' => $document->uploader->id, 'name' => $document->uploader->name] : null,
            'uploaded_at' => $document->created_at?->toIso8601String(),
            'verified_by' => $document->verifier ? ['id' => $document->verifier->id, 'name' => $document->verifier->name] : null,
            'verified_at' => $document->verified_at?->toIso8601String(),
        ];
    }
}
