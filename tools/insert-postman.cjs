const fs = require('fs');
const file = 'postman/VATI-Microfinance-API.postman_collection.json';
const j = JSON.parse(fs.readFileSync(file, 'utf8'));

const req = (method, url, raw) => ({
    method,
    header: [{ key: 'Content-Type', value: 'application/json' }],
    body: { mode: 'raw', raw, options: { raw: { language: 'json' } } },
    url,
});

const complianceBase = '{{base_url}}/api/v1/loan-applications/{{loan_application_id}}/compliance';
const compliance = [
    {
        name: 'Update Applicant Compliance',
        request: req('PUT', complianceBase + '/applicant', '{\n  "accept_declaration": true,\n  "applicant_signature": "{{applicant_signature}}",\n  "applicant_thumbprint": "{{applicant_thumbprint}}"\n}'),
        response: [],
    },
    {
        name: 'Add Guarantor Compliance',
        request: req('POST', complianceBase + '/guarantors', '{\n  "guarantor_type": "family",\n  "name": "Jane Doe",\n  "relationship": "Sister",\n  "phone": "0712345678",\n  "national_id": "19900101-12345-12345-12",\n  "signature": "{{guarantor_signature}}",\n  "thumbprint": "{{guarantor_thumbprint}}",\n  "accept_declaration": true\n}'),
        response: [],
    },
    {
        name: 'Update Nominees Compliance',
        request: req('PUT', complianceBase + '/nominees', '{\n  "nominees": [\n    {\n      "name": "Grace Nominee",\n      "relationship": "Daughter",\n      "percentage": 100\n    }\n  ]\n}'),
        response: [],
    },
    {
        name: 'Cancel Application',
        request: req('POST', '{{base_url}}/api/v1/loan-applications/{{loan_application_id}}/cancel', '{\n  "reason": "Applicant requested withdrawal"\n}'),
        response: [],
    },
];

const adminBase = '{{base_url}}/api/v1';
const loansAdmin = [
    {
        name: 'Replace Member Passbook',
        request: req('POST', adminBase + '/members/{{member_id}}/passbook-replacements', '{\n  "reason": "damaged",\n  "payment_reference": "TXN-20260218-001"\n}'),
        response: [],
    },
    {
        name: 'Issue Default Notice',
        request: req('POST', adminBase + '/loans/{{loan_id}}/default-notices', '{\n  "delivery_method": "hand",\n  "delivery_reference": "Served at branch counter"\n}'),
        response: [],
    },
    {
        name: 'Authorize Loan Clearance',
        request: req('POST', adminBase + '/loans/{{loan_id}}/clearance', '{\n  "comments": "All dues settled. Collateral released."\n}'),
        response: [],
    },
];

function findFolder(node, name) {
    const items = node.item;
    for (const n of items) if (n.name === name && Array.isArray(n.item)) return n;
    return null;
}

const loanFolder = findFolder(j, 'Loan Applications and Witnesses');
const loansFolder = findFolder(j, 'Loans, Disbursement and Collections');

if (loanFolder) loanFolder.item = loanFolder.item.concat(compliance);
if (loansFolder) loansFolder.item = loansFolder.item.concat(loansAdmin);

fs.writeFileSync(file, JSON.stringify(j, null, 4) + '\n');
console.log('loanFolder=' + !!loanFolder + ', loansFolder=' + !!loansFolder);
