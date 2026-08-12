#!/usr/bin/env python3
"""
Multi-engine OCR JSON bridge script for AMIS AI Receipt Scanner.
Supports:
1. Primary: PaddleOCR PP-OCRv6
2. Fallback 1: docTR OCR
3. Fallback 2: EasyOCR
4. Fallback 3: PyTesseract OCR
"""

import json
import os
import re
import sys
from PIL import Image

def get_image_dimensions(image_path):
    try:
        with Image.open(image_path) as img:
            return f"{img.width}x{img.height}"
    except Exception:
        return "Unknown"

def extract_lines(image_path):
    lines = []
    scores = []
    engine_name = "None"

    # Attempt 1: PaddleOCR PP-OCRv6
    try:
        from paddleocr import PaddleOCR
        ocr = PaddleOCR(lang="en", use_doc_orientation_classify=False, use_doc_unwarping=False)
        for result in ocr.predict(image_path):
            payload = result.json if not callable(getattr(result, "json", None)) else result.json()
            if isinstance(payload, str):
                payload = json.loads(payload)
            data = payload.get("res", payload)
            lines.extend(data.get("rec_texts", []))
            scores.extend(data.get("rec_scores", []))
        if lines:
            return "\n".join(lines), scores, "PaddleOCR PP-OCRv6"
    except Exception:
        pass

    # Attempt 2: docTR OCR
    try:
        from doctr.io import DocumentFile
        from doctr.models import ocr_predictor
        model = ocr_predictor(pretrained=True)
        doc = DocumentFile.from_images(image_path)
        result = model(doc)
        for page in result.pages:
            for block in page.blocks:
                for line in block.lines:
                    text = " ".join([word.value for word in line.words])
                    avg_conf = sum([word.confidence for word in line.words]) / len(line.words) if line.words else 0
                    lines.append(text)
                    scores.append(avg_conf)
        if lines:
            return "\n".join(lines), scores, "docTR OCR"
    except Exception:
        pass

    # Attempt 3: EasyOCR
    try:
        import easyocr
        reader = easyocr.Reader(['en'], gpu=False)
        results = reader.readtext(image_path)
        for res in results:
            lines.append(res[1])
            scores.append(float(res[2]))
        if lines:
            return "\n".join(lines), scores, "EasyOCR"
    except Exception:
        pass

    # Attempt 4: PyTesseract
    try:
        import pytesseract
        text = pytesseract.image_to_string(Image.open(image_path))
        lines = [l.strip() for l in text.splitlines() if l.strip()]
        scores = [0.85] * len(lines)
        if lines:
            return "\n".join(lines), scores, "PyTesseract OCR"
    except Exception:
        pass

    return "\n".join(lines), scores, engine_name

def first_match(patterns, text):
    for pattern in patterns:
        match = re.search(pattern, text, re.I)
        if match:
            return match.group(1).strip()
    return None

def identifier_from_value(value):
    source = str(value or "").upper().strip()
    tokens = re.findall(r"\b[A-Z0-9][A-Z0-9-]{5,39}\b", source)
    for token in tokens:
        if re.search(r"[A-Z]", token) and re.search(r"\d", token):
            return token
    spaced_digits = re.search(r"\b(?:\d[\s-]?){8,24}\b", source)
    if spaced_digits:
        return re.sub(r"[\s-]+", "", spaced_digits.group(0))
    return next((token for token in tokens if re.search(r"\d", token)), None)

def labeled_identifier(lines, labels):
    pattern = re.compile(rf"(?:{labels})\s*(?:no\.?|number|id|code|#)?\s*[:#-]?\s*(.*)$", re.I)
    for index, line in enumerate(lines):
        match = pattern.search(line)
        if not match:
            continue
        same_line = identifier_from_value(match.group(1))
        if same_line:
            return same_line
        if index + 1 < len(lines):
            next_line = identifier_from_value(lines[index + 1])
            if next_line:
                return next_line
    return None

