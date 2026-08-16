<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Loan;
use Barryvdh\DomPDF\Facade\Pdf;

class LoanExportController extends ApiController
{
    public function download(Loan $loan)
    {
        $loan->load([
            'member.branch', 'member.group', 'product', 'group',
            'application.guarantors', 'installments', 'payments.allocations',
            'cycles', 'disbursement', 'settlement', 'clearance', 'defaultNotices',
        ]);

        return Pdf::loadView('pdf.loan-details', compact('loan'))
            ->setPaper('a4')
            ->download('VATI-loan-'.$loan->loan_number.'.pdf');
    }
}
