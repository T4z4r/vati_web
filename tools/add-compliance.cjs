const fs = require('fs');
const FILE = 'postman/VATI-Microfinance-API.postman_collection.json';
const j = JSON.parse(fs.readFileSync(FILE, 'utf8'));
const B = '{{base_url}}/api/v1';

function mkReq(method, url, raw, description) {
    return {
        name: null,
        request: {
            method,
            header: [{ key: 'Content-Type', value: 'application/json' }],
            body: {
                mode: 'raw',
                raw,
                options: { raw: { language: 'json' } },
            },
            url,
            description: description || '',
        },
        response: [],
    };
}

// --- Loan Applications and Witnesses: compliance save/update ---
const waBase = B + '/loan-applications/{{loan_application_id}}/compliance';
const complianceItems = [
    mkReq('PUT', waBase + '/applicant',
        '{\n  "accept_declaration": true,\n  "applicant_signature": "{{applicant_signature}}",\n  "applicant_thumbprint": "{{applicant_thumbprint}}"\n}',
        'Captures applicant compliance: declaration, signature, thumbprint.'),
    mkReq('POST', waBase + '/guarantors',
        '{\n  "guarantor_type": "family",\n  "name": "Jane Doe",\n  "relationship": "Sister",\n  "phone": "0712345678",\n  "national_id": "{{guarantor_national_id}}",\n  "signature": "{{guarantor_signature}}",\n  "thumbprint": "{{guarantor_thumbprint}}",\n  "accept_declaration": true\n}',
        'Registers a guarantor with compliance signature and thumbprint.'),
    mkReq('PUT', waBase + '/nominees',
        '{\n  "nominees": [\n    {\n      "name": "Grace Nominee",\n      "relationship": "Daughter",\n      "percentage": 100\n    }\n  ]\n}',
        'Updates the nominee distribution for the application.'),
    mkReq('POST', B + '/loan-applications/{{loan_application_id}}/cancel',
        '{\n  "reason": "Applicant requested withdrawal"\n}',
        'Cancels / withdraws the loan application with a reason.'),
];

// --- Loans, Disbursement and Collections: loan administration save/update ---
const adminItems = [
    mkReq('POST', B + '/members/{{member_id}}/passbook-replacements',
        '{\n  "reason": "damaged",\n  "payment_reference": "{{payment_reference}}"\n}',
        'Records a member passbook replacement request.'),
    mkReq('POST', B + '/loans/{{loan_id}}/default-notices',
        '{\n  "delivery_method": "hand",\n  "delivery_reference": "{{delivery_reference}}"\n}',
        'Issues a default notice for an overdue loan.'),
    mkReq('POST', B + '/loans/{{loan_id}}/clearance',
        '{\n  "comments": "All dues settled, collateral released."\n}',
        'Authorizes clearance of the loan after full settlement.'),
];

function findFolder(node, name) {
    if (!node || !Array.isArray(node)) return null;
    for (const n of node) if (n.name === name && Array.isArray(n.item)) return n;
    return null;
}

const waFolder = findFolder(j.item, 'Loan Applications and Witnesses');
const loansFolder = findFolder(j.item, 'Loans, Disbursement and Collections');

if (waFolder) waFolder.item = waFolder.item.concat(complianceItems);
if (loansFolder) loansFolder.item = loansFolder.item.concat(adminItems);

fs.writeFileSync(FILE, JSON.stringify(j, null, 4) + '\n');
console.log('wa=' + !!waFolder + ', loans=' + !!loansFolder +
    ', inserted=' + complianceItems.length + '+' + adminItems.length);
