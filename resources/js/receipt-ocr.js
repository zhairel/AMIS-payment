const monthNumbers = {
    jan: 1, january: 1, feb: 2, february: 2, mar: 3, march: 3,
    apr: 4, april: 4, may: 5, jun: 6, june: 6, jul: 7, july: 7,
    aug: 8, august: 8, sep: 9, sept: 9, september: 9, oct: 10,
    october: 10, nov: 11, november: 11, dec: 12, december: 12,
};

const pad = value => String(value).padStart(2, '0');

function normalizeDate(text) {
    const named = text.match(/\b(jan(?:uary)?|feb(?:ruary)?|mar(?:ch)?|apr(?:il)?|may|jun(?:e)?|jul(?:y)?|aug(?:ust)?|sep(?:t(?:ember)?)?|oct(?:ober)?|nov(?:ember)?|dec(?:ember)?)\.?\s+(\d{1,2}),?\s+(20\d{2})\b/i);
    if (named) return `${named[3]}-${pad(monthNumbers[named[1].toLowerCase().replace('.', '')])}-${pad(named[2])}`;

    const iso = text.match(/\b(20\d{2})[\/-](\d{1,2})[\/-](\d{1,2})\b/);
    if (iso) return `${iso[1]}-${pad(iso[2])}-${pad(iso[3])}`;

    // The payment form presents MM/DD/YYYY. When one part is greater than 12,
    // use it as the day; otherwise keep the visible MM/DD order.
    const numeric = text.match(/\b(\d{1,2})[\/-](\d{1,2})[\/-](20\d{2})\b/);
    if (numeric) {
        const first = Number(numeric[1]);
        const second = Number(numeric[2]);
        const day = first > 12 ? first : second;
        const month = first > 12 ? second : first;
        if (month >= 1 && month <= 12 && day >= 1 && day <= 31) return `${numeric[3]}-${pad(month)}-${pad(day)}`;
    }

    return null;
}

function normalizeTime(text) {
    const match = text.match(/\b(\d{1,2}):(\d{2})(?::\d{2})?\s*([AP]M)?\b/i);
    if (!match) return null;
    let hour = Number(match[1]);
    const minute = Number(match[2]);
    const period = match[3]?.toUpperCase();
    if (hour > 23 || minute > 59 || (period && (hour < 1 || hour > 12))) return null;
    if (period === 'AM' && hour === 12) hour = 0;
    if (period === 'PM' && hour !== 12) hour += 12;
    return `${pad(hour)}:${pad(minute)}`;
}

function detectMode(text) {
    if (/\b(?:moneygram|western union|palawan|cebuana|mlhuillier|m lhuillier|remittance)\b/i.test(text)) return 'remittance';
    if (/\b(?:instapay|pesonet|bank transfer|online transfer)\b/i.test(text) && !/\bbdo\b/i.test(text)) return 'bank_transfer';
    if (/\bbdo\b/i.test(text) && /\b(?:deposit slip|cash deposit|over[ -]?the[ -]?counter|branch deposit)\b/i.test(text)) return 'bdo_otc';
    if (/\bbdo\b/i.test(text)) return 'bdo_online';
    if (/\b(?:maya|paymaya)\b/i.test(text)) return 'maya';
    if (/\bgcash\b/i.test(text)) return 'gcash';
    return null;
}

function identifierFromValue(value) {
    const source = String(value || '').toUpperCase().trim();
    const tokens = source.match(/\b[A-Z0-9][A-Z0-9-]{5,39}\b/g) || [];
    const alphanumeric = tokens.find(token => /[A-Z]/.test(token) && /\d/.test(token));
    if (alphanumeric) return alphanumeric;

    const spacedDigits = source.match(/\b(?:\d[\s-]?){8,24}\b/)?.[0];
    if (spacedDigits) return spacedDigits.replace(/[\s-]+/g, '');

    return tokens.find(token => /\d/.test(token)) || null;
}

function detectLabeledIdentifier(lines, labels) {
    const pattern = new RegExp(`(?:${labels})\\s*(?:no\\.?|number|id|code|#)?\\s*[:#-]?\\s*(.*)$`, 'i');
    for (let index = 0; index < lines.length; index += 1) {
        const match = lines[index].match(pattern);
        if (!match) continue;
        const sameLine = identifierFromValue(match[1]);
        if (sameLine) return sameLine;
        const nextLine = identifierFromValue(lines[index + 1]);
        if (nextLine) return nextLine;
    }
    return null;
}

