<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Member;
use Illuminate\Http\Request;

class MemberKycController extends ApiController
{
    public function update(Request $request, Member $member)
    {
        $data = $request->validate(['mpesa_phone' => ['nullable', 'string', 'max:20'], 'bank_account_number' => ['nullable', 'string', 'max:100'], 'bank_account_name' => ['nullable', 'string', 'max:150'], 'bank_name' => ['nullable', 'string', 'max:150'], 'house_number' => ['nullable', 'string', 'max:100'], 'police_station' => ['nullable', 'string', 'max:150'], 'business_name' => ['nullable', 'string', 'max:150'], 'business_type' => ['nullable', 'string', 'max:150'], 'business_address' => ['nullable', 'string'], 'household_monthly_income' => ['nullable', 'numeric', 'min:0'], 'household_monthly_expenses' => ['nullable', 'numeric', 'min:0'], 'number_of_dependants' => ['nullable', 'integer', 'min:0'], 'head_of_household' => ['nullable', 'string', 'max:150'], 'house_ownership_status' => ['nullable', 'string', 'max:100'], 'house_roof_type' => ['nullable', 'string', 'max:100'], 'house_fence_type' => ['nullable', 'string', 'max:100']]);
        $kyc = $member->kyc()->updateOrCreate(['member_id' => $member->id], $data);
        activity()->causedBy($request->user())->performedOn($member)->log('Member KYC updated');

        return response()->json(['success' => true, 'message' => 'KYC updated successfully.', 'data' => $kyc]);
    }
}
