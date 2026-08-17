<?php

namespace App\Services;

use App\Enums\ApplicationStatus;
use App\Models\LoanApplication;
use App\Models\LoanDocument;
use App\Models\LoanTerm;
use App\Models\User;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class ApplicationComplianceService
{
    public const GUARANTOR_DECLARATION = 'I accept responsibility for repayment of this loan if the applicant defaults, subject to the signed loan agreement and applicable law.';

    public function captureApplicant(LoanApplication $application, array $data, ?UploadedFile $signature, ?UploadedFile $thumbprint, string $ip): LoanApplication
    {
        $this->ensureDraft($application);
        $term = LoanTerm::query()->where('is_active', true)->whereDate('effective_from', '<=', today())->where(fn ($q) => $q->whereNull('effective_until')->orWhereDate('effective_until', '>=', today()))->latest('effective_from')->firstOrFail();
        $application->update([
            'loan_term_id' => $term->id,
            'consent_declaration' => $term->body,
            'consented_at' => now(),
            'consented_ip' => $ip,
            'cancellation_deadline' => now()->addDays(3),
            'applicant_signature_path' => $signature?->store('loan-compliance/applicants') ?? $application->applicant_signature_path,
            'applicant_thumbprint_path' => $thumbprint?->store('loan-compliance/applicants') ?? $application->applicant_thumbprint_path,
        ]);

        activity()->performedOn($application)->withProperties(['term_version' => $term->version])->log('Applicant declaration accepted');

        return $application->refresh();
    }

    public function addGuarantor(LoanApplication $application, array $data, UploadedFile $signature, UploadedFile $thumbprint, UploadedFile $jointPhoto)
    {
        $this->ensureDraft($application);

        return $application->guarantors()->create([
            ...$data,
            'signature_path' => $signature->store('loan-compliance/guarantors'),
            'thumbprint_path' => $thumbprint->store('loan-compliance/guarantors'),
            'joint_photo_path' => $jointPhoto->store('loan-compliance/guarantors'),
            'declaration_text' => self::GUARANTOR_DECLARATION,
            'declaration_accepted_at' => now(),
        ]);
    }

    public function replaceNominees(LoanApplication $application, array $nominees): void
    {
        $this->ensureDraft($application);
        $total = round((float) collect($nominees)->sum('percentage'), 2);
        if (abs($total - 100) > 0.009) {
            throw new DomainException('Nominee allocations must total exactly 100%.');
        }

        DB::transaction(function () use ($application, $nominees) {
            $application->member->nominees()->delete();
            foreach ($nominees as $nominee) {
                $application->member->nominees()->create([...$nominee, 'attested_at' => now()]);
            }
        });
    }

    public function addDocument(LoanApplication $application, string $type, UploadedFile $file, User $user, bool $required = false, ?string $remarks = null): LoanDocument
    {
        $this->ensureDraft($application);

        return $application->documents()->create([
            'document_type' => $type,
            'is_required' => $required,
            'file_path' => $file->store('loan-compliance/documents'),
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size_bytes' => $file->getSize(),
            'remarks' => $remarks,
            'verification_status' => 'pending',
            'uploaded_by' => $user->id,
        ]);
    }

    public function verifyDocument(LoanDocument $document, User $user, string $decision, ?string $remarks): LoanDocument
    {
        $document->update([
            'verification_status' => $decision,
            'verification_remarks' => $remarks,
            'verified_by' => $user->id,
            'verified_at' => now(),
        ]);

        return $document->refresh();
    }

    public function assertReadyForSubmission(LoanApplication $application): void
    {
        $application->loadMissing('member.nominees');
        if (abs((float) $application->member->nominees->sum('percentage') - 100) > 0.009) {
            throw new DomainException('Nominee allocations must total exactly 100%.');
        }
    }

    public function assertReadyForApproval(LoanApplication $application): void
    {
    }

    private function ensureDraft(LoanApplication $application): void
    {
        if (! in_array($application->status, [ApplicationStatus::DRAFT, ApplicationStatus::RETURNED], true)) {
            throw new DomainException('Compliance evidence can only be changed while the application is a draft or returned for correction.');
        }
    }
}