def transaction_reference(text):
    lines = [line.strip() for line in text.splitlines() if line.strip()]
    reference = labeled_identifier(lines, r"reference|ref(?:erence)?")
    if reference:
        return reference
    transaction = labeled_identifier(lines, r"transaction|txn|trace|confirmation|receipt|mtcn|tracking|control")
    if transaction:
        return transaction
    compact = re.sub(r"(?<=\d)[\s-](?=\d)", "", text)
    fallback = sorted(
        [item for item in re.findall(r"\b\d{10,24}\b", compact) if not re.fullmatch(r"09\d{9}", item)],
        key=len,
        reverse=True,
    )
    return fallback[0] if fallback else None

def main():
    if len(sys.argv) < 2:
        print(json.dumps({"error": "No image path provided"}))
        sys.exit(1)

    image_path = sys.argv[1]
    dimensions = get_image_dimensions(image_path)
    text, scores, engine_name = extract_lines(image_path)

    compact = re.sub(r"\s+", " ", text)
    reference = transaction_reference(text)
    amount_text = first_match([
        r"(?:amount in destination currency|destination amount|receive amount|total amount|amount sent|you sent|transfer amount|amount paid|principal amount)\s*[:#-]?\s*(?:SAR|PHP|USD|QAR|AED|KWD|BHD|OMR|Php|₱|\$)?\s*([\d,]+(?:\.\d{2})?)",
        r"(?:SAR|PHP|USD|QAR|AED|KWD|BHD|OMR|Php|₱|\$)\s*([\d,]+(?:\.\d{2})?)",
        r"\b([\d,]+\.\d{2})\b",
    ], compact)
    date = first_match([
        r"\b((?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)[a-z]*\.?\s+\d{1,2},?\s+20\d{2}(?:\s+\d{1,2}:\d{2}(?::\d{2})?(?:\s*[AP]M)?)?)\b",
        r"\b(\d{1,2}\s+(?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)[a-z]*\.?\s+20\d{2}(?:\s+\d{1,2}:\d{2}(?::\d{2})?(?:\s*[AP]M)?)?)\b",
        r"\b(\d{1,2}[/-]\d{1,2}[/-]20\d{2}(?:\s+\d{1,2}:\d{2}(?::\d{2})?(?:\s*[AP]M)?)?)\b",
        r"\b(20\d{2}[/-]\d{1,2}[/-]\d{1,2}(?:\s+\d{1,2}:\d{2}(?::\d{2})?)?)\b",
    ], compact)

    mode = None
    for value, pattern in [
        ("ANB / TeleMoney Transfer", r"\b(?:anb|telemoney)\b"),
        ("D360", r"\bd360\b"),
        ("GCash", r"\bgcash\b"),
        ("Maya", r"\b(?:maya|paymaya)\b"),
        ("BDO", r"\b(?:bdo|banco de oro)\b"),
        ("BPI", r"\b(?:bpi|bank of the philippine islands)\b"),
        ("Metrobank", r"\bmetrobank\b"),
        ("Western Union", r"\bwestern union\b"),
        ("MoneyGram", r"\bmoneygram\b"),
        ("Cebuana Lhuillier", r"\bcebuana\b"),
        ("PalawanPay", r"\bpalawan\b"),
        ("remittance", r"\b(?:remittance)\b"),
    ]:
        if re.search(pattern, compact, re.I):
            mode = value
            break

    print(json.dumps({
        "raw_text": text,
        "engine": engine_name,
        "image_dimensions": dimensions,
        "text_regions_count": len(scores),
        "confidence": (sum(scores) / len(scores)) if scores else None,
        "detected_ref": reference.upper() if reference else None,
        "detected_amount": float(amount_text.replace(",", "")) if amount_text else None,
        "detected_datetime": date,
        "detected_method": mode,
        "detected_sender": None,
        "detected_receiver": None,
        "detected_merchant": None,
        "detected_account": None,
        "has_qr": False,
    }))

if __name__ == "__main__":
    main()
