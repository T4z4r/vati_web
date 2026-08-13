<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Member;
use Barryvdh\DomPDF\Facade\Pdf;

class MemberExportController extends ApiController
{
    public function download(Member $member)
    {
        $member->load([
            'branch.area.region', 'branch.manager', 'group.loanOfficer', 'createdBy', 'kyc', 'nominees',
            'familyMembers', 'assets.assetType', 'securityAccount.transactions', 'passbookReplacements',
            'loans' => fn ($query) => $query->latest(), 'loans.product', 'loans.application.guarantors',
            'loans.cycles', 'loans.installments', 'loans.installmentRecords.collector',
            'loans.securityTransactions.collectedBy', 'loans.securityTransactions.approvedBy',
            'loans.payments.allocations', 'loans.settlement', 'loans.clearance',
        ]);

        return Pdf::loadView('pdf.member-passbook', compact('member'))
            ->setPaper('a4')
            ->download('VATI-member-'.$member->membership_number.'.pdf');
    }
}
