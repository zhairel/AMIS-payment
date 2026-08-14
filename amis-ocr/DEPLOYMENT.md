# 🚀 AMIS OCR Microservice (docTR + Tesseract) — 24/7 Production Deployment Guide

This guide details how to deploy and maintain the dedicated **AMIS OCR Microservice** 24/7 online on a Linux VPS / Cloud server (Ubuntu / Debian / CentOS / AlmaLinux), independent of any local development computer.

---

## 🏛️ Production Architecture

```text
Parent Browser / AMIS Web
          │
          │ HTTPS (TLS 1.3)
          ▼
   ocr.amis.edu.ph
          │
          │ Nginx (Reverse Proxy + SSL)
          ▼
   127.0.0.1:8088
          │
          ▼
   Docker: amis-ocr (FastAPI + Uvicorn)
      ├── docTR (Primary High-Accuracy Neural OCR)
      └── Tesseract (Fallback Engine)
```

---

## 📦 Directory Structure

```text
/opt/amis-ocr/ (or ~/amis-ocr/)
├── Dockerfile                  # Self-contained container with pre-baked docTR weights
├── docker-compose.yml          # Production Compose with restart: unless-stopped & log rotation
├── app.py                      # FastAPI REST application with Bearer Token auth & warmup
├── ocr_engine_runner.py        # Cached docTR predictor + multi-engine fallback runner
├── requirements.txt            # Python dependencies
├── .env                        # Production secrets & tokens (never committed to git)
├── .env.example                # Example environment template
└── nginx/
    └── ocr.amis.edu.ph.conf    # Nginx reverse proxy configuration template
```

---

## 🛠️ Step 1: VPS Server Prerequisites

SSH into your target Cloud VPS / Linux server:

```bash
# Update packages
sudo apt update && sudo apt upgrade -y

# Install Docker, Docker Compose plugin, Nginx, and Certbot
sudo apt install -y docker.io docker-compose-v2 nginx certbot python3-certbot-nginx curl git

# Enable Docker on server boot
sudo systemctl enable docker
sudo systemctl start docker

# Ensure current user can execute docker (optional)
sudo usermod -aG docker $USER
```

---

## 🚀 Step 2: Clone or Copy OCR Service to VPS

```bash
# Option A: Clone from repository
sudo mkdir -p /opt/amis-ocr
sudo chown -R $USER:$USER /opt/amis-ocr
git clone git@github.com:zhairel/AMIS-payment.git /tmp/amis-payment
cp -r /tmp/amis-payment/amis-ocr/* /tmp/amis-payment/amis-ocr/.env.example /opt/amis-ocr/
rm -rf /tmp/amis-payment

cd /opt/amis-ocr

# Create your production .env
cp .env.example .env
```

Generate a secure production token and save it into `.env`:

```bash
# Generate random 32-byte secret token
OCR_TOKEN=$(openssl rand -hex 32)
sed -i "s|OCR_SERVICE_TOKEN=.*|OCR_SERVICE_TOKEN=${OCR_TOKEN}|g" .env

# Display the token (save this for Laravel .env configuration)
cat .env
```

---

## 🐳 Step 3: Build & Start Docker Container

```bash
cd /opt/amis-ocr

# Build the production container (preloads docTR models during build)
docker compose build

# Start container in detached mode
docker compose up -d

# Verify container is running and healthy
docker compose ps
```

Test local health endpoint:

```bash
curl http://127.0.0.1:8088/health
```

Expected output:
```json
{"status":"ok","service":"amis-ocr","version":"1.0.0","engines":{"doctr":true,"tesseract":true},"details":{"python":"Python 3.10.x","auth_enabled":true}}
```

---

## 🌐 Step 4: Configure DNS for `ocr.amis.edu.ph`

In your DNS provider (Cloudflare / cPanel Zone Editor / Route 53):
- **Type**: `A`
- **Name**: `ocr` (or `ocr.amis.edu.ph`)
- **Target / IP**: `<YOUR_VPS_PUBLIC_IP>`
- **Proxy Status**: DNS Only (or Proxied if using Cloudflare Full SSL)
- **TTL**: Auto / 300 seconds

Verify DNS resolution from your terminal:
```bash
dig +short ocr.amis.edu.ph
```

---

## 🔒 Step 5: Configure Nginx Reverse Proxy & SSL (Let's Encrypt)

Copy the Nginx configuration:

```bash
sudo cp /opt/amis-ocr/nginx/ocr.amis.edu.ph.conf /etc/nginx/sites-available/ocr.amis.edu.ph.conf
sudo ln -sf /etc/nginx/sites-available/ocr.amis.edu.ph.conf /etc/nginx/sites-enabled/

# Test Nginx syntax
sudo nginx -t

# Issue SSL Certificate with Certbot
sudo certbot --nginx -d ocr.amis.edu.ph --non-interactive --agree-tos --email admin@amis.edu.ph

# Reload Nginx with new SSL cert
sudo systemctl reload nginx
```

Test the live public endpoint from any machine:

```bash
curl https://ocr.amis.edu.ph/health
```

---

## 🔗 Step 6: Connect Laravel AMIS Payment System

In your Laravel `.env` (on `afps.amis.edu.ph` / `payment.amis.edu.ph`):

```env
OCR_SERVICE_URL=https://ocr.amis.edu.ph
OCR_SERVICE_TOKEN=your_secure_generated_token_from_step_2
```

Clear and re-cache Laravel configuration:

```bash
php artisan config:clear
php artisan optimize
```

---

## 🔄 Step 7: Verification & Reboot Resilience Test

### 1. Test VPS Reboot Recovery
```bash
sudo reboot
```
Wait 60 seconds for the VPS to come back online, then verify:
```bash
systemctl status docker
docker compose -f /opt/amis-ocr/docker-compose.yml ps
curl https://ocr.amis.edu.ph/health
```
The container will be running automatically with `restart: unless-stopped`!

### 2. Test Live Receipt Scan via CLI
```bash
curl -X POST https://ocr.amis.edu.ph/api/scan \
  -H "Authorization: Bearer <YOUR_OCR_TOKEN>" \
  -F "receipt=@/path/to/test_receipt.jpg" \
  -F "engine=auto"
```

---

## 📊 Monitoring & Operational Commands

- **Check Container Status**:
  ```bash
  docker compose -f /opt/amis-ocr/docker-compose.yml ps
  ```
- **View Live Streaming Logs**:
  ```bash
  docker compose -f /opt/amis-ocr/docker-compose.yml logs -f --tail=100 amis-ocr
  ```
- **Check Memory & CPU Consumption**:
  ```bash
  docker stats amis-ocr --no-stream
  ```
- **Restart Container**:
  ```bash
  docker compose -f /opt/amis-ocr/docker-compose.yml restart
  ```
