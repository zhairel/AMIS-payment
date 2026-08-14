FROM python:3.10-slim

ENV PYTHONDONTWRITEBYTECODE=1 \
    PYTHONUNBUFFERED=1

# Install system dependencies, Tesseract OCR engine, and essential runtime libraries
RUN apt-get update && apt-get install -y --no-install-recommends \
    tesseract-ocr \
    tesseract-ocr-eng \
    libgl1 \
    libglib2.0-0 \
    curl \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

# Install PyTorch CPU first to minimize container layer size
RUN pip install --no-cache-dir torch torchvision --extra-index-url https://download.pytorch.org/whl/cpu

# Copy requirements and install
COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

# Pre-download docTR models during build so the image is fully self-contained and fast to boot
RUN python -c "from doctr.models import ocr_predictor; ocr_predictor(pretrained=True)"

# Copy application files
COPY app.py .
COPY ocr_engine_runner.py .

EXPOSE 8088

HEALTHCHECK --interval=30s --timeout=10s --retries=3 --start-period=30s \
  CMD curl -f http://localhost:8088/health || exit 1

CMD ["uvicorn", "app:app", "--host", "0.0.0.0", "--port", "8088", "--workers", "1"]
