import logging
import os
import shutil
import tempfile
import time
from contextlib import asynccontextmanager
from typing import Optional

from fastapi import FastAPI, File, Form, Header, HTTPException, Request, UploadFile, status
from fastapi.middleware.cors import CORSMiddleware
from fastapi.responses import JSONResponse
import ocr_engine_runner

# Configure structured logging
logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] %(message)s",
    datefmt="%Y-%m-%d %H:%M:%S"
)
logger = logging.getLogger("amis-ocr")

# Secret Bearer Token for server-to-server security (optional for local dev, enforced if set in .env)
OCR_SERVICE_TOKEN = os.getenv("OCR_SERVICE_TOKEN", "").strip()
MAX_FILE_SIZE_BYTES = int(os.getenv("MAX_FILE_SIZE_BYTES", 15 * 1024 * 1024))  # 15 MB default

ALLOWED_MIME_TYPES = {
    "image/jpeg",
    "image/jpg",
    "image/png",
    "image/webp",
    "image/bmp",
    "image/tiff",
    "application/pdf",
}

ALLOWED_EXTENSIONS = {".jpg", ".jpeg", ".png", ".webp", ".bmp", ".tiff", ".pdf"}


@asynccontextmanager
async def lifespan(app: FastAPI):
    """
    FastAPI Lifespan handler: Preloads docTR neural network into RAM on startup
    so subsequent receipt scan requests are served with minimal latency.
    """
    logger.info("[OCR] Starting AMIS OCR Service...")
    logger.info("[OCR] Preloading docTR model weights into memory...")
    try:
        ocr_engine_runner.get_doctr_model()
        logger.info("[OCR] docTR model preloaded successfully. Ready to accept incoming scans.")
    except Exception as e:
        logger.warning(f"[OCR] docTR warmup warning: {e}. Fallback to on-demand init.")

    yield

    logger.info("[OCR] AMIS OCR Service shutting down.")


app = FastAPI(
    title="AMIS OCR Microservice",
    description="Dedicated 24/7 docTR + Tesseract OCR Production Service for AMIS Family Payment System",
    version="1.0.0",
    lifespan=lifespan
)

# CORS Middleware
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)


def verify_token(authorization: Optional[str] = Header(None)):
    """
    Enforce Bearer token authentication if OCR_SERVICE_TOKEN is configured.
    """
    if not OCR_SERVICE_TOKEN:
        return True

    if not authorization:
        logger.warning("[OCR] Rejected unauthenticated request (Missing Authorization header).")
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Missing Authorization header."
        )

    parts = authorization.split(" ", 1)
    if len(parts) != 2 or parts[0].lower() != "bearer" or parts[1].strip() != OCR_SERVICE_TOKEN:
        logger.warning("[OCR] Rejected unauthorized request (Invalid Bearer token).")
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Invalid OCR service token."
        )
    return True


@app.get("/")
@app.get("/health")
def health_check():
    """
    Lightweight health endpoint.
    Reports operational status without running heavy OCR scans.
    """
    env_info = ocr_engine_runner.check_env()
    return {
        "status": "ok",
        "service": "amis-ocr",
        "version": "1.0.0",
        "engines": {
            "doctr": env_info["engines"]["doctr"]["available"],
            "tesseract": env_info["engines"]["tesseract"]["available"]
        },
        "details": {
            "python": env_info["python_version"],
            "auth_enabled": bool(OCR_SERVICE_TOKEN)
        }
    }


@app.post("/scan")
@app.post("/api/scan")
async def scan_receipt(
    request: Request,
    receipt: Optional[UploadFile] = File(None),
    file: Optional[UploadFile] = File(None),
    engine: str = Form("auto"),
    authorization: Optional[str] = Header(None)
):
    """
    Production Receipt Scanning Endpoint.
    Accepts:
      - receipt: UploadFile (preferred) or file: UploadFile
      - engine: 'auto' (docTR first with Tesseract fallback), 'doctr', or 'tesseract'
    """
    # 1. Security Check
    verify_token(authorization)

    # 2. File extraction & validation
    upload_file = receipt or file
    if not upload_file:
        logger.warning("[OCR] Bad Request: No file attached.")
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="No receipt image uploaded. Expected multipart form parameter 'receipt' or 'file'."
        )

    client_ip = request.client.host if request.client else "unknown"
    filename = upload_file.filename or "receipt.png"
    _, ext = os.path.splitext(filename.lower())

    if ext not in ALLOWED_EXTENSIONS and (upload_file.content_type or "") not in ALLOWED_MIME_TYPES:
        logger.warning(f"[OCR] Invalid file type from {client_ip}: filename={filename}, mime={upload_file.content_type}")
        raise HTTPException(
            status_code=status.HTTP_415_UNSUPPORTED_MEDIA_TYPE,
            detail=f"Unsupported file format '{ext}'. Allowed: JPEG, PNG, WEBP, BMP, PDF."
        )

    req_start = time.time()
    logger.info(f"[OCR] Request received from {client_ip}: file={filename}, engine={engine}")

    # 3. Stream to safe temporary file with size limit
    with tempfile.NamedTemporaryFile(delete=False, suffix=ext or ".png") as temp_file:
        temp_path = temp_file.name
        total_size = 0
        while chunk := await upload_file.read(1024 * 1024):  # 1MB chunks
            total_size += len(chunk)
            if total_size > MAX_FILE_SIZE_BYTES:
                temp_file.close()
                if os.path.exists(temp_path):
                    os.remove(temp_path)
                logger.warning(f"[OCR] File size limit exceeded: {total_size} bytes from {client_ip}")
                raise HTTPException(
                    status_code=status.HTTP_413_REQUEST_ENTITY_TOO_LARGE,
                    detail=f"File exceeds maximum allowed size of {MAX_FILE_SIZE_BYTES // (1024*1024)}MB."
                )
            temp_file.write(chunk)

    try:
        engine_choice = (engine or "auto").lower().strip()
        logger.info(f"[OCR] Processing file with engine strategy: {engine_choice}")

        result = ocr_engine_runner.run_pipeline(temp_path, engine_choice)

        duration_sec = round(time.time() - req_start, 2)
        status_val = result.get("status", "UNKNOWN")
        lines_count = result.get("regions", 0)
        engine_used = result.get("engine", engine_choice)

        logger.info(f"[OCR] Finished ({engine_used}) status={status_val}, lines={lines_count} in {duration_sec}s")

        response_payload = {
            "success": status_val == "SUCCESS",
            "status": status_val,
            "engine": engine_used,
            "raw_text": result.get("raw_text", ""),
            "confidence": result.get("confidence"),
            "regions": lines_count,
            "duration_ms": result.get("duration_ms", int(duration_sec * 1000)),
            "fallback_used": result.get("fallback_used", False),
            "error": result.get("error")
        }

        return JSONResponse(content=response_payload, status_code=status.HTTP_200_OK)

    except Exception as exc:
        logger.error(f"[OCR] Unexpected processing error: {exc}", exc_info=False)
        return JSONResponse(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            content={
                "success": False,
                "status": "FAILED",
                "engine": engine,
                "raw_text": "",
                "confidence": None,
                "error": "Internal OCR processing error."
            }
        )
    finally:
        if os.path.exists(temp_path):
            try:
                os.remove(temp_path)
            except Exception:
                pass
