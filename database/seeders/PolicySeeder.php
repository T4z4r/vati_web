<?php

namespace Database\Seeders;

use App\Models\Policy;
use Illuminate\Database\Seeder;

class PolicySeeder extends Seeder
{
    public function run(): void
    {
        // Passbook Policies
        Policy::updateOrCreate(
            ['policy_code' => 'PASSBOOK_001'],
            [
                'policy_title' => 'Passbook Safekeeping',
                'category' => 'passbook',
                'description' => 'Members must keep their passbooks carefully and bring them when making payments',
                'detailed_content' => 'Hifadhi Kitabu chako cha marejesho kwa uangalifu (Keep your passbook carefully). Members must carry their passbooks when coming to deposit installments in the group.',
                'rules' => [
                    'must_bring_passbook' => true,
                    'required_for_payments' => true,
                ],
                'is_active' => true,
            ]
        );

        Policy::updateOrCreate(
            ['policy_code' => 'PASSBOOK_002'],
            [
                'policy_title' => 'Passbook Signature Verification',
                'category' => 'passbook',
                'description' => 'Passbook must be signed by Loan Officer after each payment',
                'detailed_content' => 'Hakikisha Kitabu cha marejesho kinasainiwa na Afisa Mikopo baada ya kulipa rajesho. (Make sure that passbook is properly signed by the VATI Microfinance officer after depositing your money).',
                'rules' => [
                    'must_be_signed' => true,
                    'signed_by' => 'loan_officer',
                    'timing' => 'after_each_payment',
                ],
                'is_active' => true,
            ]
        );

        Policy::updateOrCreate(
            ['policy_code' => 'PASSBOOK_003'],
            [
                'policy_title' => 'Passbook Non-Transferability',
                'category' => 'passbook',
                'description' => 'Passbook is non-transferable and cannot be given to anyone else',
                'detailed_content' => 'Kwa mazingira yoyote Kitabu cha marejesho ni mali ya mtu mmoja na hakihamishiki kwa mtu mwingine. (The passbook under no circumstances is transferrable).',
                'rules' => [
                    'can_transfer' => false,
                    'personal_property' => true,
                ],
                'is_active' => true,
            ]
        );

        Policy::updateOrCreate(
            ['policy_code' => 'PASSBOOK_004'],
            [
                'policy_title' => 'Lost or Damaged Passbook Replacement',
                'category' => 'passbook',
                'description' => 'If passbook is lost or damaged, a replacement will be issued with a fee',
                'detailed_content' => 'Kama Kitabu cha marejesho kikiharibika au kupotea, utapatiwa Kitabu cha marejesho kipya kwa gharama yako. (If the passbook is damaged or lost, a duplicate passbook will be issued with a fine).',
                'rules' => [
                    'replacement_available' => true,
                    'fee_applies' => true,
                ],
                'fee_amount' => 1000.00,
                'is_active' => true,
            ]
        );

        Policy::updateOrCreate(
            ['policy_code' => 'PASSBOOK_005'],
            [
                'policy_title' => 'Passbook Loss Reporting',
                'category' => 'passbook',
                'description' => 'If passbook is lost, member must contact Loan Officer immediately',
                'detailed_content' => 'Endapo Kitabu cha marejesho kikipotea au kuwa na taarifa zisizo sahihi unatakiwa kuwasiliana na Afisa Mikopo wa tawi husika. (If the passbook is lost or the records are found wrong, contact the relevant Loan Officer of the Branch Office).',
                'rules' => [
                    'must_report' => true,
                    'report_to' => 'loan_officer',
                    'urgency' => 'immediate',
                ],
                'is_active' => true,
            ]
        );

        // Loan Policies
        Policy::updateOrCreate(
            ['policy_code' => 'LOAN_001'],
            [
                'policy_title' => 'Loan Terms and Conditions Knowledge',
                'category' => 'loan',
                'description' => 'Members must understand loan terms and conditions before receiving loan',
                'detailed_content' => 'Mwanachama anapaswa kujua vizuri vigezo na masharti ya mkopo wa VATI kabla ya kukabidhiwa mkopo husika. (Member must know and understand the terms and conditions of VATI loan before being given the loan).',
                'rules' => [
                    'must_understand' => true,
                    'timing' => 'before_disbursement',
                    'verification_required' => true,
                ],
                'is_active' => true,
            ]
        );

        Policy::updateOrCreate(
            ['policy_code' => 'LOAN_002'],
            [
                'policy_title' => 'Loan Non-Sharing Principle',
                'category' => 'loan',
                'description' => 'Loans cannot be shared with employees or other members',
                'detailed_content' => 'Hairuhusiwi kugawana mkopo na mfanyakazi au na mwanachama yeyote yule. Mtu ambaye amesaini kwenye nyaraka zetu ndiye atawajibika kwenye mkopo huo. (Loans cannot be shared with employees or other members. The person who signed the documents is responsible for the loan).',
                'rules' => [
                    'can_share' => false,
                    'single_responsibility' => true,
                    'signatory_responsible' => true,
                ],
                'is_active' => true,
            ]
        );

        Policy::updateOrCreate(
            ['policy_code' => 'LOAN_003'],
            [
                'policy_title' => 'Loan Receipt Verification',
                'category' => 'loan',
                'description' => 'Member must verify receiving full loan amount at disbursement',
                'detailed_content' => 'Baada ya kumaliza hatua za awali za maombi ya mkopo na endapo utaitwa kwenye ofisi za tawi husika kuchukua mkopo, hakikisha unapewa kiasi chote cha mkopo ulichoomba. (After completing initial loan application steps and when called to collect the loan at the branch, ensure you receive the full amount of loan you applied for).',
                'rules' => [
                    'must_verify' => true,
                    'verify_amount' => true,
                    'timing' => 'at_disbursement',
                ],
                'is_active' => true,
            ]
        );

        Policy::updateOrCreate(
            ['policy_code' => 'LOAN_004'],
            [
                'policy_title' => 'Early Repayment for Loan Clearance',
                'category' => 'loan',
                'description' => 'Members can make early repayments to clear loan and qualify for next loan',
                'detailed_content' => 'Mwanachama anaruhusiwa kulipa marejesho ya mkupuo kwa ajili ya kumaliza mkopo wake na kuweza kuchukua mkopo mwingine. Fedha hizo zitalipwa kwenye kikundi mbele ya wanachama wenzake. (Member is allowed to make early/lump-sum repayments to complete their loan and be able to take another loan. These funds will be paid at the group meeting in front of other members).',
                'rules' => [
                    'early_repayment_allowed' => true,
                    'public_payment' => true,
                    'enables_next_loan' => true,
                ],
                'is_active' => true,
            ]
        );

        // Payment Policies
        Policy::updateOrCreate(
            ['policy_code' => 'PAYMENT_001'],
            [
                'policy_title' => 'Authorized Personnel Payment',
                'category' => 'payment',
                'description' => 'Payments must be made to authorized VATI personnel only',
                'detailed_content' => 'Marejesho yote apewe afisa anayetambulika, endapo utakuwa na wasiwasi na mtu muombe kitambulisho chake au piga namba +255 764 897 791. (All payments should be given to a recognized officer. If you have doubt about a person, ask them for identification or call +255 764 897 791).',
                'rules' => [
                    'authorized_personnel_only' => true,
                    'verify_identity' => true,
                    'can_request_id' => true,
                ],
                'is_active' => true,
            ]
        );

        Policy::updateOrCreate(
            ['policy_code' => 'PAYMENT_002'],
            [
                'policy_title' => 'No Holiday Payments',
                'category' => 'payment',
                'description' => 'No payments are collected on public holidays',
                'detailed_content' => 'Hakutakuwa na marejesho siku ya sikukuu, rejesho litakalokuwa limeangukia siku ya sikukuu litakusanywa mwishoni mwa mkopo yaani kutakuwa na ongezeko la wiki kulingana na sikukuu zilizojitokeza. (There will be no payment collection on holidays. Payments that fall on holidays will be collected at the end of the loan period with additional weekly installments).',
                'rules' => [
                    'collect_on_holidays' => false,
                    'reschedule' => 'end_of_loan',
                    'add_extra_week' => true,
                ],
                'is_active' => true,
            ]
        );

        Policy::updateOrCreate(
            ['policy_code' => 'PAYMENT_003'],
            [
                'policy_title' => 'Public Payment Recording',
                'category' => 'payment',
                'description' => 'Early repayments must be recorded at group meetings in public',
                'detailed_content' => 'Fedha hizo zitalipwa kwenye kikundi mbele ya wanachama wenzake, yaani marejesho 7 ya mwisho kwa mkopo wa miezi 6 na marejesho 10 ya mwisho kwa mkopo wa miezi 8 na miezi 10. (Funds will be paid at the group meeting in front of other members. For 6-month loans, the last 7 installments. For 8 and 10-month loans, the last 10 installments).',
                'rules' => [
                    'public_recording' => true,
                    'recorded_by' => 'loan_officer',
                    'witnesses_required' => true,
                ],
                'is_active' => true,
            ]
        );

        // Membership Policies
        Policy::updateOrCreate(
            ['policy_code' => 'MEMBER_001'],
            [
                'policy_title' => 'Passbook Verification Responsibility',
                'category' => 'membership',
                'description' => 'Member must verify passbook information after each transaction',
                'detailed_content' => 'Ni jukumu la kila mwanachama kuhakiki kitabu cha marejesho kama kimesainiwa na afisa mkopo husika baada ya kulipa rejesho la wiki husika. (It is the responsibility of each member to verify that the passbook is signed by the loan officer after paying each weekly installment).',
                'rules' => [
                    'verify_after_payment' => true,
                    'check_signature' => true,
                    'member_responsibility' => true,
                ],
                'is_active' => true,
            ]
        );

        Policy::updateOrCreate(
            ['policy_code' => 'MEMBER_002'],
            [
                'policy_title' => 'Loan Information Verification',
                'category' => 'membership',
                'description' => 'Member must verify loan information and repayment schedule against balance',
                'detailed_content' => 'Mwanachama anapaswa kuhakiki taarifa za mkopo na mtiririko wa marejesho yake kulingana na deni lililobakia ambalo limeandikwa na kusainiwa na afisa mkopo. (Member must verify loan information and repayment flow according to the outstanding balance written and signed by the loan officer).',
                'rules' => [
                    'verify_loan_info' => true,
                    'check_balance' => true,
                    'cross_reference' => 'loan_officer_signature',
                ],
                'is_active' => true,
            ]
        );

        Policy::updateOrCreate(
            ['policy_code' => 'MEMBER_003'],
            [
                'policy_title' => 'Loan Storage Prohibition',
                'category' => 'membership',
                'description' => 'Member must not keep passbook at meeting venue or with group leaders',
                'detailed_content' => 'Hairuhusiwi kuhifadhi kitabu cha marejesho sehemu ya kukutania au kwa kiongozi wa kikundi au kwa mwanachama yeyote. (It is not allowed to keep the passbook at the meeting venue or with a group leader or with any other member).',
                'rules' => [
                    'personal_custody' => true,
                    'not_with_leader' => true,
                    'not_at_venue' => true,
                ],
                'is_active' => true,
            ]
        );

        // Fraud Prevention Policies
        Policy::updateOrCreate(
            ['policy_code' => 'FRAUD_001'],
            [
                'policy_title' => 'Loan Sharing Warning',
                'category' => 'loan',
                'description' => 'Report anyone attempting to convince member to share loan',
                'detailed_content' => 'Mtu yeyote akikushawishi kufanya hivyo tafadhali tujulishe haraka kupitia +255 764 897 791. (If anyone tries to convince you to do this please tell us immediately by calling +255 764 897 791).',
                'rules' => [
                    'immediate_reporting' => true,
                    'report_channel' => 'phone',
                    'report_number' => '+255 764 897 791',
                ],
                'is_active' => true,
            ]
        );

        Policy::updateOrCreate(
            ['policy_code' => 'FRAUD_002'],
            [
                'policy_title' => 'Loan Amount Discrepancy Reporting',
                'category' => 'loan',
                'description' => 'Report immediately if loan amount received does not match application',
                'detailed_content' => 'Endapo utapata tatizo lolote tafadhali tujulishe haraka kupitia +255 764 897 791. (If you encounter any problem please tell us immediately by calling +255 764 897 791).',
                'rules' => [
                    'immediate_reporting' => true,
                    'report_channel' => 'phone',
                    'report_number' => '+255 764 897 791',
                ],
                'is_active' => true,
            ]
        );

        // General Policies
        Policy::updateOrCreate(
            ['policy_code' => 'GENERAL_001'],
            [
                'policy_title' => 'Complaint Handling',
                'category' => 'membership',
                'description' => 'Members can lodge complaints through various channels',
                'detailed_content' => 'Kwa lalamiko lolote tafadhali wasiliana nasi kupitia sanduku la maoni lililopo kwenye tawi letu au piga simu. (For any complaints please contact us through the complaints box at our branch or call us).',
                'rules' => [
                    'complaint_box' => true,
                    'phone_contact' => true,
                    'letter_contact' => true,
                ],
                'is_active' => true,
            ]
        );
    }
}
