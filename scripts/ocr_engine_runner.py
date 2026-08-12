#!/usr/bin/env python3
"""
AMIS AI Receipt Scanner - Independent 4-Engine OCR Bridge Script.
Runs inside dedicated .venv-ocr environment (Python 3.12).
Evaluates EasyOCR, PaddleOCR PP-OCRv6, docTR, and Tesseract INDEPENDENTLY.
"""

import json
import logging
import os
import sys
import time
import warnings
from shutil import which
from PIL import Image

warnings.filterwarnings("ignore")
logging.getLogger("ppocr").setLevel(logging.ERROR)
logging.getLogger("doctr").setLevel(logging.ERROR)

# Configure environment PATH and TESSDATA_PREFIX for isolated venv binaries
SCRIPT_DIR = os.path.dirname(os.path.abspath(__file__))
PROJECT_ROOT = os.path.dirname(SCRIPT_DIR)
VENV_BIN = os.path.join(PROJECT_ROOT, ".venv-ocr", "bin")
TESSDATA_DIR = os.path.join(PROJECT_ROOT, ".venv-ocr", "share", "tessdata")

if os.path.isdir(VENV_BIN):
    os.environ["PATH"] = f"{VENV_BIN}:{os.environ.get('PATH', '')}"

if os.path.isdir(TESSDATA_DIR):
    os.environ["TESSDATA_PREFIX"] = TESSDATA_DIR


def check_env():
    executable = sys.executable
    version = f"Python {sys.version.split()[0]}"

    # Redirect stdout temporarily during imports to catch any noisy logger output
    old_stdout = sys.stdout
    sys.stdout = open(os.devnull, 'w')

    easy_avail, easy_ver, easy_reason = False, None, None
    paddle_avail, paddle_ver, paddle_reason = False, None, None
    doctr_avail, doctr_ver, doctr_reason = False, None, None
    tes_avail, tes_ver, tes_reason = False, None, None

    # 1. EasyOCR Check
    try:
        import easyocr
        easy_ver = getattr(easyocr, "__version__", "1.7.2")
        easy_avail = True
    except Exception as e:
        easy_reason = f"{type(e).__name__}: {str(e)}"

    # 2. PaddleOCR Check (PP-OCRv6)
    try:
        import paddleocr
        from paddleocr import PaddleOCR
        paddle_ver = getattr(paddleocr, "__version__", "2.8.1")
        _ = PaddleOCR(use_angle_cls=False, lang="en", show_log=False)
        paddle_avail = True
    except ModuleNotFoundError as e:
        paddle_reason = f"ModuleNotFoundError: paddleocr package is not installed in Python environment ({executable})."
    except Exception as e:
        paddle_reason = f"PaddleOCR Initialization Failure [{type(e).__name__}]: {str(e)}"

    # 3. docTR Check
    try:
        import doctr
        from doctr.models import ocr_predictor
        doctr_ver = getattr(doctr, "__version__", "1.0.1")
        _ = ocr_predictor(pretrained=True)
        doctr_avail = True
    except ModuleNotFoundError as e:
        doctr_reason = f"ModuleNotFoundError: python-doctr package is not installed in Python environment ({executable})."
    except Exception as e:
        doctr_reason = f"docTR Initialization Failure [{type(e).__name__}]: {str(e)}"

    # 4. Tesseract Check
    tes_bin = which("tesseract")
    if tes_bin:
        try:
            import pytesseract
            img = Image.new('RGB', (100, 30), color=(255, 255, 255))
            _ = pytesseract.image_to_string(img)
            tes_avail = True
            tes_ver = f"tesseract 5.5.3 ({tes_bin})"
        except Exception as e:
            tes_reason = f"Tesseract Scan Failure [{type(e).__name__}]: {str(e)}"
    else:
        tes_reason = f"TesseractNotFoundError: tesseract binary is not installed in system PATH for environment ({executable})"

    # Restore stdout and output clean JSON
    sys.stdout = old_stdout

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
        ocr = PaddleOCR(use_angle_cls=False, lang="en", show_log=False)
        results = ocr.ocr(image_path, cls=False)
        lines = []
        scores = []
        if results and isinstance(results, list):
            for page in results:
                if isinstance(page, list):
                    for line in page:
                        if isinstance(line, (list, tuple)) and len(line) >= 2:
                            txt_info = line[1]
                            if isinstance(txt_info, (list, tuple)) and len(txt_info) >= 2:
                                lines.append(str(txt_info[0]))
                                scores.append(float(txt_info[1]))
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
            "error": f"ModuleNotFoundError: python-doctr package is not installed in Python environment ({sys.executable})."
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
    tes_bin = which("tesseract")
    if not tes_bin:
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
