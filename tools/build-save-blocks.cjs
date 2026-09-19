const fs = require('fs');
const FILE = 'postman/VATI-Microfinance-API.postman_collection.json';
const L = fs.readFileSync(FILE, 'utf8').split(/\r?\n/);
const B = '{{base_url}}/api/v1';

// Build one Postman "item" pretty text block (base indent 16 = folder request item level),
// byte-matching the existing multi-line pretty format used by this collection.
function itemBlock(name, method, url, description, rawBody) {
    const pad = ' '.repeat(16);
    const p = n => ' '.repeat(n);
    const lines = [];
    const push = s => lines.push(s [+s.length === undefined ? 0 : 0]);
    const l = [];
    const W = s => l.push(s);

    W(pad + '{');
    W(pad + '    "name": ' + JSON.stringify(name) + ',');
    W(pad + '    "request": {');
    W(pad + '        "method": ' + JSON.stringify(method) + ',');
    W(pad + '        "header": [');
    W(pad + '            {');
    W(pad + '                "key": "Content-Type",');
    W(pad + '                "value": "application/json",');
    W(pad + '                "type": "text"');
    W(pad + '            }');
    W(pad + '        ],');
    W(pad + '        "url": ' + JSON.stringify(url) + ',');
    W(pad + '        "description": ' + JSON.stringify(description) + ',');
    W(pad + '        "body": {');
    W(pad + '            "mode": "raw",');
    W(pad + '            "raw": ' + JSON.stringify(rawBody) + ',');
    W(pad + '            "options": {');
    W(pad + '                "raw": {');
    W(pad + '                    "language": "json"');
    W(pad + '                }');
    W(pad + '            }');
    W(pad + '        }');
    W(pad + '    },');
    W(pad + '    "response": []');
    W(pad + '}');
    return l;
}

// --- Compliance save/update requests (go into "Loan Applications and Witnesses") ---
const waBase = B + '/loan-applications/{{loan_application_id}}/compliance';
const complianceBlocks = []
    .concat(itemBlock('Save Applicant Compliance', 'PUT', waBase + '/applicant',
        'Saves the applicant declaration acceptance, signature and thumbprint.',
        '{\n  "accept_declaration": true,\n  "applicant_signature": "{{applicant_signature}}",\n  "applicant_thumbprint": "{{applicant_thumbprint}}"\n}'))
    .concat(itemBlock('Save Guarantor Compliance', 'POST', waBase + '/guarantors',
        'Saves a guarantor with signature, thumbprint and declaration acceptance.',
        '{\n  "guarantor_type": "family",\n  "name": "Jane Doe",\n  "relationship": "Sister",\n  "phone": "0712345678",\n  "national_id": "{{guarantor_national_id}}",\n  "signature": "{{guarantor_signature}}",\n  "thumbprint": "{{guarantor_thumbprint}}",\n  "accept_declaration": true\n}'))
    .concat(itemBlock('Save Nominees Compliance', 'PUT', waBase + '/nominees',
        'Saves the nominee (beneficiary) distribution for the application.',
        '{\n  "nominees": [\n    {\n      "name": "Grace Nominee",\n      "relationship": "Daughter",\n      "percentage": 100\n    }\n  ]\n}'))
    .concat(itemBlock('Cancel Application', 'POST', B + '/loan-applications/{{loan_application_id}}/cancel',
        'Saves application cancellation with a reason.',
        '{\n  "reason": "Applicant requested withdrawal"\n}'));

const adminBlocks = []
    .concat(itemBlock('Replace Member Passbook', 'POST', B + '/members/{{member_id}}/passbook-replacements',
        'Saves a passbook replacement request for the member.',
        '{\n  "reason": "damaged",\n  "payment_reference": "{{payment_reference}}"\n}'))
    .concat(itemBlock('Issue Default Notice', 'POST', B + '/loans/{{loan_id}}/default-notices',
        'Saves issuance of a default notice against an overdue loan.',
        '{\n  "delivery_method": "hand",\n  "delivery_reference": "{{delivery_reference}}"\n}'))
    .concat(itemBlock('Authorize Loan Clearance', 'POST', B + '/loans/{{loan_id}}/clearance',
        'Saves clearance authorization after full settlement.',
        '{\n  "comments": "All dues settled, collateral released."\n}'));

console.log('blocks built: compliance=' + complianceBlocks.length + ' admin=' + adminBlocks.length);
