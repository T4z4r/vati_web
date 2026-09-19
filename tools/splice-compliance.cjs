const fs = require('fs');
const FILE = 'postman/VATI-Microfinance-API.postman_collection.json';
const B = '{{base_url}}/api/v1';
const j = JSON.parse(fs.readFileSync(FILE, 'utf8'));

function mk(reqName, method, url, raw) {
    return {
        name: reqName,
        request: {
            method,
            header: [{ key: 'Content-Type', value: 'application/json' }],
            body: { mode: 'raw', raw, options: { raw: { language: 'json' } }, rawOptions: { raw: { language: 'json' } } },
            url,
            description: '',
        },
        response: [],
    };
}

function block(item, indent) {
    const pad = ' '.repeat(indent);
    const obj = JSON.stringify({ name: item.name, request: item.request, response: [] }, null, 4)
        .split('\n')
        .map(l => (l ? pad + l : l))
        .join('\n');
    return obj;
}

// locate folders and splice textually on the raw lines
const L = fs.readFileSync(FILE, 'utf8').split(/\r?\n/);

// anchor: folder "Loan Applications and Witnesses" last request "Reject Application"
const loader = JSON.parse(fs.readFileSync(FILE, 'utf8'));
function findFolder(items, name) {
    for (const n of items) if (n.name === name && Array.isArray(n.item)) return n;
    return null;
}
const waF = findFolder(loader.item, 'Loan Applications and Witnesses');
const adF = findFolder(loader.item, 'Loans, Disbursement and Collections');

const itemsStr = arr => arr.map(it => block(it, 16)).join(',\n');

if (waF) {
    const m = regexQuote('Reject Application');
    // find last index of the "Reject Application" + its closing in L
    const startIdx = L.findIndex(l => l.includes('"name": "Reject Application"'));
    // find the item array close line after it (  "            ],"  )
    const closeIdx = L.findIndex((l, i) => i > startIdx && l.trim() === '],');
    const inserted = compliance.map(it => block(it, 16)).join(',\n') + '\n';
    L.splice(closeIdx, 0, inserted);
    console.log('wa spliced at closeIdx=' + closeIdx);
}

if (adF) {
    const startIdx = L.findIndex(l => l.includes('"name": "Reverse Payment"'));
    const closeIdx = L.findIndex((l, i) => i > startIdx && l.trim() === '],');
    const inserted = adminItems.map(it => block(it, 16)).join(',\n') + '\n';
    L.splice(closeIdx, 0, inserted);
    console.log('admin spliced at closeIdx=' + closeIdx);
}

fs.writeFileSync(FILE, L.join('\n'));
console.log('wa=' + !!waF + ' admin=' + !!adF);
