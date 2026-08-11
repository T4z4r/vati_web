<?php

namespace Database\Seeders;

use App\Models\LoanTerm;
use Illuminate\Database\Seeder;

class LoanTermSeeder extends Seeder
{
    public function run(): void
    {
        LoanTerm::updateOrCreate(
            ['version' => 'VATI-2026-08'],
            [
                'title' => 'VATI Small Loan Agreement and Member Declaration',
                'body' => implode("\n\n", [
                    'The applicant confirms that all information supplied is true and authorizes VATI Microfinance Limited to verify and share credit information with lawful credit-reference, financial, regulatory, service-provider, and company stakeholders.',
                    'The applicant accepts responsibility for repayment under the approved schedule and understands that pledged security may be applied after lawful notice if the debt is not repaid.',
                    'The applicant may cancel before disbursement during the three-day cooling-off period. A default-enforcement action requiring notice may proceed only after a fourteen-day notice has expired.',
                    'The guarantors accept legal responsibility for the debt if the applicant defaults, subject to the signed guarantor declarations and applicable law.',
                ]),
                'effective_from' => '2026-08-11',
                'is_active' => true,
            ]
        );
    }
}
