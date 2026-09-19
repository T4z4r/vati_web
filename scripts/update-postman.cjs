const fs = require('fs');
const f = 'postman/VATI-Microfinance-API.postman_collection.json';
const j = JSON.parse(fs.readFileSync(f, 'utf8'));

const jsonReq = (method, url, raw) => ({
    method,
    header: [{ key: 'Content-Type', value: 'application/json' }],
    body: { mode: 'raw', raw, options: { raw: { language: 'json' } } },
    url,
});

const compliance = [
    {
        name: 'Update Applicant Compliance',
        request: jsonReq('PUT', '{{base_url}}/api/v1/loan-applications/{{loan_application_id}}/compliance/applicant', '{\n  "accept_declaration": true,\n  "applicant_signature": "{{applicant_signature}}",\n  "applicant_thumbprint": "{{applicant_thumbprint}}"\n}'),
        response: [],
    },
    {
        name: 'Add Guarantor Compliance',
        request: jsonReq('POST', '{{base_url}}/api/v1/loan-applications/{{loan_application_id}}/compliance/guarantors', '{\n  "guarantor_type": "family",\n  "name": "Jane Maasai",\n  "relationship": "Sister",\n  "phone": "0712345678",\n  "national_id": "12345678-12345-12345-123",\n  "signature": "{{guarantor_signature}}",\n  "thumbprint": "{{guarantor_thumbprint}}",\n  "joint_photo": "{{guarantor_joint_photo}}",\n  "accept_declaration": true\n}'),
        response: [],
    },
    {
        name: 'Update Nominees Compliance',
        request: jsonReq('PUT', '{{base_url}}/api/v1/loan-applications/{{loan_application_id}}/compliance/nominees', '{\n  "nominees": [\n    {\n      "name": "Grace Nyerere",\n      "relationship": "Wife",\n      "percentage": 100\n    }\n  ]\n}'),
        response: [],
    },
    {
        name: 'Cancel Application',
        request: jsonReq('POST', '{{base_url}}/api/v1/loan-applications/{{loan_application_id}}/cancel', '{\n  "reason": "Applicant requested withdrawal"\n}'),
        response: [],
    },
];

const loansAdmin = [
    {
        name: 'Replace Member Passbook',
        request: jsonReq('POST', '{{base_url}}/api/v1/members/{{member_id}}/passbook-replacements', '{\n  "reason": "damaged",\n  "payment_reference": "TXN-20260218-001"\n}'),
        response: [],
    },
    {
        name: 'Issue Default Notice',
        request: jsonReq('POST', '{{base_url}}/api/v1/loans/{{loan_id}}/default-notices', '{\n  "delivery_method": "sms",\n  "delivery_reference": "255712345678",\n  "notice_text": "Final reminder to settle overdue installments."\n}'),
        response: [],
    },
    {
        name: 'Authorize Loan Clearance',
        request: jsonReq('POST', '{{base_url}}/api/v1/loans/{{loan_id}}/clearance', '{\n  "comments": "All installments settled. Authorizing clearance."\n}'),
        response: [],
    },
];

function findFolder(root, name) {
    for (const folder of root.item) {
        if (folder.name === name && Array.isArray(folder.item)) return folder;
    }
    return null;
}

function insertAfter(source, anchorName, items) {
    const i = source.item.findIndex(r => r.name === anchorName);
    source.item.splice(i + 1, 0, ...items);
}

const waFolder = findFolder(j, 'Loan Applications and Compliance');
const loanFolder = findFolder(j, 'Loans, Disbursement and Collections');
const compFolder = findFolder(j, 'Compliance and Review');

let target;
if (compFolder) target = compFolder;
else if (waFolder) target = waFolder;
else target = loanFolder;

// compliance requests: insert after "Verify Application Document" if present
if (compFolder) {
    insertAfter(compFolder, 'Verify Application Document', compliance.slice(0, 3));
    insertAfter(compFolder, 'Approve Application', compliance.slice(3));
} else if (waFolder) {
    insertAfter(waFolder, 'Verify Application Document', compliance);
}
// loan administration: insert after "Reverse Payment"
if (loanFolder) insertAfter(loanFolder, 'Reverse Payment', loansAdmin);

fs.writeFileSync(f, JSON.stringify(j, null, 4) + '\n');
console.log('done; compliance target folder =', target ? target.name : '(none)');
