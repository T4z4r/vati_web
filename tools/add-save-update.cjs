const fs = require('fs');
const FILE = 'postman/VATI-Microfinance-API.postman_collection.json';
const source = fs.readFileSync(FILE, 'utf8');
const L = source.split(/\r?\n/);
const B = '{{base_url}}/api/v1';

// Build ONE item object with Postman's exact shape (method/header/url/description/body),
// then serialize as 4-space JSON re-indented to 16 spaces = matches existing folder items.
function itemobj(name, method, url, raw, description) {
    return {
        name,
        request: {
            method,
            header: [{ key: 'Content-Type', value: 'application/json', type: 'text' }],
            url,
            description: description || '',
            body: {
                mode: 'raw',
                raw,
                options: { raw: { language: 'json' } },
            },
        },
        response: [],
    };
}

// serialize an item to lines at indent 16 (folder item level), each followed by ','
// except the last line (the closing '}').
function ser(item, last) {
    const body = JSON.stringify(item, null, 4)
        .split('\n')
        .map(l => (l.trim() === '' ? l : ' '.repeat(16) + l));
    if (!last) body[body.length - 1] = body[body.length - 1].replace(/}$/, '},');
    return body;
}

const complianceSan = [];
const appUrl = '{{base_url}}/api/v1/loan-applications/{{loan_application_id}}/compliance';
const mkSave = (nm, m, url, raw, desc) => complianceSan.push({ nm, m, url, raw, desc });

mkSave('Save Applicant Compliance', 'PUT', appUrl + '/applicant',
    '{\n  "accept_declaration": true,\n  "applicant_signature": "{{applicant_signature}}",\n  "applicant_thumbprint": "{{applicant_thumbprint}}"\n}',
    'Saves the applicant declaration acceptance with signature and thumbprint.');
mkSave('Save Guarantor Compliance', 'POST', appUrl + '/guarantors',
    '{\n  "guarantor_type": "family",\n  "name": "Jane Doe",\n  "relationship": "Sister",\n  "phone": "0712345678",\n  "national_id": "{{guarantor_national_id}}",\n  "signature": "{{guarantor_signature}}",\n  "thumbprint": "{{guarantor_thumbprint}}",\n  "accept_declaration": true\n}',
    'Saves a guarantor with their compliance signature and thumbprint.');
mkSave('Save Nominees Compliance', 'PUT', appUrl + '/nominees',
    '{\n  "nominees": [\n    {\n      "name": "Grace Nominee",\n      "relationship": "Daughter",\n      "percentage": 100\n    }\n  ]\n}',
    'Saves the nominee (beneficiary) distribution for the application.');

const loanAdminSave = [];
const mkAdm = (nm, m, url, raw, desc) => loanAdminSave.push({ nm, m, url, raw, desc });
mkAdm('Replace Member Passbook', 'POST', '{{base_url}}/api/v1/members/{{member_id}}/passbook-replacements',
    '{\n  "reason": "damaged",\n  "payment_reference": "{{payment_reference}}"\n}',
    'Saves a passbook replacement arranged for a member.');
mkAdm('Issue Default Notice', 'POST', '{{base_url}}/api/v1/loans/{{loan_id}}/default-notices',
    '{\n  "delivery_method": "hand",\n  "delivery_reference": "{{delivery_reference}}"\n}',
    'Saves a default notice issued against an overdue loan.');
mkAdm('Authorize Loan Clearance', 'POST', '{{base_url}}/api/v1/loans/{{loan_id}}/clearance',
    '{\n  "comments": "All dues settled, collateral released."\n}',
    'Saves the clearance authorization after full settlement.');

// Find the line holding the folder's item-array close: after the last request item
// of each target folder. We reposition by finding the folder item-array open then its close.
function folderItemClose(folderName) {
    const open = L.findIndex(x => x.includes('"name": "' + folderName + '"'));
    // after open, find the item-array opening '[' line with 12-space indent
    let arrOpen = -1, depth = 0, started = false;
    for (let i = open + 1; i < L.length; i++) {
        const t = L[i];
        const lead = t.length - t.replace(/^\s+/, '').length;
        if (!started && t === '            "item": [') { started = true; arrOpen = i; }
        if (started) {
            const o = (t.match(/{/g) || []).length, c = (t.match(/}/g) || []).length;
            depth += o - c;
            // item-array closes on a line ending with '],' at 12-space indent after depth returns to 0
            if (depth === 0 && /^            \],$/.test(t)) return i;
        }
    }
    return -1;
}

const waClose = folderItemClose('Loan Applications and Witnesses');
const adClose = folderItemClose('Loans, Disbursement and Collections');

function splice(folderName, items, closeLine) {
    if (closeLine < 0) { console.log('MISS folder ' + folderName); return; }
    const lines = [];
    items.forEach((o, idx) => lines.push(...ser(itemobj(o.nm, o.m, o.url, o.raw, o.desc), idx === items.length - 1)));
    L.splice(closeLine, 0, ...lines);
    console.log('inserted ' + items.length + ' into folder ' + folderName + ' (close L' + (closeLine + 1) + ')');
}

splice('Loan Applications and Witnesses', complianceSan, waClose);
// recompute admin close after the earlier splice shifted lines
const adClose2 = folderItemClose('Loans, Disbursement and Collections');
splice('Loans, Disbursement and Collections', loanAdminSave, adClose2);

fs.writeFileSync(FILE, L.join('\n') + '\n');
try {
    const check = JSON.parse(L.join('\n'));
    console.log('VALID JSON ok; folders=' + check.item.length + '; total requests now present:');
} catch (e) {
    console.log('INVALID JSON: ' + e.message);
}
