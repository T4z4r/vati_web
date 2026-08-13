<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\LoanApplication;
use App\Models\LoanDocument;
use App\Services\ApplicationComplianceService;
use App\Services\LoanCancellationService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ApplicationComplianceController extends ApiController
{
    public function applicant(Request $request, LoanApplication $loanApplication, ApplicationComplianceService $service)
    {
        $request->validate([
            'accept_declaration' => ['accepted'],
            'applicant_signature' => ['required', 'image', 'max:5120'],
            'applicant_thumbprint' => ['required', 'image', 'max:5120'],
        ]);

        return response()->json(['success' => true, 'data' => $service->captureApplicant($loanApplication, [], $request->file('applicant_signature'), $request->file('applicant_thumbprint'), $request->ip())]);
    }

    public function guarantor(Request $request, LoanApplication $loanApplication, ApplicationComplianceService $service)
    {
        $data = $request->validate([
            'guarantor_type' => ['required', Rule::in(['family', 'non_family'])], 'name' => ['required', 'max:150'],
            'relationship' => ['required', 'max:100'], 'phone' => ['required', 'max:20'], 'national_id' => ['nullable', 'max:50'],
            'voter_id' => ['nullable', 'max:50'], 'house_number' => ['nullable', 'max:100'], 'street' => ['nullable', 'max:100'],
            'ward' => ['nullable', 'max:100'], 'district' => ['nullable', 'max:100'], 'region' => ['nullable', 'max:100'],
            'signature' => ['required', 'image', 'max:5120'], 'thumbprint' => ['required', 'image', 'max:5120'],
            'joint_photo' => ['required', 'image', 'max:10240'], 'accept_declaration' => ['accepted'],
        ]);
        unset($data['signature'], $data['thumbprint'], $data['joint_photo'], $data['accept_declaration']);

        return response()->json(['success' => true, 'data' => $service->addGuarantor($loanApplication, $data, $request->file('signature'), $request->file('thumbprint'), $request->file('joint_photo'))], 201);
    }

    public function nominees(Request $request, LoanApplication $loanApplication, ApplicationComplianceService $service)
    {
        $data = $request->validate(['nominees' => ['required', 'array', 'min:1'], 'nominees.*.name' => ['required', 'max:150'], 'nominees.*.relationship' => ['required', 'max:100'], 'nominees.*.percentage' => ['required', 'numeric', 'gt:0', 'max:100']]);
        $service->replaceNominees($loanApplication, $data['nominees']);

        return response()->json(['success' => true, 'data' => $loanApplication->member->nominees()->get()]);
    }

    public function document(Request $request, LoanApplication $loanApplication, ApplicationComplianceService $service)
    {
        $data = $request->validate(['document_type' => ['required', Rule::in(['member_identity', 'guarantor_identity', 'local_government_letter', 'business_license', 'house_lease', 'other'])], 'document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'], 'is_required' => ['sometimes', 'boolean']]);

        return response()->json(['success' => true, 'data' => $service->addDocument($loanApplication, $data['document_type'], $request->file('document'), $request->user(), $request->boolean('is_required'))], 201);
    }

    public function verifyDocument(Request $request, LoanApplication $loanApplication, LoanDocument $loanDocument, ApplicationComplianceService $service)
    {
        abort_unless($loanDocument->loan_application_id === $loanApplication->id, 404);
        $data = $request->validate(['decision' => ['required', Rule::in(['verified', 'rejected'])], 'remarks' => ['nullable', 'string']]);

        return response()->json(['success' => true, 'data' => $service->verifyDocument($loanDocument, $request->user(), $data['decision'], $data['remarks'] ?? null)]);
    }

    public function cancel(Request $request, LoanApplication $loanApplication, LoanCancellationService $service)
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:2000']]);
        $service->cancel($loanApplication, $request->user(), $data['reason'] ?? null);

        return response()->json(['success' => true, 'message' => 'Application cancelled.']);
    }
}
