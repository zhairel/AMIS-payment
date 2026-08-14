<x-guest-layout title="Family Payments Login" :show-loader="false">
    <div class="min-h-screen bg-slate-950 flex flex-col lg:grid lg:grid-cols-[1.05fr_.95fr]">
        <!-- LEFT / TOP BRANDING PANEL: Clean AMIS Green with Subtle Minimal Geometric Depth -->
        <section class="relative overflow-hidden bg-[#065f46] px-6 py-14 text-white flex flex-col items-center justify-center lg:min-h-screen lg:p-14">
            <!-- Subtle Top-Right Geometric Shapes -->
            <div class="absolute -right-24 -top-24 h-96 w-96 rounded-full border border-white/10 bg-white/[0.03] pointer-events-none"></div>
            <div class="absolute -right-10 -top-10 h-64 w-64 rounded-full border border-white/5 bg-emerald-400/[0.04] pointer-events-none"></div>

            <!-- Subtle Bottom-Left Geometric Shapes -->
            <div class="absolute -bottom-32 -left-32 h-[28rem] w-[28rem] rounded-full border border-white/10 bg-white/[0.03] pointer-events-none"></div>
            <div class="absolute -bottom-16 -left-16 h-72 w-72 rounded-full border border-white/5 bg-emerald-400/[0.04] pointer-events-none"></div>

            <div class="relative z-10 flex flex-col items-center justify-center text-center max-w-xl mx-auto">
                <!-- 1. AMIS Official Logo -->
                <img src="{{ asset('images/AMIS_Logo.png') }}" class="h-32 w-32 sm:h-36 sm:w-36 lg:h-44 lg:w-44 object-contain drop-shadow-xl" alt="AL MUNAWWARA ISLAMIC SCHOOL Logo">

                <!-- 2. Arabic School Name -->
                <h1 class="mt-8 sm:mt-10 lg:mt-12 text-3xl sm:text-4xl lg:text-[40px] xl:text-[44px] font-bold tracking-wide text-white font-serif leading-snug drop-shadow" dir="rtl" style="font-family: 'Amiri', 'Tajawal', serif;">
                    المدرسة المنورة الإسلامية
                </h1>

                <!-- 3. English School Name -->
                <p class="mt-4 lg:mt-5 text-lg sm:text-xl lg:text-2xl font-bold uppercase tracking-[0.22em] text-white leading-normal">
                    AL MUNAWWARA ISLAMIC SCHOOL
                </p>

                <!-- 4. System Name -->
                <p class="mt-3 lg:mt-4 text-xl sm:text-2xl lg:text-[28px] font-bold tracking-wide text-emerald-100">
                    Family Payment System
                </p>

                <!-- 5. Description -->
                <p class="mt-6 lg:mt-8 max-w-[480px] text-sm sm:text-base lg:text-base leading-relaxed text-emerald-50/85">
                    Access your children's balances, monthly tuition fees, official statements of account, and payment receipts in one secure portal.
                </p>
            </div>
        </section>

        <!-- RIGHT / BOTTOM LOGIN PANEL -->
        <section class="flex flex-1 flex-col items-center justify-center bg-slate-50 px-4 py-10 sm:px-8 lg:min-h-screen lg:py-12">
            <div class="w-full max-w-md my-auto" x-data="{
                step: 'email',
                email: '{{ old('email') }}',
                otp: ['', '', '', ''],
                loading: false,
                signingIn: false,
                error: '',
                success: '',
                expirySeconds: 300,
                resendCooldown: 30,
                isExpired: false,
                isLocked: false,
                lockoutSeconds: 0,
                timerInterval: null,
                cooldownInterval: null,
                lockoutInterval: null,

                formatTimer(totalSeconds) {
                    const mins = Math.floor(Math.max(0, totalSeconds) / 60);
                    const secs = Math.max(0, totalSeconds) % 60;
                    return String(mins).padStart(2, '0') + ':' + String(secs).padStart(2, '0');
                },

                startTimers() {
                    this.stopTimers();
                    this.expirySeconds = 300;
                    this.resendCooldown = 30;
                    this.isExpired = false;
                    this.isLocked = false;
                    this.lockoutSeconds = 0;

                    this.timerInterval = setInterval(() => {
                        if (this.expirySeconds > 0) {
                            this.expirySeconds--;
                        } else {
                            this.isExpired = true;
                            clearInterval(this.timerInterval);
                        }
                    }, 1000);

                    this.cooldownInterval = setInterval(() => {
                        if (this.resendCooldown > 0) {
                            this.resendCooldown--;
                        } else {
                            clearInterval(this.cooldownInterval);
                        }
                    }, 1000);
                },

                startLockout(duration) {
                    this.stopTimers();
                    this.isLocked = true;
                    this.isExpired = false;
                    this.lockoutSeconds = duration || 300;
                    this.otp = ['', '', '', ''];
                    this.error = '';

                    this.lockoutInterval = setInterval(() => {
                        if (this.lockoutSeconds > 0) {
                            this.lockoutSeconds--;
                        } else {
                            clearInterval(this.lockoutInterval);
                        }
                    }, 1000);
                },

                stopTimers() {
                    if (this.timerInterval) clearInterval(this.timerInterval);
                    if (this.cooldownInterval) clearInterval(this.cooldownInterval);
                    if (this.lockoutInterval) clearInterval(this.lockoutInterval);
                },

                async request(path, body) {
                    const response = await fetch(path, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content
                        },
                        body: JSON.stringify(body)
                    });
                    const data = await response.json();
                    return { ok: response.ok, status: response.status, data };
                },

                async sendOtp(isResend = false) {
                    if (this.loading) return;
                    if (!this.email || !this.email.includes('@')) {
                        this.error = 'Enter a valid parent email address.';
                        return;
                    }
                    if (isResend && this.resendCooldown > 0 && !this.isExpired && !this.isLocked) {
                        return;
                    }

                    this.loading = true;
                    this.error = '';
                    this.success = '';
                    try {
                        const result = await this.request('{{ route('auth.send-otp') }}', { email: this.email });
                        if (result.ok && result.data.status === 'success') {
                            this.step = 'code';
                            this.success = isResend ? 'A new verification code has been sent.' : (result.data.message || 'A 4-digit verification code has been sent to your email.');
                            this.otp = ['', '', '', ''];
                            this.startTimers();
                            this.$nextTick(() => {
                                if (this.$refs.otp0) this.$refs.otp0.focus();
                            });
                        } else {
                            if (result.data.locked) {
                                this.step = 'code';
                                this.startLockout(result.data.lockout_seconds || 300);
                            } else {
                                this.error = result.data.message || Object.values(result.data.errors || {})[0]?.[0] || 'Could not send the verification code.';
                            }
                        }
                    } catch (e) {
                        this.error = 'Network error. Please try again.';
                    } finally {
                        this.loading = false;
                    }
                },

                inputOtp(event, index) {
                    const val = event.target.value.replace(/\D/g, '').slice(-1);
                    this.otp[index] = val;
                    if (val && index < 3) {
                        this.$nextTick(() => {
                            if (this.$refs['otp' + (index + 1)]) {
                                this.$refs['otp' + (index + 1)].focus();
                            }
                        });
                    }
                },

                keyOtp(event, index) {
                    if (event.key === 'Backspace') {
                        if (!this.otp[index] && index > 0) {
                            this.$refs['otp' + (index - 1)].focus();
                        }
                    } else if (event.key === 'ArrowLeft' && index > 0) {
                        this.$refs['otp' + (index - 1)].focus();
                    } else if (event.key === 'ArrowRight' && index < 3) {
                        this.$refs['otp' + (index + 1)].focus();
                    } else if (event.key === 'Enter') {
                        if (this.otp.join('').length === 4 && !this.isExpired && !this.isLocked) {
                            this.verifyOtp();
                        }
                    }
                },

                pasteOtp(event) {
                    const raw = event.clipboardData ? event.clipboardData.getData('text') : '';
                    const digits = raw.replace(/\D/g, '').slice(0, 4).split('');
                    if (!digits.length) return;
                    event.preventDefault();
                    this.otp = [digits[0] || '', digits[1] || '', digits[2] || '', digits[3] || ''];
                    const lastIdx = Math.min(3, digits.length - 1);
                    this.$nextTick(() => {
                        if (this.$refs['otp' + lastIdx]) {
                            this.$refs['otp' + lastIdx].focus();
                        }
                    });
                },

                async verifyOtp() {
                    const code = this.otp.join('');
                    if (code.length !== 4 || this.loading || this.isExpired || this.isLocked) return;

                    this.loading = true;
                    this.error = '';
                    this.success = '';
                    try {
                        const result = await this.request('{{ route('auth.verify-otp') }}', {
                            email: this.email,
                            code: code
                        });
                        if (result.ok && result.data.status === 'success') {
                            this.signingIn = true;
                            this.stopTimers();
                            setTimeout(() => {
                                window.location.href = result.data.redirectUrl;
                            }, 500);
                        } else {
                            if (result.data.locked) {
                                this.startLockout(result.data.lockout_seconds || 300);
                            } else if (result.data.expired) {
                                this.isExpired = true;
                                this.stopTimers();
                            } else {
                                this.error = result.data.message || 'Incorrect verification code. Please try again.';
                                this.otp = ['', '', '', ''];
                                this.$nextTick(() => {
                                    if (this.$refs.otp0) this.$refs.otp0.focus();
                                });
                            }
                        }
                    } catch (e) {
                        this.error = 'Network error. Please try again.';
                    } finally {
                        if (!this.signingIn) {
                            this.loading = false;
                        }
                    }
                },

                changeEmail() {
                    this.stopTimers();
                    this.step = 'email';
                    this.otp = ['', '', '', ''];
                    this.error = '';
                    this.success = '';
                    this.isExpired = false;
                    this.isLocked = false;
                    this.lockoutSeconds = 0;
                    this.$nextTick(() => {
                        const emailInput = document.getElementById('otp-email');
                        if (emailInput) emailInput.focus();
                    });
                }
            }">
                <!-- Main White Card -->
                <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-xl shadow-slate-200/60 sm:p-8">
                    <div>
                        <h2 class="text-2xl font-black text-slate-900">Sign in to Family Payments</h2>
                        <p class="mt-2 text-sm leading-6 text-slate-500">Use a one-time email code to securely access your family's payment account.</p>
                    </div>

                    <!-- Single Clean Success Banner -->
                    <div x-show="success && !isExpired && !isLocked" x-cloak x-text="success" aria-live="polite" class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 p-3.5 text-sm font-semibold text-emerald-800"></div>

                    <!-- Single Clean Error Banner (Shown only for standard errors, NOT duplicated when expired/locked) -->
                    <div x-show="error && !isExpired && !isLocked" x-cloak x-text="error" aria-live="polite" class="mt-4 rounded-xl border border-rose-200 bg-rose-50 p-3.5 text-sm font-semibold text-rose-800"></div>

                    <!-- Global Validation Errors -->
                    @if ($errors->any())
                        <div class="mt-4 rounded-xl border border-rose-200 bg-rose-50 p-3.5 text-sm font-semibold text-rose-800">{{ $errors->first() }}</div>
                    @endif

                    <!-- STEP 1: Email Input Form -->
                    <div x-show="step==='email'" class="mt-6">
                        <label for="otp-email" class="text-xs font-bold uppercase tracking-wider text-slate-700">PARENT EMAIL ADDRESS</label>
                        <input id="otp-email" x-model.trim="email" @keydown.enter.prevent="sendOtp()" type="email" autocomplete="email" placeholder="jhon@gmail.com" class="mt-2 w-full rounded-xl border-slate-300 bg-slate-50 px-4 py-3 text-sm focus:border-emerald-600 focus:ring-emerald-600">
                        <button type="button" @click="sendOtp()" :disabled="loading" class="mt-4 w-full rounded-xl bg-emerald-700 px-5 py-3.5 text-sm font-extrabold text-white hover:bg-emerald-800 disabled:opacity-50 transition">
                            <span x-show="!loading">Send Verification Code</span>
                            <span x-show="loading">Sending Securely...</span>
                        </button>
                    </div>

                    <!-- STEP 2: 4-digit OTP Verification Flow -->
                    <div x-show="step==='code'" x-cloak class="mt-6">
                        <!-- Email Instruction Layout: Separated to its own prominent line -->
                        <div>
                            <p class="text-sm text-slate-600">Enter the 4-digit code sent to</p>
                            <p class="mt-1 text-sm font-bold text-slate-900 break-all select-all" x-text="email"></p>
                        </div>

                        <!-- STATE A: 5-Minute Attempt Lockout Card (Single Dedicated Error Card) -->
                        <div x-show="isLocked" x-cloak class="mt-5 rounded-2xl border border-rose-200 bg-rose-50 p-5 text-center">
                            <h3 class="text-sm font-black text-rose-900">Too Many Incorrect Attempts</h3>
                            
                            <template x-if="lockoutSeconds > 0">
                                <p class="mt-1.5 text-xs text-rose-700">
                                    Try again in <span class="font-mono font-bold text-rose-900" x-text="formatTimer(lockoutSeconds)"></span>
                                </p>
                            </template>

                            <template x-if="lockoutSeconds === 0">
                                <div class="mt-2">
                                    <p class="text-xs text-rose-700">You can request a new verification code.</p>
                                    <button type="button" @click="sendOtp(true)" :disabled="loading" class="mt-3.5 w-full rounded-xl bg-emerald-700 px-5 py-3 text-xs font-extrabold text-white hover:bg-emerald-800 disabled:opacity-50 transition">
                                        <span x-show="!loading">Send New Code</span>
                                        <span x-show="loading">Sending Securely...</span>
                                    </button>
                                </div>
                            </template>
                        </div>

                        <!-- STATE B: Expired Code Card (Single Dedicated Expiration Card) -->
                        <div x-show="isExpired && !isLocked" x-cloak class="mt-5 rounded-2xl border border-rose-200 bg-rose-50 p-5 text-center">
                            <h3 class="text-sm font-black text-rose-900">Verification Code Expired</h3>
                            <p class="mt-1 text-xs text-rose-700">This code is no longer valid. Please send a new code.</p>
                            <button type="button" @click="sendOtp(true)" :disabled="loading" class="mt-4 w-full rounded-xl bg-rose-600 px-5 py-3 text-xs font-extrabold text-white hover:bg-rose-700 disabled:opacity-50 transition">
                                <span x-show="!loading">Send New Code</span>
                                <span x-show="loading">Sending Securely...</span>
                            </button>
                        </div>

                        <!-- STATE C: Active OTP Inputs & Verification -->
                        <div x-show="!isExpired && !isLocked">
                            <!-- 4 Digit Boxes -->
                            <div class="mt-5 grid grid-cols-4 gap-3" @paste="pasteOtp($event)">
                                @for($index=0;$index<4;$index++)
                                    <input type="text"
                                           inputmode="numeric"
                                           autocomplete="one-time-code"
                                           maxlength="1"
                                           aria-label="Digit {{ $index + 1 }} of 4"
                                           x-model="otp[{{ $index }}]"
                                           x-ref="otp{{ $index }}"
                                           @input="inputOtp($event,{{ $index }})"
                                           @keydown="keyOtp($event,{{ $index }})"
                                           class="h-14 rounded-xl border-slate-300 text-center text-2xl font-black text-slate-900 focus:border-emerald-600 focus:ring-emerald-600">
                                @endfor
                            </div>

                            <!-- 5-Minute Countdown Indicator -->
                            <div class="mt-3.5 flex items-center justify-center">
                                <p class="text-xs transition-colors" :class="expirySeconds <= 60 ? 'text-amber-600 font-semibold' : 'text-slate-500 font-medium'">
                                    Code expires in <span class="font-mono font-bold" x-text="formatTimer(expirySeconds)"></span>
                                </p>
                            </div>

                            <!-- Verify and Sign In Button -->
                            <button type="button"
                                    @click="verifyOtp()"
                                    :disabled="loading || signingIn || otp.join('').length !== 4"
                                    class="mt-5 w-full rounded-xl bg-emerald-700 px-5 py-3.5 text-sm font-extrabold text-white hover:bg-emerald-800 disabled:opacity-50 disabled:cursor-not-allowed transition">
                                <span x-show="!loading && !signingIn">Verify and Sign In</span>
                                <span x-show="loading && !signingIn">Verifying...</span>
                                <span x-show="signingIn">Verified. Signing you in...</span>
                            </button>
                        </div>

                        <!-- Bottom Navigation: Change Email & Resend Cooldown -->
                        <div class="mt-5 flex items-center justify-between text-xs">
                            <button type="button" @click="changeEmail()" class="font-bold text-slate-500 hover:text-slate-800 transition">
                                ← Change Email
                            </button>

                            <div x-show="!isExpired && !isLocked">
                                <template x-if="resendCooldown > 0">
                                    <span class="font-semibold text-slate-400">
                                        Resend code in <span class="font-mono font-bold" x-text="formatTimer(resendCooldown)"></span>
                                    </span>
                                </template>
                                <template x-if="resendCooldown === 0">
                                    <button type="button" @click="sendOtp(true)" :disabled="loading" class="font-bold text-emerald-700 hover:text-emerald-900 transition">
                                        Resend Code
                                    </button>
                                </template>
                            </div>
                        </div>
                    </div>

                    <!-- Divider OR -->
                    <div class="my-6 flex items-center gap-3">
                        <div class="h-px flex-1 bg-slate-200"></div>
                        <span class="text-[11px] font-bold uppercase text-slate-400">or</span>
                        <div class="h-px flex-1 bg-slate-200"></div>
                    </div>

                    <!-- Google SSO -->
                    <div>
                        <a href="{{ route('auth.google') }}" class="flex w-full items-center justify-center gap-2.5 rounded-xl border border-slate-300 bg-white px-5 py-3 text-sm font-bold text-slate-700 hover:bg-slate-50 transition">
                            <svg class="h-4 w-4" viewBox="0 0 24 24">
                                <path fill="#EA4335" d="M12 5.04c1.62 0 3.06.56 4.21 1.66l3.15-3.15C17.45 1.76 14.94 1 12 1 7.35 1 3.39 3.65 1.44 7.5l3.8 2.94c.9-2.7 3.4-4.4 6.76-4.4z"/>
                                <path fill="#4285F4" d="M23.49 12.27c0-.81-.07-1.59-.2-2.34H12v4.44h6.44c-.28 1.48-1.12 2.73-2.38 3.58l3.69 2.87c2.16-1.99 3.4-4.93 3.4-8.55z"/>
                                <path fill="#FBBC05" d="M5.24 14.56c-.23-.69-.36-1.43-.36-2.2s.13-1.51.36-2.2L1.44 7.22C.52 9.07 0 11.13 0 13.3c0 2.17.52 4.23 1.44 6.08l3.8-2.82z"/>
                                <path fill="#34A853" d="M12 23c3.24 0 5.97-1.07 7.96-2.92l-3.69-2.87c-1.02.68-2.33 1.09-3.97 1.09-3.36 0-5.86-1.7-6.76-4.4l-3.8 2.94C3.39 20.35 7.35 23 12 23z"/>
                            </svg>
                            Sign In with Google
                        </a>
                    </div>
                </div>

                <!-- Footer notice & copyright -->
                <div class="mt-6 space-y-1.5 text-center text-xs text-slate-400">
                    <p>OTP requests and sign-in attempts are rate-limited and audited.</p>
                    <p class="text-slate-400/80">© {{ date('Y') }} Al Munawwara Islamic School. All rights reserved.</p>
                </div>
            </div>
        </section>
    </div>
</x-guest-layout>
