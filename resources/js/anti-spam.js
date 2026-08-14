/**
 * AMIS Payment Portal — Global Anti-Spam, Double-Submit Protection, & Request Deduplication
 */

(function () {
    // 1. Debounce Utility
    window.amisDebounce = function (func, wait = 300) {
        let timeout;
        return function (...args) {
            const context = this;
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(context, args), wait);
        };
    };

    // 2. Abortable Request & Race-Condition Tracker
    const inFlightRequests = new Map();

    window.amisAbortableFetch = async function (key, url, options = {}) {
        if (inFlightRequests.has(key)) {
            const previousController = inFlightRequests.get(key);
            previousController.abort();
        }

        const controller = new AbortController();
        inFlightRequests.set(key, controller);

        try {
            const response = await fetch(url, {
                ...options,
                signal: controller.signal,
            });
            inFlightRequests.delete(key);
            return response;
        } catch (error) {
            if (error.name === 'AbortError') {
                // Request was intentionally superseded by a newer one
                throw error;
            }
            inFlightRequests.delete(key);
            throw error;
        }
    };

    // 3. Global Loading Overlay for Critical Actions
    window.amisShowLoadingOverlay = function (message = 'Processing request...\nPlease do not close this page.') {
        let overlay = document.getElementById('amis-global-loading-overlay');
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.id = 'amis-global-loading-overlay';
            overlay.className = 'amis-loading-overlay';
            overlay.setAttribute('role', 'dialog');
            overlay.setAttribute('aria-modal', 'true');
            overlay.setAttribute('aria-live', 'assertive');
            overlay.innerHTML = `
                <div class="amis-loading-modal">
                    <div class="amis-loading-spinner" aria-hidden="true"></div>
                    <div class="amis-loading-text" id="amis-loading-msg"></div>
                </div>
            `;
            document.body.appendChild(overlay);
        }

        const msgEl = document.getElementById('amis-loading-msg');
        if (msgEl) {
            msgEl.textContent = message;
        }

        overlay.style.display = 'flex';
        requestAnimationFrame(() => {
            overlay.classList.add('is-visible');
        });
    };

    window.amisHideLoadingOverlay = function () {
        const overlay = document.getElementById('amis-global-loading-overlay');
        if (overlay) {
            overlay.classList.remove('is-visible');
            setTimeout(() => {
                overlay.style.display = 'none';
            }, 200);
        }
    };

    // 4. Button Lock & Unlock Helpers
    window.amisLockButton = function (btn, processingText = null) {
        if (!btn || btn.dataset.processing === 'true') return false;

        btn.dataset.processing = 'true';
        btn.dataset.originalHtml = btn.innerHTML;
        btn.setAttribute('aria-disabled', 'true');
        btn.disabled = true;

        if (processingText) {
            btn.innerHTML = `<span class="btn-inline-spinner" aria-hidden="true"></span> <span>${processingText}</span>`;
        } else {
            const currentText = (btn.textContent || '').trim().toLowerCase();
            let label = 'Processing…';
            if (currentText.includes('submit')) label = 'Submitting…';
            else if (currentText.includes('upload') || currentText.includes('scan')) label = 'Uploading…';
            else if (currentText.includes('save')) label = 'Saving…';
            else if (currentText.includes('verify')) label = 'Verifying…';
            else if (currentText.includes('generate') || currentText.includes('soa')) label = 'Generating…';
            else if (currentText.includes('pay')) label = 'Submitting payment…';

            btn.innerHTML = `<span class="btn-inline-spinner" aria-hidden="true"></span> <span>${label}</span>`;
        }

        // Safety unlock fallback after 20 seconds
        const safetyTimer = setTimeout(() => {
            window.amisUnlockButton(btn);
        }, 20000);
        btn.dataset.safetyTimer = String(safetyTimer);

        return true;
    };

    window.amisUnlockButton = function (btn) {
        if (!btn) return;
        if (btn.dataset.safetyTimer) {
            clearTimeout(Number(btn.dataset.safetyTimer));
            delete btn.dataset.safetyTimer;
        }
        btn.dataset.processing = 'false';
        btn.removeAttribute('aria-disabled');
        btn.disabled = false;

        if (btn.dataset.originalHtml) {
            btn.innerHTML = btn.dataset.originalHtml;
            delete btn.dataset.originalHtml;
        }
    };

    // 5. Global Form & Button Event Delegation
    document.addEventListener('DOMContentLoaded', () => {
        // Prevent rapid double submit on regular forms
        document.addEventListener('submit', (e) => {
            const form = e.target;
            if (!(form instanceof HTMLFormElement)) return;

            // Ignore search or read-only GET forms
            if (form.method && form.method.toUpperCase() === 'GET') return;

            if (form.dataset.submitting === 'true') {
                e.preventDefault();
                e.stopImmediatePropagation();
                return;
            }

            form.dataset.submitting = 'true';

            const submitBtn = form.querySelector('button[type="submit"], input[type="submit"]');
            if (submitBtn && submitBtn instanceof HTMLElement) {
                window.amisLockButton(submitBtn);
            }

            // If it is an asynchronous form handled by Alpine/JS, unlock on window error or when notified
            setTimeout(() => {
                form.dataset.submitting = 'false';
            }, 10000);
        }, true);

        // Prevent rapid multi-clicks on action buttons
        document.addEventListener('click', (e) => {
            const target = e.target;
            if (!(target instanceof HTMLElement)) return;

            const btn = target.closest('button, .btn, [role="button"], a.btn-action');
            if (!btn) return;

            // If already processing, kill event immediately
            if (btn.dataset.processing === 'true') {
                e.preventDefault();
                e.stopPropagation();
                return;
            }

            // Auto-lock for marked buttons or critical payment/submit buttons
            if (
                btn.matches('[data-submit-lock]') ||
                btn.classList.contains('payment-primary-action') ||
                btn.classList.contains('btn-payment-submit') ||
                btn.classList.contains('family-finalize')
            ) {
                const locked = window.amisLockButton(btn);
                if (!locked) {
                    e.preventDefault();
                    e.stopPropagation();
                }
            }
        }, true);
    });
})();
