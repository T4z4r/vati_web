const fs = require('fs');
const FILE = 'postman/VATI-Microfinance-API.postman_collection.json';
const j = JSON.parse(fs.readFileSync(FILE, 'utf8'));
const b = '{{base_url}}/api/v1';

const R = (method, url, header, body) => ({ method, header, body, url });

const jsonSend = {
    mode: 'raw',
    raw: '',
    options: { raw: { language: 'json' } },
};

function mk(method, url, raw) {
    return {
        method,
        header: [{ key: 'Content-Type', value: 'application/json' }],
        body: { mode: 'raw', raw, options: { raw: { language: 'json' } } },
        url,
    };
}

function req(name, method, url, raw) {
    return { name, request: mk(method, url, raw), response: [] };
}

const waBase = b + '/loan-applications/{{loan_application_id}}';
const wa = [
    req('Update Applicant Compliance', 'PUT', waBase + '/compliance/applicant',
        '{\n  "accept_declaration": true,\n  "applicant_signature": "{{applicant_signature}}",\n  "applicant_thumbprint": "{{applicant_thumbprint}}"\n}'),
    req('Add Guarantor Compliance', 'POST', waBase + '/compliance/guarantors',
        '{\n  "guarantor_type": "family",\n  "name": "Jane Doe",\n  "relationship": "Sister",\n  "phone": "0712345678",\n  "national_id": "{{guarantor_national_id}}",\n  "signature": "{{guarantor_signature}}",\n  "thumbprint": "{{guarantor_thumbprint}}",\n  "accept_declaration": true\n}'),
    req('Update Nominees Compliance', 'PUT', waBase + '/compliance/nominees',
        '{\n  "nominees": [\n    { "name": "Grace Nominee", "relationship": "Daughter", "percentage": 100 }\n  ]\n}'),
    req('Cancel Application', 'POST', waBase + '/cancel',
        '{\n  "reason": "Applicant requested withdrawal"\n}'),
];

const admin = [
    B('Replace Member Passbook', 'POST', b + '/members/{{member_id}}/passbook-replacements',
        '{\n  "reason": "damaged",\n  "payment_reference": "{{payment_reference}}"\n}'),
    B('Issue Default Notice', 'POST', b + '/loans/{{loan_id}}/default-notices',
        '{\n  "delivery_method": "hand",\n  "delivery_reference": "{{delivery_reference}}"\n}'),
    B('Authorize Loan Clearance', 'POST', b + '/loans/{{loan_id}}/clearance',
        '{\n  "comments": "All dues settled, collateral released."\n}'),
];

function findFolder(nodes, name) {
    for (const n of nodes) if (n.name === name && Array.isArray(n.item)) return n;
    return null;
}

const waFolder = findFolder(j.item, 'Loan Applications and Witnesses');
const loansFolder = findFolder(j.item, 'Loans, Disbursement and Collections');

if (waFolder) waFolder.item = waFolder.item.concat(...wa);
if (loansFolder) loansFolder.item = loansFolder.item.concat(...admin);

fs.writeFileSync(FILE, JSON.stringify(j, null, 4) + '\n');
console.log('waFolder=' + !!waFolder + ' loansFolder=' + !!loansFolder +
    ' inserted compliance=' + (waFolder ? wa.length : 0) + ' admin=' + (loansFolder ? admin.length : 0));
