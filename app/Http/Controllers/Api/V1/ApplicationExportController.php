<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\LoanApplication;
use App\Services\ApplicationDetailService;
use Barryvdh\DomPDF\Facade\Pdf;

class ApplicationExportController extends ApiController
{
    public function download(LoanApplication $loanApplication, ApplicationDetailService $details)
    {
        $data = $details->build($loanApplication);

        return Pdf::loadView('pdf.loan-application', compact('data'))
            ->setPaper('a4')
            ->download('loan-application-'.$loanApplication->application_number.'.pdf');
    }
}
