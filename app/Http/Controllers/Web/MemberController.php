<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMemberRequest;
use App\Models\AssetType;
use App\Models\Branch;
use App\Models\Member;
use App\Models\MemberGroup;
use App\Services\GroupMembershipService;
use App\Services\NumberGeneratorService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Throwable;

class MemberController extends Controller
{
    public function index(Request $request)
    {
        $members = Member::with(['branch', 'group'])->when($this->branchId($request), fn ($q, $id) => $q->where('branch_id', $id))->when($request->group_id, fn ($q, $id) => $q->where('group_id', $id))->when($request->status, fn ($q, $v) => $q->where('status', $v))->when($request->search, fn ($q, $v) => $q->where(fn ($q) => $q->where('membership_number', 'like', "%{$v}%")->orWhere('first_name', 'like', "%{$v}%")->orWhere('last_name', 'like', "%{$v}%")->orWhere('phone', 'like', "%{$v}%")))->latest()->paginate(20)->withQueryString();

        return view('admin.members.index', ['members' => $members, 'groups' => MemberGroup::where('status', true)->when($this->branchId($request), fn ($q, $id) => $q->where('branch_id', $id))->orderBy('group_name')->get()]);
    }

    public function create(Request $request)
    {
        $branchId = $this->branchId($request);

        return view('admin.members.create', $this->formData($request, new Member, $request->integer('group_id')));
    }

    public function store(StoreMemberRequest $request, NumberGeneratorService $numbers, GroupMembershipService $memberships)
    {
        $data = $request->validated();
        $photo = Arr::pull($data, 'photo');
        $photoPath = null;

        try {
            $member = DB::transaction(function () use ($request, $numbers, $memberships, $data, $photo, &$photoPath) {
                if ($photo) {
                    $photoPath = $photo->store('members/photos', 'public');
                    $data['photo_path'] = $photoPath;
                }
                $kyc = Arr::pull($data, 'kyc');
                $nominees = Arr::pull($data, 'nominees', []);
                $familyMembers = Arr::pull($data, 'family_members', []);
                $assets = Arr::pull($data, 'assets', []);
                $member = Member::create([...$data, 'membership_number' => $numbers->member(), 'created_by' => $request->user()->id]);
                if ($kyc) {
                    $member->kyc()->create($kyc);
                }
                $memberships->assign($member, MemberGroup::findOrFail($member->group_id), $member->admission_date ?? today());
                foreach ($nominees as $nominee) {
                    $member->nominees()->create([...$nominee, 'attested_at' => now()]);
                }
                foreach ($familyMembers as $familyMember) {
                    $member->familyMembers()->create($familyMember);
                }
                $this->createAssets($member, $assets);
                activity()->causedBy($request->user())->performedOn($member)->log('Member registered');

                return $member;
            });
        } catch (Throwable $exception) {
            if ($photoPath) {
                Storage::disk('public')->delete($photoPath);
            }

            throw $exception;
        }

        return redirect()->route('admin.members.show', $member)->with('success', 'Member registered successfully.');
    }

    public function show(Member $member)
    {
        $member->load([
            'branch.manager',
            'group',
            'createdBy',
            'kyc',
            'activeGroupMembership',
            'securityAccount.transactions',
            'passbookReplacements',
            'documents',
            'nominees',
            'familyMembers',
            'assets.assetType',
        ]);

        $applications = $member->loanApplications()
            ->with(['product', 'loan', 'guarantors', 'groupWitnesses.member'])
            ->latest()
            ->get();

        $loans = $member->loans()
            ->with([
                'product',
                'application.guarantors',
                'cycles',
                'installments',
                'installmentRecords.collector',
                'payments',
                'securityTransactions.collectedBy',
                'securityTransactions.approvedBy',
                'settlement',
                'clearance',
            ])
            ->latest()
            ->get();

        return view('admin.members.show', compact('member', 'applications', 'loans'));
    }

    public function edit(Request $request, Member $member)
    {
        $member->load('kyc', 'nominees', 'familyMembers', 'assets.assetType');

        return view('admin.members.create', $this->formData($request, $member, $member->group_id));
    }

