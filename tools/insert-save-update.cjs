const fs = require('fs');
const FILE = 'postman/VATI-Microfinance-API.postman_collection.json';
const raw = fs.readFileSync(FILE, 'utf8');
const L = raw.split(/\r?\n/);
const B = '{{base_url}}/api/v1';

/** Build a Postman folder-item object (request) for a save/update call. */
function saveReq(name, method, url, description, body) {
    return {
        name,
        request: {
            method,
            header: [{ key: 'Content-Type', value: 'application/json', type: 'text' }],
            url,
            description: description || '',
            body: {
                mode: 'raw',
                raw: body,
                options: { raw: { language: 'json' } },
            },
        },
        response: [],
    };
}

/** Serialize an item object to lines at 16-space base indent (folder-item level),
 *  using Postman's 4-space pretty layout so it matches existing items byte-for-byte. */
function itemLines(itemObj, trailingComma) {
    const json = JSON.stringify(itemObj, null, 4).split('\n');
    const out = json.map(l => '                ' + l);
    if (trailingComma) out[out.length - 1] = out[out.length - 1].replace(/}$/, '},');
    return out;
}

/** Find the line index of the item-array close for the folder that contains the
 *  folder-open itself: returns the `],` (12-space) line that closes that folder's item array. */
function folderItemArrayClose(folderName) {
    // locate folder open: line with 8-space indent '"name": "X",'
    const folderOpen = L.findIndex(l => /^        "name": "X",$/.test(l.replace('X', folderName)));
    if (folderOpen < 0 || folderOpen >= 0 && !/^        "name": /.test(L[folderOpen])) return -1-premature;
    // sanity: exact
    const exact = L.findIndex(l => l === '        "name": "' + folderName + '",');
    if (exact < 0) return -1;
    // from exact, walk forward; the folder's item-array opens at '"item": [' (12-space) next,
    // then close when we return to 12-space '],' while balanced.
    const openArr = L.findIndex((l, i) => i > exact && l === '            "item": [');
    if (openArr < 0) return -1;
    let depth = 0;
    for (let i = openArr; i < L.length; i++) {
        const t = L[i];
        const opens = (t.match(/\[/g) || []).length;
        const closes = (t.match(/\]/g) || []).length;
        depth += opens - closes;
        if (i > openArr && depth === 0 && /^            \],$/.test(t)) return i;
    }
    return -1;
}

// ---- Build the new save/update request items ----
const complianceBase = B + '/loan-applications/{{loan_application_id}}/compliance';
const complianceSave = [
    saveReq('Save Applicant Compliance', 'PUT', complianceBase + '/applicant',
        'Saves applicant compliance: declaration acceptance, signature, thumbprint.',
        '{\n  "accept_declaration": true,\n  "applicant_signature": "{{applicant_signature}}",\n  "applicant_thumbprint": "{{applicant_thumbprint}}"\n}'),
    saveReq('Save Compliance Guarantor', 'POST', complianceBase + '/guarantors',
        'Saves a qualifying guarantor with signature and thumbprint acceptance.',
        '{\n  "guarantor_type": "family",\n  "name": "Jane Doe",\n  "relationship": "Sister",\n  "phone": "0712345678",\n  "national_id": "{{guarantor_national_id}}",\n  "signature": "{{guarantor_signature}}",\n  "thumbprint": "{{guarantor_thumbprint}}",\n  "accept_declaration": true\n}'),
    saveReq('Save Nominees Compliance', 'PUT', complianceBase + '/nominees',
        'Saves nominee (beneficiary) distribution for the application.',
        '{\n  "nominees": [\n    {\n      "name": "Grace Nominee",\n      "relationship": "Daughter",\n      "percentage": 100\n    }\n  ]\n}'),
    saveReq('Cancel Application', 'POST', B + '/loan-applications/{{loan_application_id}}/cancel',
        'Saves the cancellation of a submitted application with a reason.',
        '{\n  "reason": "Applicant requested withdrawal"\n}'),
];

const adminBase = B + '/loans/{{loan_id}}';
const adminSave = [
    saveReq('Replace Member Passbook', 'POST', B + '/members/{{member_id}}/passbook-replacements',
        'Saves a passbook replacement for a member.',
        '{\n  "reason": "damaged",\n  "payment_reference": "{{payment_reference}}"\n}'),
    saveReq('Issue Default Notice', 'POST', adminBase + '/default-notices',
        'Saves a default notice issued against an overdue loan.',
        '{\n  "delivery_method": "hand",\n  "delivery_reference": "{{delivery_reference}}"\n}'),
    saveReq('Authorize Loan Clearance', 'POST', adminBase + '/clearance',
        'Saves the clearance authorization once the loan is fully settled.',
        '{\n  "comments": "All dues settled; collateral released."\n}'),
];

function insertBlocks(folderName, items) {
    const close = folderItemArrayClose(folderName);
    if (close < 0) { console.log('SKIP folder not found: ' + folderName); return; }
    // convert the previous last item's closing '}' to '},' (it now has a follower)
    L[close - 1] = L[close - 1].replace(/}$/, '},');
    // build lines: all new items except the very last one get a trailing comma
    const lines = [];
    items.forEach((it, idx) => lines.push(...itemLines(it, idx < items.length - 1)));
    L.splice(close, 0, ...lines.search(/},$/); // placeholder, replaced below
}

// NOTE: replaced below
