#!/usr/bin/env python3
"""
Standalone Developer CLI Receipt Scanner Test Script.
Tests receipt OCR extraction directly outside Laravel.

Usage:
    python3 scripts/ocr_standalone_test.py /path/to/receipt.jpg
"""

import json
import os
import re
import sys
from PIL import Image

def get_image_info(path):
    if not os.path.exists(path):
        return None
    try:
        with Image.open(path) as img:
            return {"width": img.width, "height": img.height, "format": img.format}
    except Exception as e:
        return {"error": str(e)}

def parse_receipt_fields(text):
    compact = re.sub(r"\s+", " ", text)
    lines = [line.strip() for line in text.splitlines() if line.strip()]

    # Reference No. Aliases
    reference_patterns = [
        r"(?:reference|ref|txn|transaction|mtcn|control|tracking|confirmation|receipt|transfer)\s*(?:no\.?|number|id|#)?\s*[:#-]?\s*([A-Za-z0-9\s-]{6,40})",
        r"\b(?:Ref|Txn|MTCN|Control)\b\s*[:#-]?\s*([A-Za-z0-9-]{6,35})",
    ]
    ref_match = None
    for pat in reference_patterns:
        m = re.search(pat, compact, re.I)
        if m:
            candidate = re.sub(r"[\s-]+", "", m.group(1).strip())
            if len(candidate) >= 6 and re.search(r"\d", candidate):
                ref_match = candidate.upper()
                break

    if not ref_match:
        # Fallback regex for standalone 10-24 digit transaction numbers
        digits = re.findall(r"\b\d{10,24}\b", compact)
        filtered_digits = [d for d in digits if not d.startswith("09")]
        if filtered_digits:
            ref_match = filtered_digits[0]

    # Amount Aliases
    amount_match = None
    amt_m = re.search(r"(?:amount|total|sent|paid|principal|received)\s*[:#-]?\s*(?:PHP|₱|\$)?\s*([\d,]+\.\d{2})", compact, re.I)
    if amt_m:
        try:
            amount_match = float(amt_m.group(1).replace(",", ""))
        except ValueError:
            pass
    if amount_match is None:
        amt_fallback = re.search(r"(?:PHP|₱|\$)\s*([\d,]+\.\d{2})", compact, re.I)
        if amt_fallback:
            try:
                amount_match = float(amt_fallback.group(1).replace(",", ""))
            except ValueError:
                pass

    # Date Aliases
    date_match = None
    dt_m = re.search(r"\b((?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)[a-z]*\.?\s+\d{1,2},?\s+20\d{2}(?:\s+\d{1,2}:\d{2}(?:\s*[AP]M)?)?)\b", compact, re.I)
    if dt_m:
        date_match = dt_m.group(1).strip()
    else:
        dt_num = re.search(r"\b(\d{1,2}[/-]\d{1,2}[/-]20\d{2}(?:\s+\d{1,2}:\d{2}(?:\s*[AP]M)?)?)\b", compact)
        if dt_num:
            date_match = dt_num.group(1).strip()

    # Provider Mode
    mode = "Other / Unknown"
    if re.search(r"\bgcash\b", compact, re.I):
        mode = "GCash"
    elif re.search(r"\b(?:maya|paymaya)\b", compact, re.I):
        mode = "Maya"
    elif re.search(r"\bbdo\b", compact, re.I):
        mode = "BDO"
    elif re.search(r"\b(?:western union|moneygram|cebuana|palawan|remittance)\b", compact, re.I):
        mode = "Remittance"

    return {
        "provider": mode,
        "reference_number": ref_match,
        "transaction_date": date_match,
        "amount": amount_match,
    }

def main():
    if len(sys.argv) < 2:
        print("Usage: python3 scripts/ocr_standalone_test.py /path/to/receipt.jpg")
        sys.exit(1)

    image_path = sys.argv[1]
    info = get_image_info(image_path)
    if not info:
        print(f"Error: File not found or unreadable image at {image_path}")
        sys.exit(1)

    print("=" * 60)
    print(f"AMIS AI RECEIPT SCANNER STANDALONE CLI TEST")
    print("=" * 60)
    print(f"Input Image : {image_path}")
    print(f"Dimensions  : {info.get('width', 0)} x {info.get('height', 0)} ({info.get('format', 'UNKNOWN')})")
    print("-" * 60)

    # Attempt Engine 1: PaddleOCR
    engine_used = "Unavailable"
    raw_text = ""
    confidence = 0.0

    try:
        from paddleocr import PaddleOCR
        print("Running PaddleOCR PP-OCRv6...")
        engine = PaddleOCR(lang="en", use_doc_orientation_classify=False)
        lines = []
        scores = []
        for result in engine.predict(image_path):
            payload = result.json if not callable(getattr(result, "json", None)) else result.json()
            if isinstance(payload, str):
                payload = json.loads(payload)
            data = payload.get("res", payload)
            lines.extend(data.get("rec_texts", []))
            scores.extend(data.get("rec_scores", []))
        raw_text = "\n".join(lines)
        confidence = (sum(scores) / len(scores)) if scores else 0.0
        engine_used = "PaddleOCR PP-OCRv6"
    except Exception as e1:
        print(f"PaddleOCR notice: {e1}")
        # Fallback Engine 2: EasyOCR
        try:
            import easyocr
            print("Fallback to EasyOCR...")
            reader = easyocr.Reader(['en'], gpu=False)
            results = reader.readtext(image_path)
            lines = [res[1] for res in results]
            scores = [float(res[2]) for res in results]
            raw_text = "\n".join(lines)
            confidence = (sum(scores) / len(scores)) if scores else 0.0
            engine_used = "EasyOCR Engine"
        except Exception as e2:
            print(f"EasyOCR notice: {e2}")

    fields = parse_receipt_fields(raw_text)

    print(f"OCR Engine  : {engine_used}")
    print(f"Confidence  : {round(confidence * 100, 1)}%" if confidence else "Confidence  : N/A")
    print(f"Raw Text    : {raw_text if raw_text else 'No text detected'}")
    print("-" * 60)
    print("STANDARDIZED EXTRACTED FIELDS:")
    print(f"  Payment Provider / Mode    : {fields['provider']}")
    print(f"  Transaction / Reference No. : {fields['reference_number'] or 'Not detected'}")
    print(f"  Date & Time                : {fields['transaction_date'] or 'Not detected'}")
    print(f"  Amount                     : {fields['amount'] or 'Not detected'}")
    print("=" * 60)

if __name__ == "__main__":
    main()
