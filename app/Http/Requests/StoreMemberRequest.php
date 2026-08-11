<?php

namespace App\Http\Requests;

use App\Models\MemberGroup;
use Illuminate\Foundation\Http\FormRequest;

class StoreMemberRequest extends FormRequest
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
            'phone' => ['required', 'string', 'max:20', 'unique:members,phone'],
            'national_id' => ['nullable', 'string', 'max:50', 'unique:members,national_id'],
            'alternate_phone' => ['nullable', 'string', 'max:20'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', 'string', 'max:30'],
            'marital_status' => ['nullable', 'string', 'max:30'],
            'occupation' => ['nullable', 'string', 'max:100'],
            'physical_address' => ['nullable', 'string'],
            'region' => ['nullable', 'string', 'max:100'],
            'district' => ['nullable', 'string', 'max:100'],
            'ward' => ['nullable', 'string', 'max:100'],
            'street' => ['nullable', 'string', 'max:100'],
            'admission_date' => ['nullable', 'date'],
            'kyc' => ['nullable', 'array'],
            'kyc.household_monthly_income' => ['nullable', 'numeric', 'min:0'],
            'kyc.household_monthly_expenses' => ['nullable', 'numeric', 'min:0'],
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
        });
    }
}
