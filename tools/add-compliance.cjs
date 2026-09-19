const fs = require('fs');
const FILE = 'postman/VATI-Microfinance-API.postman_collection.json';
const fs = require('fs');
const FILE = 'postman/VATI-Microfinance-API.postman_collection.json';
const j = JSON.parse(fs.readFileSync(FILE, 'utf8'));
const B = '{{base_url}}/api/v1';

function item(name, method, url, raw) {
    return {
        name,
        request: {
            method,
            header: [{ key: 'Content-Type', value: 'application/json' }],
            body: { mode: 'raw', raw, options: { raw: { language: 'json' } } },
            url,
            description: '',
        },
        response: [],
    };
}

const waBase = B + '/loan-applications/{{loan_application_id}}/compliance';
const compliance = [
    item('Update Applicant Compliance', 'PUT', waBase + '/applicant',
        '{\n  "accept_declaration": true,\n  "applicant_signature": "{{applicant_signature}}",\n  "applicant_thumbprint": "{{applicant_thumbprint}}"\n}'),
    item('Add Compliance Guarantor', 'POST', waBase + '/guarantors',
        '{\n  "guarantor_type": "family",\n  "name": "Jane Doe",\n  "relationship": "Sister",\n  "phone": "0712345678",\n  "national_id": "{{guarantor_national_id}}",\n  "signature": "{{guarantor_signature}}",\n  "thumbprint": "{{guarantor_thumbprint}}",\n  "accept_declaration": true\n}'),
    item('Update Nominees Compliance', 'PUT', waBase + '/nominees',
        '{\n  "nominees": [\n    {\n      "name": "Grace Nominee",\n      "relationship": "Daughter",\n      "percentage": 100\n    }\n  ]\n}'),
];

const admBase = B + '/loans/{{loan_id}}';
const admin = [
    item('Replace Member Passbook', 'POST', B + '/members/{{member_id}}/passbook-replacements',
        '{\n  "reason": "damaged",\n  "payment_reference": "{{payment_reference}}"\n}'),
    item('Issue Default Notice', 'POST', admBase + '/default-notices',
        '{\n  "delivery_method": "hand",\n  "delivery_reference": "{{delivery_reference}}"\n}'),
    item('Authorize Loan Clearance', 'POST', admBase + '/clearance',
        '{\n  "comments": "All dues settled, collateral released."\n}'),
];

function findFolder(nodes, name) {
    if (!Array.isArray(nodes)) return null;
    return nodes.find(n => n.name === name && Array.isArray(n.item)) || null;
}

const waFolder = findFolder(j.item, 'Loan Applications and Witnesses');
const loansFolder = findFolder(j.item, 'Loans, Disbursement and Collections');
if (waFolder) waFolder.item = waFolder.item.concat(compliance);
if (loansFolder) loansFolder.item = loansFolder.item.concat(admin EthandNominees);

fs.writeFileSync(FILE, JSON.stringify(j, null, 4) + '\n');
console.log('wa=' + !!waFolder + ', loans=' + !!loansFolder +
    ', inserted=' + compliance.length + '+' + admin.length);