    public function update(Request $request, Member $member, GroupMembershipService $memberships)
    {
        if ($request->has('nominees')) {
            $request->merge([
                'nominees' => array_values(array_filter(
                    $request->input('nominees', []),
                    fn ($row) => filled($row['name'] ?? null)
                        || filled($row['relationship'] ?? null)
                        || (float) ($row['percentage'] ?? 0) > 0
                )),
            ]);
        }

        foreach (['family_members', 'assets'] as $collection) {
            if ($request->has($collection)) {
                $request->merge([
                    $collection => array_values(array_filter(
                        $request->input($collection, []),
                        fn ($row) => collect($row)->contains(fn ($value) => filled($value))
                    )),
                ]);
            }
        }

        $data = $request->validate([
            'branch_id' => ['required', 'exists:branches,id'],
            'group_id' => ['required', 'exists:member_groups,id'],
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:min_width=200,min_height=200'],
            'guardian_name' => ['nullable', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:20', Rule::unique('members', 'phone')->ignore($member)],
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
            'status' => ['required', Rule::in(['active', 'inactive', 'suspended', 'closed'])],
            'kyc' => ['nullable', 'array'],
            'kyc.household_monthly_income' => ['nullable', 'numeric', 'min:0'],
            'kyc.household_monthly_expenses' => ['nullable', 'numeric', 'min:0'],
            'kyc.business_name' => ['nullable', 'string', 'max:200'],
            'kyc.business_type' => ['nullable', 'string', 'max:100'],
            'kyc.business_address' => ['nullable', 'string'],
            'kyc.mpesa_phone' => ['nullable', 'string', 'max:20'],
            'kyc.bank_account_number' => ['nullable', 'string', 'max:50'],
            'kyc.bank_account_name' => ['nullable', 'string', 'max:100'],
            'kyc.bank_name' => ['nullable', 'string', 'max:100'],
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
        ]);

        if (array_key_exists('nominees', $data) && count($data['nominees']) > 0
            && abs((float) collect($data['nominees'])->sum('percentage') - 100) > 0.009) {
            return back()->withInput()->withErrors(['nominees' => 'Nominee allocations must total exactly 100%.']);
        }

        $group = MemberGroup::findOrFail($data['group_id']);
        if (! $group->status || (int) $group->branch_id !== (int) $data['branch_id']) {
            return back()->withInput()->with('error', 'The selected group must be active and belong to the selected branch.');
        }

        $photo = Arr::pull($data, 'photo');
        $oldPhotoPath = $member->photo_path;
        $newPhotoPath = $photo?->store('members/photos', 'public');
        if ($newPhotoPath) {
            $data['photo_path'] = $newPhotoPath;
        }

        try {
            DB::transaction(function () use ($member, $data, $memberships, $group) {
                $kyc = Arr::pull($data, 'kyc');
                $nominees = Arr::pull($data, 'nominees');
                $familyMembers = Arr::pull($data, 'family_members');
                $assets = Arr::pull($data, 'assets');
                $groupChanged = (int) $member->group_id !== (int) $data['group_id'];
                $member->update($data);

                if ($kyc) {
                    $member->kyc()->updateOrCreate(['member_id' => $member->id], $kyc);
                }

                if ($groupChanged) {
                    $memberships->assign($member, $group, $member->admission_date ?? today());
                }

                if ($nominees !== null) {
                    $member->nominees()->delete();
                    foreach ($nominees as $nominee) {
                        $member->nominees()->create([...$nominee, 'attested_at' => now()]);
                    }
                }

                if ($familyMembers !== null) {
                    $member->familyMembers()->delete();
                    foreach ($familyMembers as $familyMember) {
                        $member->familyMembers()->create($familyMember);
                    }
                }

                if ($assets !== null) {
                    $member->assets()->delete();
                    $this->createAssets($member, $assets);
                }
            });
        } catch (Throwable $exception) {
            if ($newPhotoPath) {
                Storage::disk('public')->delete($newPhotoPath);
            }

            throw $exception;
        }

        if ($newPhotoPath && $oldPhotoPath && $oldPhotoPath !== $newPhotoPath) {
            Storage::disk('public')->delete($oldPhotoPath);
        }

        return redirect()->route('admin.members.show', $member)->with('success', 'Member updated successfully.');
    }

    public function destroy(Member $member)
    {
        if ($member->loans()->exists() || $member->loanApplications()->whereNotIn('status', ['draft', 'cancelled', 'rejected'])->exists()) {
            return back()->with('error', 'This member has loan history and cannot be deleted.');
        }

        $member->delete();

        return redirect()->route('admin.members.index')->with('success', 'Member deleted.');
    }

    public function updateKyc(Request $request, Member $member)
    {
        $data = $request->validate(['mpesa_phone' => ['nullable', 'max:20'], 'bank_account_number' => ['nullable', 'max:100'], 'bank_account_name' => ['nullable', 'max:150'], 'bank_name' => ['nullable', 'max:150'], 'house_number' => ['nullable', 'max:100'], 'police_station' => ['nullable', 'max:150'], 'business_name' => ['nullable', 'max:150'], 'business_type' => ['nullable', 'max:150'], 'business_address' => ['nullable', 'string'], 'household_monthly_income' => ['nullable', 'numeric', 'min:0'], 'household_monthly_expenses' => ['nullable', 'numeric', 'min:0'], 'number_of_dependants' => ['nullable', 'integer', 'min:0'], 'head_of_household' => ['nullable', 'max:150'], 'house_ownership_status' => ['nullable', 'max:100']]);
        $member->kyc()->updateOrCreate(['member_id' => $member->id], $data);

        return back()->with('success', 'Member KYC updated.');
    }

    private function branchId(Request $request): ?int
    {
        $user = $request->user();

        return $user->hasAnyRole(['super_admin', 'head_office_admin']) ? ($request->integer('branch_id') ?: null) : $user->branch_id;
    }

    private function formData(Request $request, Member $member, ?int $selectedGroup = null): array
    {
        $branchId = $this->branchId($request);

        return [
            'member' => $member,
            'branches' => Branch::where('status', true)->when($branchId, fn ($q, $id) => $q->whereKey($id))->get(),
            'groups' => MemberGroup::with('branch')->where('status', true)->when($branchId, fn ($q, $id) => $q->where('branch_id', $id))->orderBy('group_name')->get(),
            'selectedGroup' => $selectedGroup,
        ];
    }

    private function createAssets(Member $member, array $assets): void
    {
        foreach ($assets as $asset) {
            $name = Arr::pull($asset, 'name');
            $category = Arr::pull($asset, 'category');
            $assetType = AssetType::firstOrCreate(
                ['name' => $name],
                ['category' => $category, 'status' => true]
            );
            $member->assets()->create([...$asset, 'asset_type_id' => $assetType->id]);
        }
    }
}
