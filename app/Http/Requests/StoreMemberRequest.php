<?php

namespace App\Http\Requests;

use App\Models\MemberGroup;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMemberRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->has('nominees')) {
            $this->merge([
                'nominees' => array_values(array_filter(
                    $this->input('nominees', []),
                    fn ($row) => filled($row['name'] ?? null)
                        || filled($row['relationship'] ?? null)
                        || (float) ($row['percentage'] ?? 0) > 0
                )),
            ]);
        }

        foreach (['family_members', 'assets'] as $collection) {
            if ($this->has($collection)) {
                $this->merge([
                    $collection => array_values(array_filter(
                        $this->input($collection, []),
                        fn ($row) => collect($row)->contains(fn ($value) => filled($value))
                    )),
                ]);
            }
        }
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $member = $this->route('member');
        $required = $member ? 'sometimes' : 'required';

        return [
            'branch_id' => [$required, 'exists:branches,id'],
            'group_id' => [$required, 'exists:member_groups,id'],
            'first_name' => [$required, 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => [$required, 'string', 'max:100'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:min_width=200,min_height=200'],
            'guardian_name' => ['nullable', 'string', 'max:100'],
            'phone' => [$required, 'string', 'max:20', Rule::unique('members', 'phone')->ignore($member)],
            'national_id' => ['nullable', 'string', 'max:50', Rule::unique('members', 'national_id')->ignore($member)],
            'voter_id' => ['nullable', 'string', 'max:50', Rule::unique('members', 'voter_id')->ignore($member)],
            'alternate_phone' => ['nullable', 'string', 'max:20'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', 'string', 'max:30'],
            'marital_status' => ['nullable', 'string', 'max:30'],
            'occupation' => ['nullable', 'string', 'max:100'],
            'nationality' => ['nullable', 'string', 'max:50'],
            'physical_address' => ['nullable', 'string'],
            'region' => ['nullable', 'string', 'max:100'],
            'district' => ['nullable', 'string', 'max:100'],
            'ward' => ['nullable', 'string', 'max:100'],
            'street' => ['nullable', 'string', 'max:100'],
            'admission_date' => ['nullable', 'date'],
            'passbook_issue_date' => ['nullable', 'date', 'after_or_equal:admission_date'],
            'status' => [$member ? 'sometimes' : 'nullable', Rule::in(['active', 'inactive', 'suspended', 'closed'])],
            'kyc' => ['nullable', 'array'],
            'kyc.household_monthly_income' => ['nullable', 'numeric', 'min:0'],
            'kyc.household_monthly_expenses' => ['nullable', 'numeric', 'min:0'],
            'kyc.business_name' => ['nullable', 'string', 'max:200'],
            'kyc.business_type' => ['nullable', 'string', 'max:100'],
            'kyc.business_address' => ['nullable', 'string'],
            'kyc.mpesa_phone' => ['nullable', 'string', 'max:20'],
            'kyc.bank_account_number' => ['nullable', 'string', 'max:100'],
            'kyc.bank_account_name' => ['nullable', 'string', 'max:150'],
            'kyc.bank_name' => ['nullable', 'string', 'max:150'],
            'kyc.house_number' => ['nullable', 'string', 'max:100'],
            'kyc.police_station' => ['nullable', 'string', 'max:150'],
            'kyc.number_of_dependants' => ['nullable', 'integer', 'min:0'],
            'kyc.head_of_household' => ['nullable', 'string', 'max:150'],
            'kyc.house_ownership_status' => ['nullable', 'string', 'max:100'],
            'kyc.house_roof_type' => ['nullable', 'string', 'max:100'],
            'kyc.house_fence_type' => ['nullable', 'string', 'max:100'],
            'nominees' => ['nullable', 'array'],
            'nominees.*.name' => ['required', 'string', 'max:150'],
            'nominees.*.relationship' => ['required', 'string', 'max:100'],
            'nominees.*.percentage' => ['required', 'numeric', 'gt:0', 'max:100'],
            'family_members' => ['nullable', 'array'],
            'family_members.*.name' => ['required', 'string', 'max:150'],
            'family_members.*.gender' => ['nullable', 'string', 'max:30'],
            'family_members.*.age' => ['nullable', 'integer', 'min:0', 'max:150'],
            'family_members.*.relationship' => ['nullable', 'string', 'max:100'],
            'family_members.*.education' => ['nullable', 'string', 'max:100'],
            'family_members.*.marital_status' => ['nullable', 'string', 'max:50'],
            'family_members.*.occupation' => ['nullable', 'string', 'max:150'],
            'family_members.*.secondary_occupation' => ['nullable', 'string', 'max:150'],
            'assets' => ['nullable', 'array'],
            'assets.*.name' => ['required', 'string', 'max:150'],
            'assets.*.category' => ['nullable', 'string', 'max:100'],
            'assets.*.quantity' => ['required', 'integer', 'min:1'],
            'assets.*.estimated_value' => ['nullable', 'numeric', 'min:0'],
            'assets.*.description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $member = $this->route('member');
            $branchId = $this->input('branch_id', $member?->branch_id);
            $group = MemberGroup::find($this->input('group_id', $member?->group_id));
            if ($group && ! $group->status) {
                $validator->errors()->add('group_id', 'The selected group is inactive.');
            }
            if ($group && (int) $group->branch_id !== (int) $branchId) {
                $validator->errors()->add('group_id', 'The selected group does not belong to the selected branch.');
            }
            $nominees = collect($this->input('nominees', []));
            if ($nominees->isNotEmpty() && abs((float) $nominees->sum('percentage') - 100) > 0.009) {
                $validator->errors()->add('nominees', 'Nominee allocations must total exactly 100%.');
            }
        });
    }
}
