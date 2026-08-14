<x-app-layout>
    <x-slot name="title">Account Profile - AMIS Family Payments</x-slot>

    <div class="min-h-screen bg-slate-100/70 py-8 sm:py-12">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">
            
            <!-- Top Navigation & Header -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <a href="{{ route('payment.dashboard') }}" class="inline-flex items-center gap-2 text-sm font-bold text-emerald-700 hover:text-emerald-800 transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                        </svg>
                        Back to Family Payments Dashboard
                    </a>
                    <h1 class="mt-2 text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">Account & Security Profile</h1>
                    <p class="mt-1 text-sm text-slate-600">Manage your parent profile information, contact email, and security settings.</p>
                </div>

                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 border border-emerald-200 px-3 py-1 text-xs font-bold text-emerald-800">
                        <span class="h-2 w-2 rounded-full bg-emerald-500 animate-pulse"></span>
                        Family Portal Active
                    </span>
                </div>
            </div>

            <!-- Parent Identity Hero Banner -->
            <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-[#065f46] via-[#054e3a] to-[#033b2c] p-6 sm:p-8 text-white shadow-xl shadow-emerald-950/10">
                <!-- Subtle background decorative shapes -->
                <div class="absolute -right-20 -top-20 h-64 w-64 rounded-full border border-white/10 bg-white/[0.03] pointer-events-none"></div>
                <div class="absolute -bottom-24 -left-16 h-72 w-72 rounded-full border border-white/10 bg-white/[0.03] pointer-events-none"></div>

                <div class="relative z-10 flex flex-col md:flex-row md:items-center md:justify-between gap-6">
                    <div class="flex items-center gap-5">
                        <!-- Avatar Initials -->
                        <div class="flex h-16 w-16 sm:h-20 sm:w-20 shrink-0 items-center justify-center rounded-2xl bg-white/10 border border-white/20 text-2xl sm:text-3xl font-black text-white shadow-inner">
                            {{ strtoupper(substr($user->name ?: 'P', 0, 2)) }}
                        </div>

                        <div>
                            <div class="flex flex-wrap items-center gap-2.5">
                                <h2 class="text-xl sm:text-2xl font-black text-white">{{ $user->name }}</h2>
                                <span class="rounded-full bg-emerald-400/20 border border-emerald-300/30 px-2.5 py-0.5 text-xs font-extrabold text-emerald-100">
                                    Parent / Guardian
                                </span>
                            </div>
                            
                            <div class="mt-1.5 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs sm:text-sm text-emerald-100/90">
                                <span class="flex items-center gap-1.5">
                                    <svg class="w-4 h-4 text-emerald-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                    </svg>
                                    {{ $user->email }}
                                </span>

                                @if($user->hasVerifiedEmail())
                                    <span class="inline-flex items-center gap-1 font-bold text-emerald-300">
                                        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                        </svg>
                                        Verified
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- School Portal Meta -->
                    <div class="flex flex-wrap items-center gap-3 md:flex-col md:items-end md:gap-1.5 border-t md:border-t-0 border-white/10 pt-4 md:pt-0">
                        <span class="text-xs font-semibold text-emerald-200 uppercase tracking-wider">Al Munawwara Islamic School</span>
                        <span class="text-xs text-emerald-100/80">Family Payment System Portal</span>
                    </div>
                </div>
            </div>

            <!-- Two-Column Grid: Forms on Left, Info on Right -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                
                <!-- LEFT COLUMN: Profile Form & Password Form (Span 2) -->
                <div class="lg:col-span-2 space-y-8">
                    
                    <!-- Card 1: Profile Information -->
                    <div class="rounded-3xl border border-slate-200/80 bg-white p-6 sm:p-8 shadow-sm">
                        <div class="flex items-center gap-3 border-b border-slate-100 pb-5">
                            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-50 text-emerald-700">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                </svg>
                            </div>
                            <div>
                                <h2 class="text-lg font-black text-slate-900">Profile Information</h2>
                                <p class="text-xs text-slate-500">Update your account name and registered email address.</p>
                            </div>
                        </div>

                        <div class="mt-6">
                            @include('profile.partials.update-profile-information-form')
                        </div>
                    </div>

                    <!-- Card 2: Update Password -->
                    <div class="rounded-3xl border border-slate-200/80 bg-white p-6 sm:p-8 shadow-sm">
                        <div class="flex items-center gap-3 border-b border-slate-100 pb-5">
                            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-50 text-emerald-700">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                </svg>
                            </div>
                            <div>
                                <h2 class="text-lg font-black text-slate-900">Update Password</h2>
                                <p class="text-xs text-slate-500">Ensure your account uses a secure password if you sign in with password.</p>
                            </div>
                        </div>

                        <div class="mt-6">
                            @include('profile.partials.update-password-form')
                        </div>
                    </div>
                </div>

                <!-- RIGHT COLUMN: Security & Account Overview (Span 1) -->
                <div class="space-y-6">
                    
                    <!-- Security Summary Card -->
                    <div class="rounded-3xl border border-slate-200/80 bg-white p-6 shadow-sm">
                        <h3 class="text-sm font-extrabold uppercase tracking-wider text-slate-900">Sign-In Security</h3>
                        <p class="mt-1 text-xs text-slate-500">Authentication methods available for your account.</p>

                        <div class="mt-5 space-y-3.5">
                            <!-- Email OTP Method -->
                            <div class="flex items-center justify-between rounded-2xl border border-emerald-100 bg-emerald-50/60 p-3.5">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-700 text-white">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                        </svg>
                                    </div>
                                    <div>
                                        <div class="text-xs font-bold text-slate-900">Email OTP Code</div>
                                        <div class="text-[11px] text-slate-500">One-time 4-digit code</div>
                                    </div>
                                </div>
                                <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-extrabold text-emerald-800">Primary</span>
                            </div>

                            <!-- Google SSO Method -->
                            <div class="flex items-center justify-between rounded-2xl border border-slate-200 bg-slate-50 p-3.5">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-white border border-slate-200">
                                        <svg class="h-4 w-4" viewBox="0 0 24 24">
                                            <path fill="#EA4335" d="M12 5.04c1.62 0 3.06.56 4.21 1.66l3.15-3.15C17.45 1.76 14.94 1 12 1 7.35 1 3.39 3.65 1.44 7.5l3.8 2.94c.9-2.7 3.4-4.4 6.76-4.4z"/>
                                            <path fill="#4285F4" d="M23.49 12.27c0-.81-.07-1.59-.2-2.34H12v4.44h6.44c-.28 1.48-1.12 2.73-2.38 3.58l3.69 2.87c2.16-1.99 3.4-4.93 3.4-8.55z"/>
                                            <path fill="#FBBC05" d="M5.24 14.56c-.23-.69-.36-1.43-.36-2.2s.13-1.51.36-2.2L1.44 7.22C.52 9.07 0 11.13 0 13.3c0 2.17.52 4.23 1.44 6.08l3.8-2.82z"/>
                                            <path fill="#34A853" d="M12 23c3.24 0 5.97-1.07 7.96-2.92l-3.69-2.87c-1.02.68-2.33 1.09-3.97 1.09-3.36 0-5.86-1.7-6.76-4.4l-3.8 2.94C3.39 20.35 7.35 23 12 23z"/>
                                        </svg>
                                    </div>
                                    <div>
                                        <div class="text-xs font-bold text-slate-900">Google SSO</div>
                                        <div class="text-[11px] text-slate-500">Sign in with Google</div>
                                    </div>
                                </div>
                                <span class="rounded-full bg-slate-200 px-2 py-0.5 text-[10px] font-extrabold text-slate-700">Available</span>
                            </div>
                        </div>

                        <div class="mt-5 border-t border-slate-100 pt-4 text-[11px] text-slate-500 leading-relaxed">
                            <p>🔒 All login attempts and password changes are rate-limited and logged in the official AMIS audit logs for security.</p>
                        </div>
                    </div>

                    <!-- Danger Zone Card -->
                    <div class="rounded-3xl border border-rose-200/80 bg-rose-50/40 p-6 shadow-sm">
                        <div class="flex items-center gap-2.5 text-rose-800">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                            </svg>
                            <h3 class="text-sm font-black uppercase tracking-wider">Danger Zone</h3>
                        </div>
                        <p class="mt-2 text-xs text-rose-700 leading-relaxed">
                            Once your parent account is deleted, all linked data will be removed.
                        </p>
                        <div class="mt-4">
                            @include('profile.partials.delete-user-form')
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
