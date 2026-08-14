#!/usr/bin/env python3
"""
AMIS AI Receipt Scanner - docTR + Tesseract OCR Engine Runner.
Runs inside dedicated Docker container or Python 3.10+ environment.
Evaluates docTR (primary) and Tesseract (fallback) with model caching.
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
logging.getLogger("torch").setLevel(logging.ERROR)

logger = logging.getLogger("amis-ocr-runner")

# Global cached docTR model
_DOCTR_PREDICTOR = None


def get_doctr_model():
    """
    Load docTR model once and cache in memory for high-performance reuse.
    """
    global _DOCTR_PREDICTOR
    if _DOCTR_PREDICTOR is None:
        try:
            from doctr.models import ocr_predictor
            # Load pretrained model (det: db_resnet50, reco: crnn_vgg16_bn or default)
            _DOCTR_PREDICTOR = ocr_predictor(pretrained=True)
            logger.info("[OCR] docTR model successfully loaded and cached in memory.")
        except Exception as e:
            logger.error(f"[OCR] Failed to load docTR predictor: {e}")
            raise e
    return _DOCTR_PREDICTOR


def check_env():
    """
    Check availability of docTR and Tesseract engines.
    """
    executable = sys.executable
    version = f"Python {sys.version.split()[0]}"

    old_stdout = sys.stdout
    sys.stdout = open(os.devnull, 'w')

    doctr_avail, doctr_ver, doctr_reason = False, None, None
    tes_avail, tes_ver, tes_reason = False, None, None

    # 1. docTR Check
    try:
        import doctr
        doctr_ver = getattr(doctr, "__version__", "1.0.1")
        _ = get_doctr_model()
        doctr_avail = True
    except ModuleNotFoundError:
        doctr_reason = f"ModuleNotFoundError: python-doctr package is not installed in Python environment ({executable})."
    except Exception as e:
        doctr_reason = f"docTR Initialization Failure [{type(e).__name__}]: {str(e)}"

    # 2. Tesseract Check
    tes_bin = which("tesseract")
    if not tes_bin:
        for possible in ["/usr/bin/tesseract", "/usr/local/bin/tesseract"]:
            if os.path.isfile(possible):
                tes_bin = possible
                break

    if tes_bin:
        try:
            import pytesseract
            img = Image.new('RGB', (100, 30), color=(255, 255, 255))
            _ = pytesseract.image_to_string(img)
            tes_avail = True
            tes_ver = f"Tesseract ({tes_bin})"
        except Exception as e:
            tes_reason = f"Tesseract Scan Failure [{type(e).__name__}]: {str(e)}"
    else:
        tes_reason = f"TesseractNotFoundError: tesseract binary is not installed in system PATH for environment ({executable})"

    sys.stdout = old_stdout

    return {
        "python_executable": executable,
        "python_version": version,
        "engines": {
            "doctr": {"available": doctr_avail, "version": doctr_ver, "reason": doctr_reason},
            "tesseract": {"available": tes_avail, "version": tes_ver, "reason": tes_reason},
        }
    }


def run_doctr(image_path: str) -> dict:
    """
    Execute docTR OCR on the given image path.
    """
    start = time.time()
    try:
        from doctr.io import DocumentFile
        model = get_doctr_model()
        doc = DocumentFile.from_images(image_path)
        result = model(doc)
        lines = []
        scores = []
        for page in result.pages:
            for block in page.blocks:
                for line in block.lines:
                    text = " ".join([word.value for word in line.words if word.value.strip()])
                    if text.strip():
                        avg_conf = sum([word.confidence for word in line.words]) / len(line.words) if line.words else 0
                        lines.append(text.strip())
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
    except ModuleNotFoundError:
        duration = int((time.time() - start) * 1000)
        return {
            "engine": "docTR",
            "status": "NOT_AVAILABLE",
            "raw_text": "",
            "regions": 0,
            "confidence": None,
            "duration_ms": duration,
            "error": f"ModuleNotFoundError: python-doctr is not installed."
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


def run_tesseract(image_path: str) -> dict:
    """
    Execute Tesseract OCR on the given image path with multiple fallbacks.
    """
    start = time.time()
    tes_bin = which("tesseract")
    if not tes_bin:
        for possible in ["/usr/bin/tesseract", "/usr/local/bin/tesseract"]:
            if os.path.isfile(possible):
                tes_bin = possible
                break

    if not tes_bin:
        duration = int((time.time() - start) * 1000)
        return {
            "engine": "Tesseract",
            "status": "NOT_AVAILABLE",
            "raw_text": "",
            "regions": 0,
            "confidence": None,
            "duration_ms": duration,
            "error": "Tesseract binary not found in system."
        }

    try:
        import pytesseract
        data = pytesseract.image_to_data(
            Image.open(image_path),
            config="--psm 6",
            output_type=pytesseract.Output.DICT,
        )
        grouped_lines = {}
        scores = []
        for index, word in enumerate(data.get("text", [])):
            word = str(word).strip()
            if not word:
                continue
            key = (
                data.get("block_num", [0])[index],
                data.get("par_num", [0])[index],
                data.get("line_num", [0])[index],
            )
            grouped_lines.setdefault(key, []).append(word)
            try:
                confidence = float(data.get("conf", [-1])[index])
                if confidence >= 0:
                    scores.append(confidence / 100.0)
            except (TypeError, ValueError):
                pass
        lines = [" ".join(words) for words in grouped_lines.values() if words]
        duration = int((time.time() - start) * 1000)
        return {
            "engine": "Tesseract",
            "status": "SUCCESS",
            "raw_text": "\n".join(lines),
            "regions": len(lines),
            "confidence": round(sum(scores) / len(scores), 3) if scores else None,
            "duration_ms": duration,
            "error": None
        }
    except Exception as pytesseract_err:
        import subprocess
        try:
            cmd = [tes_bin, image_path, "stdout", "-l", "eng", "--psm", "6"]
            proc = subprocess.run(cmd, capture_output=True, text=True, timeout=30)
            if proc.returncode == 0 and proc.stdout.strip():
                raw_text = proc.stdout.strip()
                lines = [l.strip() for l in raw_text.splitlines() if l.strip()]
                duration = int((time.time() - start) * 1000)
                return {
                    "engine": "Tesseract",
                    "status": "SUCCESS",
                    "raw_text": raw_text,
                    "regions": len(lines),
                    "confidence": 0.85,
                    "duration_ms": duration,
                    "error": None
                }
        except Exception:
            pass

        duration = int((time.time() - start) * 1000)
        return {
            "engine": "Tesseract",
            "status": "FAILED",
            "raw_text": "",
            "regions": 0,
            "confidence": None,
            "duration_ms": duration,
            "error": f"Tesseract error: {str(pytesseract_err)}"
        }


def run_pipeline(image_path: str, requested_engine: str = "auto") -> dict:
    """
    Execute OCR pipeline:
    - If requested_engine == 'doctr': runs docTR only
    - If requested_engine == 'tesseract': runs Tesseract only
    - If requested_engine == 'auto': tries docTR first; if empty or failed, falls back to Tesseract.
    """
    engine_choice = (requested_engine or "auto").lower().strip()

    if engine_choice == "doctr":
        return run_doctr(image_path)
    elif engine_choice == "tesseract":
        return run_tesseract(image_path)

    # Primary: docTR
    doctr_res = run_doctr(image_path)
    if doctr_res.get("status") == "SUCCESS" and doctr_res.get("raw_text", "").strip():
        return doctr_res

    # Fallback: Tesseract
    logger.info("[OCR] docTR returned empty or failed result; triggering Tesseract fallback.")
    tes_res = run_tesseract(image_path)
    tes_res["fallback_used"] = True
    tes_res["primary_doctr_error"] = doctr_res.get("error")
    return tes_res


def main():
    if len(sys.argv) < 2:
        print(json.dumps({"error": "No command provided"}))
        sys.exit(1)

    cmd = sys.argv[1].lower()

    if cmd == "check_env":
        print(json.dumps(check_env()))
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
            "error": f"FileNotFoundError: Image not found at {image_path}"
        }))
        return

    if cmd == "doctr":
        print(json.dumps(run_doctr(image_path)))
    elif cmd == "tesseract":
        print(json.dumps(run_tesseract(image_path)))
    elif cmd == "pipeline" or cmd == "auto":
        print(json.dumps(run_pipeline(image_path, "auto")))
    else:
        print(json.dumps({"error": f"Unknown OCR engine command '{cmd}'"}))


if __name__ == "__main__":
    main()
