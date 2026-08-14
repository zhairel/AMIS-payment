# AMIS Payment Portal (`payment.amis.edu.ph`)

The official payment tracking and billing portal for **Al Munawwara Islamic School (AMIS)**. This application lets parents log in to view outstanding balances, gross total fees, and amounts paid for all their enrolled children (students).

---

## 🚀 Features

1. **Authentication Methods (Copied from Enrollment):**
   * **OTP Verification:** Verification code sent directly to parent email.
   * **Google Sign-In:** One-click OAuth login.
   * **Microsoft Sign-In:** Native Microsoft Azure OAuth integration.
2. **Payment Dashboard:**
   * Lists all children (students) linked to the logged-in parent user.
   * Renders modern cards with the student's **Full Name**, **Grade Level**, **AMIS ID** (Student Number), and **Outstanding Balance**.
   * Provides a detailed financial breakdown: **Gross Total Fees** vs. **Amount Settled**.
   * Dynamic pill badges indicating account status: `Paid` (Fully Paid), `Partial` (Partially Paid), and `Unpaid`.
3. **Database Sharing:**
   * Shares the central `amis` database schema with other portals (`amis_enrollment`, `amis_student`, `amis_admin`).

---

## 🛠️ Local Development Setup

### 1. Requirements
* PHP ^8.2 (with JSON, Session, Database PDO support)
* Composer
* Node.js & npm
* MySQL / MariaDB (connected to the main `amis` database)

### 2. Installation
From the `/home/tatsuya/Projects/AMIS/amis_payment` directory, run:
```bash
# Install PHP packages
php ../composer.phar install --ignore-platform-reqs

# Install Node dependencies
npm install

# Build assets with Vite
npm run build
```

### 3. Server Configuration
Verify your `.env` configuration contains the correct ports and settings:
```env
APP_NAME="AMIS Payment"
APP_ENV=local
APP_URL=http://127.0.0.1:8050
SESSION_COOKIE=amis_payment_session

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=amis
DB_USERNAME=amis_user
DB_PASSWORD=amis123
```

### 4. Running the Server Locally
To run the server locally on port `8050`, execute:
```bash
php artisan serve --port=8050
```
Then visit: `http://127.0.0.1:8050`

## Receipt OCR worker

Receipt uploads are stored unchanged on the private disk and processed asynchronously. The worker creates a separate cleanup copy, runs PaddleOCR first, and invokes Tesseract only when critical fields are missing, uncertain, or invalid.

Install the OCR environment:

```bash
python3 -m venv .venv-ocr
.venv-ocr/bin/pip install -r requirements-ocr.txt
# Debian/Ubuntu system packages used for Tesseract and image processing:
sudo apt-get install tesseract-ocr imagemagick
```

Set `PADDLE_OCR_PYTHON` to the virtual environment's Python executable, migrate the database, and keep a dedicated worker running:

```bash
php artisan migrate --force
php artisan queue:work database --queue=receipts,default --tries=2 --timeout=180
```

Finance users (`admin` or `staff`) review receipts at `/finance/receipts`. Receipt approval is deliberately separate from posting a payment to a student balance.
