const fs = require('fs');
const file = 'postman/VATI-Microfinance-API.postman_collection.json';
const j = JSON.parse(fs.readFileSync(file, 'utf8'));

const jsonReq = (method, url, rawJson, desc) => ({
    method,
    header: [{ key: 'Content-Type', value: 'application/json' }],
    body: { mode: 'raw', raw: rawJson, options: { raw: { language: 'json' } } },
    url,
    description: desc || '',
});

function findFolder(folders, name) {
    for (const folder of folders) {
        if (folder.name === name) return folder;
    }
    return null;
}

// --- 1. Loan Applications and Witnesses: compliance save/update + cancel ---
const wa = findFolder(j.item, 'Loan Applications and Witnesses');
const compliance = [
    {
        name: 'Update Applicant Compliance',
        request: jsonReq('PUT', '{{base_url}}/api/v1/loan-applications/{{loan_application_id}}/compliance/applicant', '{\n  "accept_declaration": true,\n  "applicant_signature": "{{applicant_signature}}",\n  "applicant_thumbprint": "{{applicant_thumbprint}}"\n}'),
        response: [],
    },
    {
        name: 'Add Compliance Guarantor',
        request: jsonReq('POST', '{{base_url}}/api/v1/loan-applications/{{loan_application_id}}/compliance/guarantors', '{\n  "guarantor_type": "family",\n  "name": "Jane Guarantor",\n  "relationship": "Sister",\n  "phone": "0712345678",\n  "national_id": "19900512-12345-12345-12",\n  "voter_id": "12345678",\n  "house_number": "B-221",\n  "street": "Mbezi Beach",\n  "ward": "Mbezi",\n  "district": "Kinondoni",\n  "region": "Dar es Salaam",\n  "signature": "{{guarantor_signature}}",\n  "thumbprint": "{{guarantor_thumbprint}}",\n  "joint_photo": "{{guarantor_joint_photo}}",\n  "accept_declaration": true\n}'),
        response: [],
    },
    {
        name: 'Update Nominees Compliance',
        request: jsonReq('PUT', '{{base_url}}/api/v1/loan-applications/{{loan_application_id}}/compliance/nominees', '{\n  "nominees": [\n    {\n      "name": "Grace Nominee",\n      "relationship": "Daughter",\n      "percentage": 100\n    }\n  ]\n}'),
        response: [],
    },
    {
        name: 'Cancel Application',
        request: jsonReq('POST', '{{base_url}}/api/v1/loan-applications/{{loan_application_id}}/cancel', '{\n  "reason": "Applicant requested withdrawal"\n}'),
        response: [],
    },
];
if (wa) wa.item = wa.item.concat(complianceapsed);

// --- 2. Loans, Disbursement and Collections: loan administration ---
const loans = findFolder(j.item, 'Loans, Disbursement and Collections');
const admin = [
    {
        name: 'Replace Member Passbook',
        request: jsonReq('POST', '{{base_url}}/api/v1/members/{{member_id}}/passbook-replacements', '{\n  "reason": "damaged",\n  "payment_reference": "TXN-20260218-001"\n}'),
        response: [],
    },
    {
        name: 'Issue Default Notice',
        request: jsonReq('POST', '{{base_url}}/api/v1/loans/{{loan_id}}/default-notices', '{\n  "delivery_method": "hand",\n  "delivery_reference": "Served at branch counter"\n}'),
        response: [],
    },
    {
        name: 'Authorize Loan Clearance',
        request: jsonReq('POST', '{{base_url}}/api/v1/loans/{{loan_id}}/clearance', '{\n  "comments": "All dues settled, clearance approved."\n}'),
        response: [],
    },
];
if (loans) loans.item = loans.item.concat(admin);

fs.writeFileSync(postman file, JSON.stringify(j, null, 4) + '\n');
console.log('inserted compliance=' + (wa ? compliance.length : 0) + ', admin=' + (loans ? admin.length : 0));