function detectReference(text) {
    const lines = String(text || '').split(/\r?\n/).map(line => line.trim()).filter(Boolean);

    // Prefer the explicit receipt reference. If it is absent, use the
    // transaction/trace/confirmation identifier in the same form field.
    const reference = detectLabeledIdentifier(lines, 'reference|ref(?:erence)?');
    if (reference) return reference;
    const transaction = detectLabeledIdentifier(lines, 'transaction|txn|trace|confirmation|receipt|mtcn|tracking|control');
    if (transaction) return transaction;

    const normalized = String(text || '').replace(/(?<=\d)[\s-](?=\d)/g, '');
    return (normalized.match(/\b\d{12,24}\b/g) || [])
        .filter(candidate => !/^09\d{9}$/.test(candidate))
        .sort((left, right) => right.length - left.length)[0] || null;
}

function detectAmount(text, expectedAmount) {
    const expectedValue = Number(expectedAmount);
    const allCandidates = [...text.matchAll(/(?:₱|php)?\s*([\d,]+\.\d{2})\b/giu)]
        .map(match => Number(match[1].replaceAll(',', '')))
        .filter(value => Number.isFinite(value) && value > 0 && value < 10000000);
    const expected = Number.isFinite(expectedValue)
        ? allCandidates.find(value => Math.abs(value - expectedValue) < 1)
        : null;
    if (expected !== null && expected !== undefined) return expected;

    const labeled = text.match(/(?:total\s*amount|amount\s*(?:sent|paid|transferred?)?|you\s*sent)\s*[:#-]?\s*(?:₱|php)?\s*([\d,]+(?:\.\d{2})?)/iu);
    if (labeled) return Number(labeled[1].replaceAll(',', ''));

    const candidates = [...text.matchAll(/(?:₱|php)\s*([\d,]+(?:\.\d{2})?)/giu)]
        .map(match => Number(match[1].replaceAll(',', '')))
        .filter(Number.isFinite);
    return candidates[0] ?? allCandidates[0] ?? null;
}

function detectPerson(text, labels) {
    const expression = new RegExp(`(?:${labels})\\s*[:#-]?\\s*([A-Z][A-Z .,'-]{2,50})`, 'i');
    return text.match(expression)?.[1]?.trim() || null;
}

function classifyDocument(text, fields) {
    const lower = text.toLowerCase();
    const nonReceiptTerms = ['statement of account', 'statement account', 'schedule of fees', 'tuition fees', 'required payment monthly', 'due monthly payment', 'monthly payment', 'discount status', 'grade level', 'final fees', 'books lms', 'school year'];
    const nonReceiptHits = nonReceiptTerms.filter(term => lower.includes(term)).length;
    if (lower.includes('statement of account') || lower.includes('schedule of fees') || nonReceiptHits >= 2) {
        return {type: 'not_receipt', message: 'This appears to be an SOA or fee document—not an actual payment receipt.'};
    }

    const receiptTerms = ['successful', 'successfully', 'transaction details', 'reference number', 'transaction number', 'amount sent', 'amount paid', 'paid to', 'sent to', 'payment received', 'transfer complete'];
    const receiptTermHits = receiptTerms.filter(term => lower.includes(term)).length;
    const coreFieldCount = [fields.mode, fields.reference, fields.amount, fields.date].filter(value => value !== null && value !== '').length;
    const score = receiptTermHits * 2
        + (fields.mode ? 2 : 0) + (fields.reference ? 2 : 0) + (fields.amount !== null ? 2 : 0) + (fields.date ? 1 : 0);

    // A readable screenshot with no meaningful payment evidence is not sent
    // to manual payment review. This blocks desktop/settings/social screenshots
    // even if OCR happens to find an unrelated date or number.
    if (cleanTextHasWords(text) && receiptTermHits === 0 && coreFieldCount < 2) {
        return {type: 'not_receipt', message: 'This image does not appear to be a payment receipt. Upload the actual payment confirmation.'};
    }

    return score >= 5
        ? {type: 'receipt', message: 'Payment receipt detected. Please double-check the auto-filled details.'}
        : {type: 'uncertain', message: 'Some details were not detected. Complete the fields and Finance will verify the receipt.'};
}

function cleanTextHasWords(text) {
    return (String(text).match(/[a-z]{3,}/gi) || []).length >= 2;
}

async function prepareImage(file) {
    const imageUrl = URL.createObjectURL(file);

    try {
        const image = await new Promise((resolve, reject) => {
            const element = new Image();
            element.onload = () => resolve(element);
            element.onerror = reject;
            element.src = imageUrl;
        });
        const scale = Math.min(2.4, Math.max(1, 1500 / image.width), 3000 / image.height);
        const canvas = document.createElement('canvas');
        canvas.width = Math.round(image.width * scale);
        canvas.height = Math.round(image.height * scale);
        const context = canvas.getContext('2d', {willReadFrequently: true});
        context.fillStyle = '#ffffff';
        context.fillRect(0, 0, canvas.width, canvas.height);
        context.drawImage(image, 0, 0, canvas.width, canvas.height);

        const imageData = context.getImageData(0, 0, canvas.width, canvas.height);
        const pixels = imageData.data;
        for (let index = 0; index < pixels.length; index += 4) {
            const gray = (0.299 * pixels[index]) + (0.587 * pixels[index + 1]) + (0.114 * pixels[index + 2]);
            const contrasted = Math.max(0, Math.min(255, ((gray - 128) * 1.35) + 128));
            pixels[index] = contrasted;
            pixels[index + 1] = contrasted;
            pixels[index + 2] = contrasted;
        }
        context.putImageData(imageData, 0, 0);

        return await new Promise((resolve, reject) => canvas.toBlob(blob => blob ? resolve(blob) : reject(new Error('Could not prepare receipt image.')), 'image/png', 0.96));
    } finally {
        URL.revokeObjectURL(imageUrl);
    }
}

function parse(text, expectedAmount) {
    const cleanText = String(text || '').replace(/\r/g, '\n').replace(/[ \t]+/g, ' ');
    const fields = {
        reference: detectReference(cleanText),
        amount: detectAmount(cleanText, expectedAmount),
        date: normalizeDate(cleanText),
        time: normalizeTime(cleanText),
        mode: detectMode(cleanText),
        sender: detectPerson(cleanText, 'from|sender|sent by|remitter'),
        receiver: detectPerson(cleanText, 'to|recipient|receiver|beneficiary|paid to'),
        merchant: detectPerson(cleanText, 'merchant|business|store'),
        account: cleanText.match(/(?:account\s*(?:no\.?|#|number)|mobile\s*(?:no\.?|number))\s*[:#-]?\s*([\d +()-]{8,24})/i)?.[1]?.trim() || null,
    };

    return {...fields, ...classifyDocument(cleanText, fields), rawText: cleanText};
}

function isComplete(result, expectedAmount) {
    const amountMatches = result.amount !== null
        && Math.abs(Number(result.amount) - Number(expectedAmount)) < 1;
    return result.type !== 'not_receipt'
        && Boolean(result.reference && result.date && result.mode && amountMatches);
}

function mergeResults(first, second, expectedAmount) {
    const combined = parse(`${first.rawText || ''}\n${second.rawText || ''}`, expectedAmount);
    const preferred = (key) => combined[key] ?? first[key] ?? second[key] ?? null;
    return {
        ...combined,
        reference: preferred('reference'),
        amount: preferred('amount'),
        date: preferred('date'),
        time: preferred('time'),
        mode: preferred('mode'),
        sender: preferred('sender'),
        receiver: preferred('receiver'),
        merchant: preferred('merchant'),
        account: preferred('account'),
        rawText: `${first.rawText || ''}\n${second.rawText || ''}`.trim(),
        engine: 'Tesseract OCR',
        passes: 2,
    };
}

async function recognize(file, expectedAmount, onProgress = () => {}) {
    const {createWorker} = await import('tesseract.js');
    let activePass = 1;
    onProgress({status: 'preparing image', progress: 0.03});
    const preparedImage = await prepareImage(file);
    const worker = await createWorker('eng', 1, {
        logger(message) {
            const progress = Number(message.progress || 0);
            onProgress({status: message.status || 'Reading receipt', progress, pass: activePass});
        },
    });

    try {
        await worker.setParameters({
            tessedit_pageseg_mode: '6',
            preserve_interword_spaces: '1',
        });
        const firstScan = await worker.recognize(preparedImage, {rotateAuto: true});
        const firstResult = {...parse(firstScan.data.text, expectedAmount), confidence: Number(firstScan.data.confidence || 0) / 100, engine: 'Tesseract OCR', passes: 1};
        if (isComplete(firstResult, expectedAmount) || firstResult.type === 'not_receipt') return firstResult;

        activePass = 2;
        onProgress({status: 'switching to alternate Tesseract scan', progress: 0.04, pass: 2});
        await worker.setParameters({
            tessedit_pageseg_mode: '11',
            preserve_interword_spaces: '1',
        });
        const secondScan = await worker.recognize(file, {rotateAuto: true});
        const secondResult = {...parse(secondScan.data.text, expectedAmount), confidence: Number(secondScan.data.confidence || 0) / 100};
        const merged = mergeResults(firstResult, secondResult, expectedAmount);
        merged.confidence = Math.max(firstResult.confidence || 0, secondResult.confidence || 0);
        return merged;
    } finally {
        await worker.terminate();
    }
}

window.AmisReceiptOcr = {recognize, parse, isComplete};
