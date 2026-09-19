const fs = require('fs');
const FILE = 'postman/VATI-Microfinance-API.postman_collection.json';
const L = fs.readFileSync(FILE, 'utf8').split(/\r?\n/);
const B = '{{base_url}}/api/v1';

// ---- tiny Postman request item builder (16-space base, matching folder items) ----
function saveItem(name, method, url, desc, rawBodyJson) {
    const p = '                ';   // 16
    const l = [];
    l.push(p + '{');
    l.push(p + '    "name": ' + JSON.stringify(name) + ',');
    l.push(p + '    "request": {');
    l.push(p + '        "method": ' + JSON.stringify(method) + ',');
    l.push(p + '        "header": [');
    l.push(p + '            {');
    l.push(p + '                "key": "Content-Type",');
    l.push(p + '                "value": "application/json",');
    l.push(p + '                "type": "text"');
    l.push(p + '            }');
    l.push(p + '        ],');
    l.push(p + '        "url": ' + JSON.stringify(url) + ',');
    l.push(p + '        "description": ' + JSON.stringify(desc) + ',');
    l.push(p + '        "body": {');
    l.push(p + '            "mode": "raw",');
    l.push(p + '            "raw": ' + JSON.stringify(rawBodyJson) + ',');
    l.push(p + '            "options": {');
    l.push(p + '                "raw": {');
    l.push(p + '                    "language": "json"');
    l.push(p + '                }');
    l.push(p + '            }');
    l.push(p + '        }');
    l.push(p + '    },');
    l.push(p + '    "response": []');
    l.push(p + '}');
    return l;
}

// folders' item-array close anchors (verified earlier)
//   Loan Applications and Witnesses  -> close at L1586 (line index 1585)
//   Loans, Disbursement and Collections -> close at L1737 (line index 1736)
const waClose = 1585;   // 1-based line 1586 == index 1585
const adClose = 1736;   // 1-based line 1737 == index 1736

const appId = '{{loan_application_id}}';

// compliance save/update requests
const waBlocks = []
    .concat(saveItem('Save Applicant Compliance', 'PUT', B + '/loan-applications/' + appId + '/compliance/applicant',
        'Saves the applicant declaration acceptance, signature and thumbprint.',
        '{\n  "accept_declaration": true,\n  "applicant_signature": "{{applicant_signature}}",\n  "applicant_thumbprint": "{{applicant_thumbprint}}"\n}'))
    .concat(saveItem('Save Guarantor Compliance', 'POST', B + '/loan-applications/' + appId + '/compliance/guarantors',
        'Saves a guarantor with signature and thumbprint.',
        '{\n  "guarantor_type": "family",\n  "name": "Jane Doe",\n  "relationship": "Sister",\n  "phone": "0712345678",\n  "national_id": "{{guarantor_national_id}}",\n  "signature": "{{guarantor_signature}}",\n  "thumbprint": "{{guarantor_thumbprint}}",\n  "accept_declaration": true\n}'));

// Reject Application block ends at L1585 (index 1584) with '}' and L1586 (1585) is '],'.
// Insert new blocks in place of the '}', turning Reject Application into non-last (add comma).
L[waClose - 1] = L[waClose - 1].replace(/}\s*$/, '},');  // give Reject Application a trailing comma
// also the last appended block must NOT have a trailing comma (it becomes last) => handled in splice
L.splice(waClose, 0, ...waBlocks.slice(0, waBlocks.length - 1));
L.splice(waClose + waBlocks.length - 1, 0, ...waBlocks[waBlocks.length - 1]);
// remove the trailing comma from the final inserted block
const finalIdx = waClose + waBlocks.length - 1;
L[finalIdx - 1] = L[finalIdx - 1]; // noop safeguard
// Actually simplest: build two groups then handle commas by assembling text once.
