<?php

namespace App\Http\Requests;

use App\Models\MemberGroup;
use Illuminate\Foundation\Http\FormRequest;

class OnboardMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'branch_id' => ['required', 'exists:branches,id'],
            'group_id' => ['required', 'exists:member_groups,id'],
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'guardian_name' => ['nullable', 'string', 'max:150'],
            'phone' => ['required', 'string', 'max:20', 'unique:members,phone'],
            'alternate_phone' => ['nullable', 'string', 'max:20'],
            'national_id' => ['nullable', 'string', 'max:50', 'unique:members,national_id'],
            'voter_id' => ['nullable', 'string', 'max:50'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', 'string', 'max:30'],
            'marital_status' => ['nullable', 'string', 'max:30'],
            'occupation' => ['nullable', 'string', 'max:100'],
            'nationality' => ['nullable', 'string', 'max:100'],
            'physical_address' => ['nullable', 'string'],
            'region' => ['nullable', 'string', 'max:100'],
            'district' => ['nullable', 'string', 'max:100'],
            'ward' => ['nullable', 'string', 'max:100'],
            'street' => ['nullable', 'string', 'max:100'],
            'admission_date' => ['nullable', 'date'],
            'passbook_issue_date' => ['nullable', 'date', 'after_or_equal:admission_date'],
            'kyc' => ['nullable', 'array'],
            'kyc.mpesa_phone' => ['nullable', 'string', 'max:20'],
            'kyc.bank_account_number' => ['nullable', 'string', 'max:100'],
            'kyc.bank_account_name' => ['nullable', 'string', 'max:150'],
            'kyc.bank_name' => ['nullable', 'string', 'max:150'],
            'kyc.house_number' => ['nullable', 'string', 'max:100'],
            'kyc.police_station' => ['nullable', 'string', 'max:150'],
            'kyc.business_name' => ['nullable', 'string', 'max:150'],
            'kyc.business_type' => ['nullable', 'string', 'max:150'],
            'kyc.business_address' => ['nullable', 'string'],
            'kyc.household_monthly_income' => ['nullable', 'numeric', 'min:0'],
            'kyc.household_monthly_expenses' => ['nullable', 'numeric', 'min:0'],
            'kyc.number_of_dependants' => ['nullable', 'integer', 'min:0'],
            'kyc.head_of_household' => ['nullable', 'string', 'max:150'],
            'kyc.house_ownership_status' => ['nullable', 'string', 'max:100'],
            'kyc.house_roof_type' => ['nullable', 'string', 'max:100'],
            'kyc.house_fence_type' => ['nullable', 'string', 'max:100'],
            'nominees' => ['nullable', 'array', 'min:1'],
            'nominees.*.name' => ['required', 'string', 'max:150'],
            'nominees.*.relationship' => ['required', 'string', 'max:100'],
            'nominees.*.percentage' => ['required', 'numeric', 'gt:0', 'max:100'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $group = MemberGroup::find($this->input('group_id'));
            if ($group && ! $group->status) {
                $validator->errors()->add('group_id', 'The selected group is inactive.');
            }
            if ($group && (int) $group->branch_id !== (int) $this->input('branch_id')) {
                $validator->errors()->add('group_id', 'The selected group does not belong to the selected branch.');
            }
            if ($this->has('nominees') && abs((float) collect($this->input('nominees'))->sum('percentage') - 100) > 0.009) {
                $validator->errors()->add('nominees', 'Nominee allocations must total exactly 100%.');
            }
        });
    }
}
