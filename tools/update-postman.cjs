const fs = require('fs');
const FILE = 'postman/VATI-Microfinance-API.postman_collection.json';
const j = JSON.parse(fs.readFileSync(FILE, 'utf8'));
const b = '{{base_url}}/api/v1';

const R = (method, url, raw) => ({
    method,
    header: [{ key: 'Content-Type', value: 'application/json' }],
    body: { mode: 'raw', raw, options: { raw: { language: 'json' } } },
    url,
});

const cBase = b + '/loan-applications/{{loan_application_id}}';
const wa = [
    { name: 'Update Applicant Compliance', request: R('PUT', cBase + '/compliance/applicant', '{\n  "accept_declaration": true,\n  "applicant_signature": "{{applicant_signature}}",\n  "applicant_thumbprint": "{{applicant_thumbprint}}"\n}'), response: [] },
    { name: 'Add Guarantor Compliance', request: R('POST', cBase + '/compliance/guarantors', '{\n  "guarantor_type": "family",\n  "name": "Jane Doe",\n  "relationship": "Sister",\n  "phone": "0712345678",\n  "national_id": "{{guarantor_national_id}}",\n  "signature": "{{guarantor_signature}}",\n  "thumbprint": "{{guarantor_thumbprint}}",\n  "accept_declaration": true\n}'), response: [] },
    { name: 'Update Nominees Compliance', request: R('PUT', cBase + '/compliance/nominees', '{\n  "nominees": [\n    {\n      "name": "Grace Nominee",\n      "relationship": "Daughter",\n      "percentage": 100\n    }\n  ]\n}'), response: [] },
    { name: 'Cancel Application', request: R('POST', cBase + '/cancel', '{\n  "reason": "Applicant requested withdrawal"\n}'), response: [] },
];

const loansAdmin = [
    { name: 'Replace Member Passbook', request: R('POST', b + '/members/{{member_id}}/passbook-replacements', '{\n  "reason": "damaged",\n  "payment_reference": "{{payment_reference}}"\n}'), response: [] },
    { name: 'Issue Default Notice', request: R('POST', b + '/loans/{{loan_id}}/default-notices', '{\n  "delivery_method": "hand",\n  "delivery_reference": "{{delivery_reference}}"\n}'), response: [] },
    { name: 'Authorize Loan Clearance', request: R('POST', b + '/loans/{{loan_id}}/clearance', '{\n  "comments": "All dues settled, collateral released"\n}'), response: [] },
];

function findFolder(nodes, name) {
    for (const n of nodes) if (n.name === name && Array.isArray(n.item)) return n;
    return null;
}
const waFolder = findFolder(j.item, 'Loan Applications and Witnesses');
const loansFolder = findFolder(j.item, 'Loans, Disbursement and Collections');

if (waFolder) waFolder.item = waFolder.item.concat(wa);
if (loansFolder) loansFolder.item = loansFolder.item.concat(loansAdmin);

fs.writeFileSync(FILE, JSON.stringify(j, null, 4) + '\n');
console.log('wa=' + (waFolder ? wa.length : 0) + ' loans=' + (loansFolder ? loansAdmin.length : 0));
