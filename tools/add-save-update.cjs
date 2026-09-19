const fs = require('fs');
const FILE = 'postman/VATI-Microfinance-API.postman_collection.json';
const L = fs.readFileSync(FILE, 'utf8').split(/\r?\n/);
const bUrl = '{{base_url}}/api/v1';

// Build a request item block at folder base indent (16 spaces for folder items)
function block(name, method, url, raw) {
    const lines = [];
    const push = (indent, s) => lines.push(' '.repeat(indent) + s);
    push(16, '{');
    push(20, '"name": ' + JSON.stringify(name) + ',');
    push(20, '"request": {');
    push(24, '"method": ' + JSON.stringify(method) + ',');
    push(24, '"header": [');
    push(28, '{');
    push(32, '"key": "Content-Type",');
    push(32, '"value": "application/json",');
    push(32, '"type": "text"');
    push(28, '}');
    push(24, '],');
    push(24, '"url": ' + JSON.stringify(url) + ',');
    push(24, '"description": "",');
    push(24, '"body": {');
    push(28, '"mode": "raw",');
    push(28, '"raw": ' + JSON.stringify(raw) + ',');
    push(28, '"options": {');
    push(32, '"raw": {');
    push(36, '"language": "json"');
    push(32, '}');
    push(28, '}');
    push(24, '}');
    push(20, '}');
    push(16, '},');
    return lines;
}

// Find the line index of a folder's "request" (item) by name inside folder item array.
// We locate the folder object start ( { after "name": "..."), then its "item": [ ... ]
function findItemArrayClose(idxNameLine) {
    // Folder structure (4-space): folder "name" at 12, "item": [ at 12, entries at 16.
    // Find this folder's item-array close: the first `            ],` (12 indent) at the same object level,
    // i.e. after the "item": [ at 12 indent. We track nesting to be safe.
    let i = idxNameLine;
    const depth = [];
    let set = 0; // nesting inside this folder obj
    let inArr = false; // encountered "item": [
    while (i < L.length) {
        const line = L[i];
        const t = line.trim();
        const ind = line.length - line.replace(/^\s+/, '').length;
        if (t === '"item": [') inArr = true;
        if (inArr && ind === 12 && t === '],') { inArr = false; return i; }
        i++;
    }
    return -1;
}

// Locate a folder by name: lines with exactly `            "name": "X",` (12 indent) followed by `            "item": [`
function folderStartIdx(name) {
    for (let i = 0; i < L.length; i++) {
        const t = L[i].trim();
        const ind = L[i].length - L[i].replace(/^\s+/, '').length;
        if (ind === 12 && t === '"name": ' + JSON.stringify(name) + ',') {
            // confirm next non empty is "item": [
            for (let k = i + 1; k < L.length && k < i + 6; k++) {
                if (L[k].trim() === '"item": [') return i;
            }
        }
    }
    return -1;
}

// compliance saves for Loan Applications and Witnesses
const waUrl = bUrl + '/loan-applications/{{loan_application_id}}/compliance';
const compliance = [
    { n: 'Update Applicant Compliance', m: 'PUT', u: waUrl + '/applicant',
        r: '{\n  "accept_declaration": true,\n  "applicant_signature": "{{applicant_signature}}",\n  "applicant_thumbnail": "{{applicant_thumbnail}}"\n}' },
    { n: 'Add Compliance Guarantor', m: 'POST', u: waUrl + '/guarantors',
        r: '{\n  "guarantor_type": "family",\n  "name": "Jane Doe",\n  "relationship": "Sister",\n  "phone": "0712345678",\n  "national_id": "{{guarantor_national_id}}",\n  "signature": "{{guarantor_signature}}",\n  "thumbnail": "{{guarantor_thumbnail}}",\n  "accept_declaration": true\n}' },
    { n: 'Update Nominees Compliance', m: 'PUT', u: waUrl + '/nominees',
        r: '{\n  "nominees": [\n    {\n      "name": "Grace Nominee",\n      "relationship": "Daughter",\n      "percentage": 100\n    }\n  ]\n}' },
    { n: 'Cancel Application', m: 'POST', u: bUrl + '/loan-applications/{{loan_application_id}}/cancel',
        r: '{\n  "reason": "Applicant requested withdrawal"\n}' },
];

// loan clearance / notices / passbook for Loans, Disbursement and Collections
const admin = [
    { n: 'Replace Member Passbook', m: 'POST', u: bUrl + '/members/{{member_id}}/passbook-replacements',
        r: '{\n  "reason": "damaged",\n  "payment_reference": "{{payment_reference}}"\n}' },
    { n: 'Issue Default Notice', m: 'POST', u: bUrl + '/loans/{{loan_id}}/default-notices',
        r: '{\n  "delivery_method": "hand",\n  "delivery_reference": "{{delivery_reference}}"\n}' },
    { n: 'Authorize Loan Clearance', m: 'POST', u: bUrl + '/loans/{{loan_id}}/clearance',
        r: '{\n  "comments": "All dues settled, collateral released."\n}' },
];

const waStart = folderStartIdx('Loan Applications and Witnesses');
const adStart = folderStartIdx('Loans, Disbursement and Collections');
const waClose = findItemArrayClose(waStart);
const adClose = findItemArrayClose(adStartese);

let addedWa = 0, addedAd = 0, err = [];
if (waClose < 0) err.push('waClose not found');
if (adClose < 0) err.push('adClose not found');

if (waClose > 0) {
    const ins = compliance.map(c => block(c.n, c.m, c.u, c.r)).flat();
    L.splice(waClose, 0, ...ins);
    addedWa = compliance.length;
}
if (adClose > 0) {
    // recompute: indices shifted by addedWa lines
    const ins = admin.map(c => block(c.n, c.m, c.u, c.r)).flat();
    L.splice(adClose + addedWa * 14, 0, ...ins);
    addedAd = admin.length;
}

const out = L.join('\n');
try {
    JSON.parse(out);
    fs.writeFileSync(FILE, out);
    console.log('OK wa=' + waStart + '->' + waClose + ' ad=' + adStart + '->' + adClose +
        ' addedCompliance=' + addedWa + ' addedAdmin=' + addedAd + (err.length ? ' ERR:' + err.join(',') : ''));
} catch (e) {
    console.log('INVALID after splice: ' + e.message);
    fs.writeFileSync(FILE + '.pre', out);
}
