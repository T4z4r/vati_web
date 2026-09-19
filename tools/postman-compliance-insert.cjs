const fs = require('fs');
const file = 'postman/VATI-Microfinance-API.postman_collection.json';
const source = JSON.parse(fs.readFileSync(file, 'utf8'));

const jsonReq = (method, url, raw) => ({
    method,
    header: [{ key: 'Content-Type', value: 'application/json' }],
    body: { mode: 'raw', raw, options: { raw: { language: 'json' } } },
    url,
});

const complianceItems = [
    {
        name: 'Set Applicant Compliance',
        request: jsonReq('PUT', '{{base_url}}/api/v1/loan-applications/{{loan_application_id}}/compliance/applicant', '{\n  "accept_declaration": true,\n  "applicant_signature": "{{applicant_signature}}",\n  "applicant_thumbprint": "{{applicant_thumbprint}}"\n}'),
        response: [],
    },
    {
        name: 'Add Compliance Guarantor',
        request: jsonReq('POST', '{{base_url}}/api/v1/loan-applications/{{loan_application_id}}/compliance/guarantors', '{\n  "guarantor_type": "family",\n  "name": "Jane Doe",\n  "relationship": "Sister",\n  "phone": "0712345678",\n  "national_id": "19900101-12345-12345-12",\n  "signature": "{{guarantor_signature}}",\n  "thumbprint": "{{guarantor_thumbprint}}",\n  "joint_photo": "{{joint_photo}}",\n  "accept_declaration": true\n}'),
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

const loansAdminItems = [
    {
        name: 'Replace Member Passbook',
        request: jsonReq('POST', '{{base_url}}/api/v1/members/{{member_id}}/passbook-replacements', '{\n  "reason": "damaged",\n  "payment_reference": "TXN-20260218-001"\n}'),
        response: [],
    },
    {
        name: 'Issue Default Notice',
        request: jsonReq('POST', '{{base_url}}/api/v1/loans/{{loan_id}}/default-notices', '{\n  "delivery_method": "hand",\n  "delivery_reference": "Served at collection meeting"\n}'),
        response: [],
    },
    {
        name: 'Authorize Loan Clearance',
        request: jsonReq('POST', '{{base_url}}/api/v1/loans/{{loan_id}}/clearance', '{\n  "comments": "All dues settled, collateral released."\n}'),
        response: [],
    },
];

function walk(folder, targetName, cb) {
    for (const node of folder) {
        if (Array.isArray(node.item)) {
            if (node.name === targetName) cb(node);
            walk(node.item, targetName, cb);
        }
    }
}

function insertAfter(container, anchorName, items) {
    const idx = container.item.findIndex(n => n.name === anchorName);
    const at = idx >= 0 ? idx + 1 : container.item.length;
    container.item.splice(at, 0, ...items);
    return at;
}

walk(source.item, 'Loan Applications and Witnesses', folder =>
    insertAfter(folder, 'Verify Application Document', complianceItems));
walk(source.item, 'Loans, Disbursement and Collections', folder =>
    insertAfter(folder, 'Reverse Payment', loansAdminItems));

fs.writeFileSync(file, JSON.stringify(source, null, 4) + '\n');
console.log('done');
