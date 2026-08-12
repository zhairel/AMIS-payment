#!/usr/bin/env python3
"""
AMIS AI Receipt Scanner - Independent 4-Engine OCR Bridge Script.
Allows running EasyOCR, PaddleOCR PP-OCRv6, docTR, and Tesseract INDEPENDENTLY
without standard fallback chaining.
"""

import json
import os
import sys
import time
import warnings
from shutil import which
from PIL import Image

warnings.filterwarnings("ignore")

def check_env():
    executable = sys.executable
    version = f"Python {sys.version.split()[0]}"

    # 1. EasyOCR Check
    try:
        import easyocr
        easy_ver = getattr(easyocr, "__version__", "installed")
        easy_avail = True
        easy_reason = None
    except Exception as e:
        easy_avail = False
        easy_ver = None
        easy_reason = f"{type(e).__name__}: {str(e)}"

    # 2. PaddleOCR Check
    try:
        import paddleocr
        paddle_ver = getattr(paddleocr, "__version__", "installed")
        paddle_avail = True
        paddle_reason = None
    except Exception as e:
        paddle_avail = False
        paddle_ver = None
        paddle_reason = f"ModuleNotFoundError: paddleocr package is not installed in the Python environment used by Laravel ({executable}). Details: {str(e)}"

    # 3. docTR Check
    try:
        import doctr
        doctr_ver = getattr(doctr, "__version__", "installed")
        doctr_avail = True
        doctr_reason = None
    except Exception as e:
        doctr_avail = False
        doctr_ver = None
        doctr_reason = f"python-doctr package is not installed in the Python environment used by Laravel ({executable}). Details: {str(e)}"

    # 4. Tesseract Check
    tes_bin = which("tesseract")
    if tes_bin:
        tes_avail = True
        tes_ver = f"tesseract binary at {tes_bin}"
        tes_reason = None
    else:
        tes_avail = False
        tes_ver = None
        tes_reason = f"TesseractNotFoundError: tesseract binary is not installed in system PATH for environment ({executable})"

    print(json.dumps({
        "python_executable": executable,
        "python_version": version,
        "engines": {
            "easyocr": {"available": easy_avail, "version": easy_ver, "reason": easy_reason},
            "paddleocr": {"available": paddle_avail, "version": paddle_ver, "reason": paddle_reason},
            "doctr": {"available": doctr_avail, "version": doctr_ver, "reason": doctr_reason},
            "tesseract": {"available": tes_avail, "version": tes_ver, "reason": tes_reason},
        }
    }))

def run_easyocr(image_path):
    start = time.time()
    try:
        import easyocr
        reader = easyocr.Reader(['en'], gpu=False)
        results = reader.readtext(image_path)
        lines = []
        confs = []
        for res in results:
            lines.append(res[1])
            confs.append(float(res[2]))
        duration = int((time.time() - start) * 1000)
        return {
            "engine": "EasyOCR",
            "status": "SUCCESS",
            "raw_text": "\n".join(lines),
            "regions": len(lines),
            "confidence": round(sum(confs) / len(confs), 3) if confs else None,
            "duration_ms": duration,
            "error": None
        }
    except Exception as e:
        duration = int((time.time() - start) * 1000)
        return {
            "engine": "EasyOCR",
            "status": "FAILED",
            "raw_text": "",
            "regions": 0,
            "confidence": None,
            "duration_ms": duration,
            "error": f"{type(e).__name__}: {str(e)}"
        }

def run_paddleocr(image_path):
    start = time.time()
    try:
        from paddleocr import PaddleOCR
        ocr = PaddleOCR(lang="en", use_doc_orientation_classify=False, use_doc_unwarping=False)
        lines = []
        scores = []
        for result in ocr.predict(image_path):
            payload = result.json if not callable(getattr(result, "json", None)) else result.json()
            if isinstance(payload, str):
                payload = json.loads(payload)
            data = payload.get("res", payload)
            lines.extend(data.get("rec_texts", []))
            scores.extend(data.get("rec_scores", []))
        duration = int((time.time() - start) * 1000)
        return {
            "engine": "PaddleOCR PP-OCRv6",
            "status": "SUCCESS",
            "raw_text": "\n".join(lines),
            "regions": len(lines),
            "confidence": round(sum(scores) / len(scores), 3) if scores else None,
            "duration_ms": duration,
            "error": None
        }
    except ModuleNotFoundError as e:
        duration = int((time.time() - start) * 1000)
        return {
            "engine": "PaddleOCR PP-OCRv6",
            "status": "NOT_AVAILABLE",
            "raw_text": "",
            "regions": 0,
            "confidence": None,
            "duration_ms": duration,
            "error": f"ModuleNotFoundError: paddleocr package is not installed in Python environment ({sys.executable})."
        }
    except Exception as e:
        duration = int((time.time() - start) * 1000)
        return {
            "engine": "PaddleOCR PP-OCRv6",
            "status": "FAILED",
            "raw_text": "",
            "regions": 0,
            "confidence": None,
            "duration_ms": duration,
            "error": f"{type(e).__name__}: {str(e)}"
        }

