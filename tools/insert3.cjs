const fs = require('fs');
const FILE = 'postman/VATI-Microfinance-API.postman_collection.json';
const L = fs.readFileSync(FILE, 'utf8').split(/\r?\n/);

function blk(indentSpaces, name, method, url, raw) {
    const p = ' '.repeat(indentSpaces);
    const s = ' '.repeat(indentSpaces + 2);
    const s2 = ' '.repeat(indentSpaces + 4);
    return [
        p + '{',
        p + '    "name": ' + JSON.stringify(name) + ',',
        p + '    "request": {',
        p + '        "method": ' + JSON.stringify(method) + ',',
        p + '        "header": [',
        p + '            {',
        p + '                "key": "Content-Type",',
        p + '                "value": "application/json"',
        p + '            }',
        p + '        ],',
        p + '        "url": ' + JSON.stringify(url) + ',',
        p + '        "description": "",',
        p + '        "body": {',
        p + '            "mode": "raw",',
        p + '            "raw": ' + JSON.stringify(raw),
        p + '        }',
        p + '    },',
        p + '    "response": []',
        p + '}',
    ].join('\n');
}

// ---------- Compliance items (Loan Applications and Witnesses folder) ----------
const wBase = '{{base_url}}/api/v1/loan-applications/{{loan_application_id}}/compliance';
const cBase = wBase + '';
const appUrl = '{{base_url}}/api/v1/loan-applications/{{loan_application_id}}';
const waItems = [
    blk(16, 'Update Applicant Compliance', 'PUT', wBase + '/applicant',
        '{\n  "accept_declaration": true,\n  "applicant_signature": "{{applicant_signature}}",\n  "applicant_thumbprint": "{{applicant_thumbprint}}"\n}'),
    blk(16, 'Add Compliance Guarantor', 'POST', wBase + '/guarantors',
        '{\n  "name": "Jane Doe",\n  "relationship": "Sister",\n  "phone": "0712345678",\n  "national_id": "{{guarantor_national_id}}",\n  "signature": "{{guarantor_signature}}",\n  "thumbprint": "{{guarantor_thumbprint}}",\n  "accept_declaration": true\n}'),
    blk(16, 'Update Nominees Compliance', 'PUT', wBase + '/nominees',
        '{\n  "nominees": [\n    {\n      "name": "Grace Nominee",\n      "relationship": "Daughter",\n      "percentage": 100\n    }\n  ]\n}'),
    blk(16, 'Cancel Application', 'POST', appUrl + '/cancel',
        '{\n  "reason": "Applicant requested withdrawal"\n}'),
];

// ---------- Loan administration items (Loans, Disbursement and Collections folder) ----------
const b = '{{base_url}}/api/v1';
const adItems = [
    blk(16, 'Replace Member Passbook', 'POST', b + '/members/{{member_id}}/passbook-replacements',
        '{\n  "reason": "damaged",\n  "payment_reference": "{{payment_reference}}"\n}'),
    blk(16, 'Issue Default Notice', 'POST', b + '/loans/{{loan_id}}/default-notices',
        '{\n  "delivery_method": "hand",\n  "delivery_reference": "{{delivery_reference}}"\n}'),
    blk(16, 'Authorize Loan Clearance', 'POST', b + '/loans/{{loan_id}}/clearance',
        '{\n  "comments": "All dues settled. Collateral released."\n}'),
];

// ---------- locate folder-close brace line (the "    ]," before the folder's closing "}") ----------
function idxLine(pred) {
    for (let i = 0; i < L.length; i++) if (pred(L[i], i)) return i;
    return -1;
}
function spliceBeforeFolder(itemName) {
    const start = idxLine(l => l.includes('"name": "' + itemName + '"'));
    const bOpen = idxLine((l, i) => i > start && l.trim() === '"item": [');
    const bClose = idxLine((l, i) => i > bOpen && l.trim() === '],');
    return bClose violating;
}

const waClose = spliceBeforeFolder('Reject Application');
fs.writeFileSync('DEBUG.txt', 'waClose=' + waClose + '\n');
const adminClose = spliceBeforeFolder('Reverse Payment');
fs.appendFileSync('DEBUG.txt', 'adminClose=' + adminClose + '\n');
console.log('waClose=' + waClose + ' adminClose=' + adminClose);
