<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanDocument;
use App\Models\Member;
use App\Services\ApplicationComplianceService;
use App\Services\LoanAdministrationService;
use App\Services\LoanCancellationService;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ComplianceController extends Controller
{
    public function applicant(Request $request, LoanApplication $loanApplication, ApplicationComplianceService $service)
    {
        $request->validate(['accept_declaration' => ['accepted'], 'applicant_signature' => ['required', 'image', 'max:5120'], 'applicant_thumbprint' => ['required', 'image', 'max:5120']]);

        return $this->run(fn () => $service->captureApplicant($loanApplication, [], $request->file('applicant_signature'), $request->file('applicant_thumbprint'), $request->ip()), 'Applicant declaration and biometric evidence captured.');
    }

    public function guarantor(Request $request, LoanApplication $loanApplication, ApplicationComplianceService $service)
    {
        $data = $request->validate([
            'guarantor_type' => ['required', Rule::in(['family', 'non_family'])], 'name' => ['required', 'max:150'], 'relationship' => ['required', 'max:100'],
            'phone' => ['required', 'max:20'], 'national_id' => ['nullable', 'max:50'], 'signature' => ['required', 'image', 'max:5120'],
            'thumbprint' => ['required', 'image', 'max:5120'], 'joint_photo' => ['required', 'image', 'max:10240'], 'accept_declaration' => ['accepted'],
        ]);
        $evidence = [$request->file('signature'), $request->file('thumbprint'), $request->file('joint_photo')];
        unset($data['signature'], $data['thumbprint'], $data['joint_photo'], $data['accept_declaration']);

        return $this->run(fn () => $service->addGuarantor($loanApplication, $data, ...$evidence), 'Guarantor declaration captured.');
    }

    public function nominees(Request $request, LoanApplication $loanApplication, ApplicationComplianceService $service)
    {
        $data = $request->validate(['nominees' => ['required', 'array', 'min:1'], 'nominees.*.name' => ['required', 'max:150'], 'nominees.*.relationship' => ['required', 'max:100'], 'nominees.*.percentage' => ['required', 'numeric', 'gt:0', 'max:100']]);

        return $this->run(fn () => $service->replaceNominees($loanApplication, $data['nominees']), 'Nominees saved with a 100% allocation.');
    }

    public function document(Request $request, LoanApplication $loanApplication, ApplicationComplianceService $service)
    {
        $data = $request->validate(['document_type' => ['required', Rule::in(['member_identity', 'guarantor_identity', 'local_government_letter', 'business_license', 'house_lease', 'other'])], 'document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240']]);

        return $this->run(fn () => $service->addDocument($loanApplication, $data['document_type'], $request->file('document'), $request->user()), 'Checklist document uploaded.');
    }

    public function verifyDocument(Request $request, LoanApplication $loanApplication, LoanDocument $loanDocument, ApplicationComplianceService $service)
    {
        abort_unless($loanDocument->loan_application_id === $loanApplication->id, 404);
        $data = $request->validate(['decision' => ['required', Rule::in(['verified', 'rejected'])], 'remarks' => ['nullable', 'string']]);

        return $this->run(fn () => $service->verifyDocument($loanDocument, $request->user(), $data['decision'], $data['remarks'] ?? null), 'Document verification recorded.');
    }

    public function cancel(Request $request, LoanApplication $loanApplication, LoanCancellationService $service)
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:2000']]);

        return $this->run(fn () => $service->cancel($loanApplication, $request->user(), $data['reason'] ?? null), 'Application cancelled within the cooling-off period.');
    }

    public function passbook(Request $request, Member $member, LoanAdministrationService $service)
    {
        $data = $request->validate(['reason' => ['required', Rule::in(['lost', 'damaged'])], 'payment_reference' => ['required', 'max:100'], 'remarks' => ['nullable', 'string']]);

        return $this->run(fn () => $service->replacePassbook($member, $request->user(), $data), 'Duplicate passbook issued after collecting TZS 1,000.');
    }

    public function defaultNotice(Request $request, Loan $loan, LoanAdministrationService $service)
    {
        $data = $request->validate(['delivery_method' => ['required', Rule::in(['hand', 'sms', 'email', 'registered_mail'])], 'delivery_reference' => ['nullable', 'max:150']]);

        return $this->run(fn () => $service->issueDefaultNotice($loan, $request->user(), $data), 'Fourteen-day default notice issued.');
    }

    public function clearance(Request $request, Loan $loan, LoanAdministrationService $service)
    {
        $data = $request->validate(['comments' => ['nullable', 'string'], 'manager_signature' => ['required', 'image', 'max:5120']]);

        return $this->run(fn () => $service->authorizeClearance($loan, $request->user(), $data, $request->file('manager_signature')), 'Loan clearance authorized and signed.');
    }

    private function run(callable $callback, string $message)
    {
        try {
            $callback();
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $message);
    }
}
