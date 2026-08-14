<x-guest-layout title="Family Payments Login" :show-loader="false">
    <div class="min-h-screen bg-slate-950 flex flex-col lg:grid lg:grid-cols-[1.05fr_.95fr]">
        <!-- LEFT / TOP BRANDING PANEL: Rich AMIS Logo Green, Larger Typography & Generous Spacing -->
        <section class="relative overflow-hidden bg-gradient-to-br from-[#065f46] via-[#054e3a] to-[#033b2c] px-6 py-14 text-white flex flex-col items-center justify-center lg:min-h-screen lg:p-14">
            <!-- Subtle Tonal Background Shapes (Harmonized with Logo Green Palette) -->
            <div class="absolute -right-28 -top-28 h-96 w-96 rounded-full bg-emerald-400/20 blur-2xl pointer-events-none"></div>
            <div class="absolute -bottom-36 -left-20 h-[30rem] w-[30rem] rounded-full bg-teal-300/15 blur-2xl pointer-events-none"></div>
            <div class="absolute inset-0 bg-radial-at-c from-emerald-500/10 via-transparent to-black/20 pointer-events-none"></div>
            
            <div class="relative z-10 flex flex-col items-center justify-center text-center max-w-xl mx-auto">
                <!-- 1. AMIS Official Logo -->
                <img src="{{ asset('images/AMIS_Logo.png') }}" class="h-32 w-32 sm:h-36 sm:w-36 lg:h-44 lg:w-44 object-contain drop-shadow-2xl" alt="AL MUNAWWARA ISLAMIC SCHOOL Logo">

                <!-- 2. Arabic School Name (+8px larger, generous spacing) -->
                <h1 class="mt-8 sm:mt-10 lg:mt-12 text-3xl sm:text-4xl lg:text-[40px] xl:text-[44px] font-bold tracking-wide text-white font-serif leading-snug drop-shadow" dir="rtl" style="font-family: 'Amiri', 'Tajawal', serif;">
                    المدرسة المنورة الإسلامية
                </h1>

                <!-- 3. English School Name (+8px larger, generous spacing) -->
                <p class="mt-4 lg:mt-5 text-lg sm:text-xl lg:text-2xl font-bold uppercase tracking-[0.22em] text-white/95 leading-normal drop-shadow-sm">
                    AL MUNAWWARA ISLAMIC SCHOOL
                </p>

                <!-- 4. System Name (+8px larger, generous spacing) -->
                <p class="mt-2.5 lg:mt-3 text-base sm:text-lg lg:text-xl font-semibold tracking-wider text-emerald-100/90">
                    Family Payment System
                </p>

                <!-- 5. Description (+8px larger, generous spacing, max-width ~480px) -->
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
                error: '',
                success: '',
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
                async sendOtp() {
                    if (!this.email || !this.email.includes('@')) {
                        this.error = 'Enter a valid parent email address.';
                        return;
                    }
                    this.loading = true;
                    this.error = '';
                    this.success = '';
                    try {
                        const result = await this.request('{{ route('auth.send-otp') }}', { email: this.email });
                        if (result.ok && result.data.status === 'success') {
                            this.step = 'code';
                            this.success = result.data.message || 'Verification code sent to your email.';
                            this.otp = ['', '', '', ''];
                            this.$nextTick(() => {
                                if (this.$refs.otp0) this.$refs.otp0.focus();
                            });
                        } else {
                            this.error = result.data.message || Object.values(result.data.errors || {})[0]?.[0] || 'Could not send the verification code.';
                        }
                    } catch (e) {
                        this.error = 'Network error. Please try again.';
                    } finally {
                        this.loading = false;
                    }
                },
                inputOtp(event, index) {
                    this.otp[index] = event.target.value.replace(/\D/g, '').slice(-1);
                    if (this.otp[index] && index < 3) {
                        this.$refs['otp' + (index + 1)].focus();
                    }
                    if (this.otp.join('').length === 4) {
                        this.verifyOtp();
                    }
                },
                keyOtp(event, index) {
                    if (event.key === 'Backspace' && !this.otp[index] && index > 0) {
                        this.$refs['otp' + (index - 1)].focus();
                    }
                },
                pasteOtp(event) {
                    const digits = event.clipboardData.getData('text').replace(/\D/g, '').slice(0, 4).split('');
                    if (!digits.length) return;
                    event.preventDefault();
                    this.otp = [digits[0] || '', digits[1] || '', digits[2] || '', digits[3] || ''];
                    if (this.otp.join('').length === 4) {
                        this.verifyOtp();
                    }
                },
                async verifyOtp() {
                    if (this.otp.join('').length !== 4 || this.loading) return;
                    this.loading = true;
                    this.error = '';
                    this.success = '';
                    try {
                        const result = await this.request('{{ route('auth.verify-otp') }}', {
                            email: this.email,
                            code: this.otp.join('')
                        });
                        if (result.ok && result.data.status === 'success') {
                            window.location.href = result.data.redirectUrl;
                        } else {
                            this.error = result.data.message || 'Invalid verification code.';
                            this.otp = ['', '', '', ''];
                            this.$nextTick(() => {
                                if (this.$refs.otp0) this.$refs.otp0.focus();
                            });
                        }
                    } catch (e) {
                        this.error = 'Network error. Please try again.';
                    } finally {
                        this.loading = false;
                    }
                }
            }">
                <!-- Main White Card -->
                <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-xl shadow-slate-200/60 sm:p-8">
                    <div>
                        <h2 class="text-2xl font-black text-slate-900">Sign in to Family Payments</h2>
                        <p class="mt-2 text-sm leading-6 text-slate-500">Use a one-time email code to securely access your family's payment account.</p>
                    </div>

                    @if ($errors->any())
                        <div class="mt-4 rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm font-semibold text-rose-800">{{ $errors->first() }}</div>
                    @endif
                    <div x-show="error" x-cloak x-text="error" class="mt-4 rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm font-semibold text-rose-800"></div>
                    <div x-show="success" x-cloak x-text="success" class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm font-semibold text-emerald-800"></div>

                    <!-- Email Code / OTP Flow -->
                    <div class="mt-6">
                        <!-- Step 1: Email Input -->
                        <div x-show="step==='email'">
                            <label for="otp-email" class="text-sm font-bold text-slate-700">Parent email address</label>
                            <input id="otp-email" x-model.trim="email" @keydown.enter.prevent="sendOtp()" type="email" autocomplete="email" placeholder="name@school.edu.ph" class="mt-2 w-full rounded-xl border-slate-300 bg-slate-50 px-4 py-3 text-sm focus:border-emerald-600 focus:ring-emerald-600">
                            <button type="button" @click="sendOtp()" :disabled="loading" class="mt-4 w-full rounded-xl bg-emerald-700 px-5 py-3.5 text-sm font-extrabold text-white hover:bg-emerald-800 disabled:opacity-50 transition">
                                <span x-show="!loading">Send verification code</span>
                                <span x-show="loading">Sending securely…</span>
                            </button>
                        </div>

                        <!-- Step 2: 4-digit Code Input -->
                        <div x-show="step==='code'" x-cloak>
                            <p class="text-sm text-slate-600">Enter the 4-digit code sent to <strong class="break-all text-slate-900" x-text="email"></strong>.</p>
                            <div class="mt-5 grid grid-cols-4 gap-3" @paste="pasteOtp($event)">
                                @for($index=0;$index<4;$index++)
                                    <input type="text" inputmode="numeric" maxlength="1" x-model="otp[{{ $index }}]" x-ref="otp{{ $index }}" @input="inputOtp($event,{{ $index }})" @keydown="keyOtp($event,{{ $index }})" class="h-14 rounded-xl border-slate-300 text-center text-2xl font-black text-slate-900 focus:border-emerald-600 focus:ring-emerald-600">
                                @endfor
                            </div>
                            <button type="button" @click="verifyOtp()" :disabled="loading || otp.join('').length!==4" class="mt-5 w-full rounded-xl bg-emerald-700 px-5 py-3.5 text-sm font-extrabold text-white disabled:opacity-50 transition">
                                <span x-show="!loading">Verify and sign in</span>
                                <span x-show="loading">Verifying…</span>
                            </button>
                            <div class="mt-4 flex items-center justify-between text-xs font-bold">
                                <button type="button" @click="step='email';otp=['','','',''];error='';success=''" class="text-slate-500 hover:text-slate-700">← Change email</button>
                                <button type="button" @click="sendOtp()" :disabled="loading" class="text-emerald-700 hover:text-emerald-900">Resend code</button>
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
                            Sign in with Google
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