def run_doctr(image_path):
    start = time.time()
    try:
        from doctr.io import DocumentFile
        from doctr.models import ocr_predictor
        model = ocr_predictor(pretrained=True)
        doc = DocumentFile.from_images(image_path)
        result = model(doc)
        lines = []
        scores = []
        for page in result.pages:
            for block in page.blocks:
                for line in block.lines:
                    text = " ".join([word.value for word in line.words])
                    avg_conf = sum([word.confidence for word in line.words]) / len(line.words) if line.words else 0
                    lines.append(text)
                    scores.append(avg_conf)
        duration = int((time.time() - start) * 1000)
        return {
            "engine": "docTR",
            "status": "SUCCESS",
            "raw_text": "\n".join(lines),
            "regions": len(lines),
            "confidence": round(sum(scores) / len(scores), 3) if scores else None,
            "duration_ms": duration,
            "error": None
        }
    except ModuleNotFoundError as e:
        duration = int((time.time() - start) * 1000)
        return {
            "engine": "docTR",
            "status": "NOT_AVAILABLE",
            "raw_text": "",
            "regions": 0,
            "confidence": None,
            "duration_ms": duration,
            "error": f"python-doctr package is not installed in the Python environment used by Laravel ({sys.executable})."
        }
    except Exception as e:
        duration = int((time.time() - start) * 1000)
        return {
            "engine": "docTR",
            "status": "FAILED",
            "raw_text": "",
            "regions": 0,
            "confidence": None,
            "duration_ms": duration,
            "error": f"{type(e).__name__}: {str(e)}"
        }

def run_tesseract(image_path):
    start = time.time()
    if not which("tesseract"):
        duration = int((time.time() - start) * 1000)
        return {
            "engine": "Tesseract",
            "status": "NOT_AVAILABLE",
            "raw_text": "",
            "regions": 0,
            "confidence": None,
            "duration_ms": duration,
            "error": f"TesseractNotFoundError: tesseract binary is not installed in system PATH for environment ({sys.executable})."
        }
    try:
        import pytesseract
        text = pytesseract.image_to_string(Image.open(image_path))
        lines = [l.strip() for l in text.splitlines() if l.strip()]
        duration = int((time.time() - start) * 1000)
        return {
            "engine": "Tesseract",
            "status": "SUCCESS",
            "raw_text": "\n".join(lines),
            "regions": len(lines),
            "confidence": 0.85 if lines else None,
            "duration_ms": duration,
            "error": None
        }
    except Exception as e:
        duration = int((time.time() - start) * 1000)
        return {
            "engine": "Tesseract",
            "status": "FAILED",
            "raw_text": "",
            "regions": 0,
            "confidence": None,
            "duration_ms": duration,
            "error": f"{type(e).__name__}: {str(e)}"
        }

def main():
    if len(sys.argv) < 2:
        print(json.dumps({"error": "No command provided"}))
        sys.exit(1)

    cmd = sys.argv[1].lower()

    if cmd == "check_env":
        check_env()
        return

    if len(sys.argv) < 3:
        print(json.dumps({"error": "No image path provided for engine execution"}))
        sys.exit(1)

    image_path = sys.argv[2]

    if not os.path.isfile(image_path):
        print(json.dumps({
            "engine": cmd,
            "status": "FAILED",
            "raw_text": "",
            "regions": 0,
            "confidence": None,
            "duration_ms": 0,
            "error": f"FileNotFoundError: Test receipt image file not found at path {image_path}"
        }))
        return

    if cmd == "easyocr":
        print(json.dumps(run_easyocr(image_path)))
    elif cmd in ["paddleocr", "paddle"]:
        print(json.dumps(run_paddleocr(image_path)))
    elif cmd == "doctr":
        print(json.dumps(run_doctr(image_path)))
    elif cmd == "tesseract":
        print(json.dumps(run_tesseract(image_path)))
    else:
        print(json.dumps({"error": f"Unknown OCR engine command '{cmd}'"}))

if __name__ == "__main__":
    main()
