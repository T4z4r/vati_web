const fs = require('fs');
const FILE = 'postman/VATI-Microfinance-API.postman_collection.json';
const L = fs.readFileSync(FILE, 'utf8').split(/\r?\n/);
const B = '{{base_url}}/api/v1';

// --- Build one request-item block (base indent = 16 spaces, matching existing folder items) ---
function reqBlock(name, method, url, desc, rawJson) {
    const out = [];
    const P = s => out.push(s);
    const V = (key, val) => P(' '.repeat(20) + '"' + key + '": ' + val + ',');

    P(' '.repeat(16) + '{');
    P(' '.repeat(16) + '    "name": ' + JSON.stringify(name) + ',');
    P(' '.repeat(16) + '    "request": {');
    V('method', JSON.stringify(method));
    P(' '.repeat(16) + '        "header": [');
    P(' '.repeat(16) + '            {');
    P(' '.repeat(16) + '                "key": "Content-Type",');
    P(' '.repeat(16) + '                "value": "application/json",');
    P(' '.repeat(16) + '                "type": "text"');
    P(' '.repeat(16) + '            }');
    P(' '.repeat(16) + '        ],');
    V('url', JSON.stringify(url));
    V('description', JSON.stringify(desc));
    P(' '.repeat(16) + '        "descriptionTitle": "",');
    P(' '.repeat(16) + '        "body": {');
    P(' '.repeat(16) + '            "mode": "raw",');
    P(' '.repeat(16) + '            "raw": ' + JSON.stringify(rawJson) + ',');
    P(' '.repeat(16) + '            "options": {');
    P(' '.repeat(16) + '                "raw": {');
    P(' '.repeat(16) + '                    "language": "json"');
    P(' '.repeat(16) + '                }');
    P(' '.repeat(16) + '            }');
    P(' '.repeat(16) + '        }');
    P(' '.repeat(16) + '    },');
    P(' '.repeat(16) + '    "response": []');
    P(' '.repeat(16) + '}');
    return out;
}

// --- helper to JSON-escape a json object string (as stored in collection raw) ---
const jsonStr = o => JSON.stringify(o).replace(/"/g, '\\"');

// --- The new save/update + list requests (compliance) ---
const wb = B + '/loan-applications/{{loan_application_id}}/compliance';
const complianceBlocks = [];
const push = (name, method, url, desc, rawJson) =>
    complianceBlocks.push(reqBlock(name, method, url, desc, rawJson));

push('Update Applicant Compliance', 'PUT', wb + '/applicant',
    'Captures/saves the applicant declaration, signature and thumbprint.',
    '{\n  "accept_declaration": true,\n  "applicant_signature": "{{applicant_signature}}",\n  "applicant_thumbprint": "{{applicant_thumbprint}}"\n}');
push('Add Compliance Guarantor', 'POST', wb + '/guarantors',
    'Saves a guarantor with signature, thumbprint and acceptance.',
    '{\n  "guarantor_type": "family",\n  "name": "Jane Doe",\n  "relationship": "Sister",\n  "phone": "0712345678",\n  "national_id": "{{guarantor_national_id}}",\n  "signature": "{{guarantor_signature}}",\n  "thumbprint": "{{guarantor_thumbprint}}",\n  "accept_declaration": true\n}');
push('Update Nominees Compliance', 'PUT', wb + '/nominees',
    'Saves the nominee (beneficiary) distribution for the application.',
    '{\n  "nominees": [\n    {\n      "name": "Grace Nominee",\n      "relationship": "Daughter",\n      "percentage": 100\n    }\n  ]\n}');

// --- The new loan-administration save/update requests ---
const ab = B + '/loans/{{loan_id}}';
const adminBlocks = [];
const apush = (name, method, url, desc, rawJson) =>
    adminBlocks.push(reqBlock(name, method, url, desc, rawJson));

apush('Replace Member Passbook', 'POST', B + '/members/{{member_id}}/passbook-replacements',
    'Records a member passbook replacement with reason and payment.',
    '{\n  "reason": "damaged",\n  "payment_reference": "{{payment_reference}}"\n}');
apush('Issue Default Notice', 'POST', ab + '/default-notices',
    'Issues a default notice to the borrower (save/issue).',
    '{\n  "delivery_method": "hand",\n  "delivery_reference": "{{delivery_reference}}"\n}');
apush('Authorize Loan Clearance', 'POST', ab + '/clearance',
    'Saves the clearance authorization after the borrower settles.',
    '{\n  "comments": "All dues settled. Collateral released."\n}');

// --- Splice the compliance folder (anchor: after "Reject Application" item) ---
function findLine(substr, from) {
    for (let i = from || 0; i < L.length; i++) if (L[i].indexOf(substr) >= 0) return i;
    return -1;
}
function folderClose(requestName) {
    // find the item's closing '}' of the request, then the folder's '],'
    const rn = findLine('"' + requestName + '"');
    let i = rn;
    // walk to the item-array closing of this folder: find line that is exactly '            ],' (12 spaces)
    while (i < L.length) {
        const t = L[i];
        if (/^            \],$/.test(t)) return i所在的;
        i++;
    }
    return -1;
}

// compliance spliced into Loan Applications and Witnesses
const cIdx = folderClose('Reject Application');
// admin spliced into Loans, Disbursement and Collections (anchor: Reverse Payment)
const aIdx = folderClose('Reverse Payment');

function splice(idx, blocks) {
    const insert = [];
    blocks.forEach((blk, bi) => {
        const last = bi === blocks.length - 1;
        blk.forEach((ln, li) => {
            insert.push(li === blk.length - 1 && !last ? ln.replace(/}\s*$/, '},') : ln);
        });
    });
    L.splice(idx, 0, ...insert);
}

console.log('cIdx=' + cIdx + ', aIdx=' + aIdx);
if (cIdx >= 0) splice(cIdx, complianceBlocks);
if (aIdx >= 0) {
    // recompute admin index after compliance insertion shifted it (admin folder is after waiver folder)
    const shifted = aIdx + (cIdx >= 0 ? insertedCount : 0);
    // simpler: re-find admin anchor from scratch
    const finalAdmin = folderClose('Reverse Payment');
    splice(finalAdmin, adminBlocks);
}

const out = L.join('\n');
try {
    JSON.parse(out);
    fs.writeFileSync(FILE, out + '\n');
    console.log('OK valid JSON, bytes=' + Buffer.byteLength(out));
} catch (e) {
    fs.writeFileSync(FILE + '.bad.json', out);
    console.log('INVALID: ' + e.message);
}
