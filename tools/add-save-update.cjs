const fs = require('fs');
const FILE = 'postman/VATI-Microfinance-API.postman_collection.json';
const j = JSON.parse(fs.readFileSync(FILE, 'utf8'));
const B = '{{base_url}}/api/v1';
const appId = '{{loan_application_id}}';
const memId = '{{member_id}}';

// Build one Postman item object (request + empty response) for a save/update call.
function saveReq(name, method, url, rawBody, description) {
    return {
        name,
        request: {
            method,
            header: [{ key: 'Content-Type', value: 'application/json', type: 'text' }],
            url,
            description: description || '',
            body: {
                mode: 'raw',
                raw: rawBody,
                options: { raw: { language: 'json' } },
            },
        },
        response: [],
    };
}

// Serialize an item to 4-space-pretty then prefix every line with 16 spaces
// (folder item level), ending the block with ',' so splicing stays valid JSON.
function ser(item) {
    return JSON.stringify(item, null, 4)
        .split('\n')
        .map(l => (l.trim() === '' ? l : ' '.repeat(16) + l));
}

// Walk the top-level folders, return the one with the given name.
function findFolder(folders, name) {
    for (const n of folders) if (n.name === name && Array.isArray(n.item)) return n;
    return null;
}

// --- New SAVE / UPDATE requests (mirroring the web save operations) ---

// Loan applications & witnesses -> compliance save/update
const waBase = B + '/loan-applications/' + appId + '/compliance';
const complianceBlocks = [
    saveReq('Save Applicant Compliance', 'PUT', waBase + '/applicant',
        '{\n  "accept_declaration": true,\n  "applicant_signature": "{{applicant_signature}}",\n  "applicant_thumbprint": "{{applicant_thumbprint}}"\n}',
        'Saves the applicant declaration, signature and thumbprint (PKI capture).'),
    saveReq('Save Guarantor Compliance', 'POST', waBase + '/guarantors',
        '{\n  "guarantor_type": "family",\n  "name": "Jane Doe",\n  "relationship": "Sister",\n  "phone": "0712345678",\n  "national_id": "{{guarantor_national_id}}",\n  "signature": "{{guarantor_signature}}",\n  "thumbprint": "{{guarantor_thumbprint}}",\n  "accept_declaration": true\n}',
        'Saves a guarantor with signature, thumbprint and declaration acceptance.'),
    saveReq('Save Nominees Compliance', 'PUT', waBase + '/nominees',
        '{\n  "nominees": [\n    {\n      "name": "Grace Nominee",\n      "relationship": "Daughter",\n      "percentage": 100\n    }\n  ]\n}',
        'Saves the nominee (beneficiary) distribution for the application.'),
    saveReq('Cancel Application', 'POST', B + '/loan-applications/' + appId + '/cancel',
        '{\n  "reason": "Applicant requested withdrawal"\n}',
        'Saves the cancellation of an application with a reason.'),
];

// Loans, disbursement & collections -> loan administration save/update
const loanBase = B + '/loans/{{loan_id}}';
const adminBlocks = [
    saveReq('Replace Member Passbook', 'POST', B + '/members/' + memId + '/passbook-replacements',
        '{\n  "reason": "damaged",\n  "payment_reference": "{{payment_reference}}"\n}',
        'Saves a passbook replacement request for the member.'),
    saveReq('Issue Default Notice', 'POST', loanBase + '/default-notices',
        '{\n  "delivery_method": "hand",\n  "delivery_reference": "{{delivery_reference}}"\n}',
        'Saves a default notice issued against an overdue loan.'),
    saveReq('Authorize Loan Clearance', 'POST', loanBase + '/clearance',
        '{\n  "comments": "All dues settled, collateral released."\n}',
        'Saves the loan clearance authorization after full settlement.'),
];

// --- Splice into the correct folders (top-level folders each have a flat "item" array) ---
const wa = findFolder(j.item, 'Loan Applications and Witnesses');
const ad = findFolder(j.item, 'Loans, Disbursement and Collections');

if (wa) wa.item = wa.item.concat(complianceBlocks);
if (ad) ad.item = ad.item.concat(adminBlocks);

// Validate before writing
try {
    JSON.stringify(j);
} catch (e) {
    console.log('ERROR building: ' + e.message);
    process.exit(1);
}
fs.writeFileSync(FILE, JSON.stringify(j, null, 4) + '\n');
console.log('wa=' + (!!wa ? wa.item.length : 'MISSING') + ', ad=' + (!!ad ? ad.item.length : 'MISSING'));
console.log('inserted compliance=' + complianceBlocks.length + ', admin=' + adminBlocks.length);
