<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\SystemSetting;
use App\Services\DataPurgeService;
use App\Services\SystemInfoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SystemController extends Controller
{
    public function __construct(
        private SystemInfoService $infoService,
        private DataPurgeService $purgeService
    ) {}

    public function overview()
    {
        $data = $this->infoService->overview();

        return view('admin.system.overview', $data);
    }

    public function audit(Request $request)
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:200'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'log_name' => ['nullable', 'string', 'max:50'],
            'subject_type' => ['nullable', 'string', 'max:150'],
        ]);

        $query = DB::table('activity_log')
            ->leftJoin('users', 'activity_log.causer_id', '=', 'users.id')
            ->select(
                'activity_log.id',
                'activity_log.log_name',
                'activity_log.description',
                'activity_log.subject_type',
                'activity_log.subject_id',
                'activity_log.properties',
                'activity_log.created_at',
                'users.name as user_name',
                'users.id as user_id'
            );

        if ($request->filled('from')) {
            $query->where('activity_log.created_at', '>=', $request->from);
        }

        if ($request->filled('to')) {
            $query->where('activity_log.created_at', '<=', $request->to . ' 23:59:59');
        }

        if ($request->filled('user_id')) {
            $query->where('activity_log.causer_id', $request->user_id);
        }

        if ($request->filled('log_name')) {
            $query->where('activity_log.log_name', $request->log_name);
        }

        if ($request->filled('subject_type')) {
            $query->where('activity_log.subject_type', $request->subject_type);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('activity_log.description', 'LIKE', "%{$search}%")
                    ->orWhere('users.name', 'LIKE', "%{$search}%");
            });
        }

        $activities = $query->orderByDesc('activity_log.created_at')->paginate(25)->withQueryString();

        $users = DB::table('users')->select('id', 'name')->orderBy('name')->get();
        $logNames = DB::table('activity_log')->select('log_name')->distinct()->orderBy('log_name')->pluck('log_name');
        $subjectTypes = DB::table('activity_log')
            ->whereNotNull('subject_type')
            ->distinct()
            ->pluck('subject_type')
            ->mapWithKeys(fn ($type) => [class_basename($type) => $type])
            ->sortKeys();

        return view('admin.system.audit', compact('activities', 'users', 'logNames', 'subjectTypes'));
    }

    public function settings()
    {
        $settings = SystemSetting::allGrouped();
        $branches = Branch::orderBy('branch_name')->get();

        return view('admin.system.settings', compact('settings', 'branches'));
    }

    public function updateSettings(Request $request)
    {
        $request->validate([
            'settings' => 'required|array',
            'settings.*' => 'nullable|string|max:5000',
        ]);

        SystemSetting::setMany($request->settings);

        return redirect()->route('admin.system.settings')->with('success', 'System settings updated successfully.');
    }

    public function data()
    {
        $summary = $this->purgeService->summary();
        $branches = Branch::orderBy('branch_name')->get();

        return view('admin.system.data', compact('summary', 'branches'));
    }

    public function preview(Request $request)
    {
        $request->validate([
            'entity' => 'required|in:members,groups,applications,loans,loan_products',
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
            'branch_id' => 'nullable|integer|exists:branches,id',
        ]);

        $preview = $this->purgeService->preview(
            $request->entity,
            $request->input('from'),
            $request->input('to'),
            $request->integer('branch_id')
        );

        $validation = $this->purgeService->validate(
            $request->entity,
            $request->input('from'),
            $request->input('to'),
            $request->integer('branch_id')
        );

        return response()->json([
            'success' => true,
            'data' => array_merge($preview, $validation),
        ]);
    }

    public function purge(Request $request)
    {
        $request->validate([
            'entity' => 'required|in:members,groups,applications,loans,loan_products',
            'confirmation_phrase' => 'required|same:expected_phrase',
            'expected_phrase' => 'required',
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
            'branch_id' => 'nullable|integer|exists:branches,id',
        ]);

        try {
            $result = $this->purgeService->purge(
                $request->entity,
                $request->input('from'),
                $request->input('to'),
                $request->integer('branch_id'),
                $request->confirmation_phrase
            );

            activity()
                ->causedBy($request->user())
                ->withProperties([
                    'entity' => $request->entity,
                    'count' => $result['deleted'],
                    'cascade' => $result['cascade'] ?? [],
                ])
                ->log("Data purge executed: {$request->entity} ({$result['deleted']} records)");

            return redirect()->route('admin.system.data')->with('success', $result['message']);
        } catch (\DomainException $e) {
            return redirect()->route('admin.system.data')->with('error', $e->getMessage());
        }
    }
}
