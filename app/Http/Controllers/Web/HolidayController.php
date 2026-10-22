<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Holiday;
use App\Models\Loan;
use App\Services\HolidayScheduleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HolidayController extends Controller
{
    public function index()
    {
        return view('admin.system.holidays', ['holidays' => Holiday::orderBy('date')->get()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate(['date' => 'required|date_format:Y-m-d', 'name' => 'required|string|max:255']);
        Holiday::updateOrCreate(['date' => $data['date']], ['name' => $data['name']]);

        return back()->with('success', 'Holiday saved.');
    }

    public function destroy(Holiday $holiday)
    {
        $holiday->delete();

        return back()->with('success', 'Holiday removed. Existing schedule dates are unchanged.');
    }

    public function import(Request $request, HolidayScheduleService $service)
    {
        $data = $request->validate(['year' => 'required|integer|min:2000|max:2100']);
        $service->importTanzania($data['year']);

        return back()->with('success', 'Tanzania holidays added. Enter confirmed Eid and Maulid dates separately.');
    }

    public function correct(Request $request, HolidayScheduleService $service)
    {
        $data = $request->validate([
            'from' => 'required|date_format:Y-m-d', 'to' => 'required|date_format:Y-m-d|after_or_equal:from',
            'include_paid' => 'nullable|boolean',
        ]);
        $count = 0;
        Loan::whereHas('installments', fn ($q) => $q->whereDate('due_date', '>=', $data['from'])->whereDate('due_date', '<=', $data['to'])
            ->whereIn(DB::raw('DATE(due_date)'), Holiday::select('date')))
            ->chunkById(100, function ($loans) use ($service, $request, &$count) {
                foreach ($loans as $loan) {
                    $count += $service->correct($loan, $request->boolean('include_paid'));
                }
            });
        activity()->causedBy($request->user())->withProperties($data + ['changed_installments' => $count])->log('Holiday repayment dates corrected');

        return back()->with('success', "$count installment dates corrected. Payment records preserved.");
    }
}
