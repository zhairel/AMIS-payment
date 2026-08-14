<x-app-layout>
    <x-slot name="title">Account Profile - AMIS Family Payments</x-slot>

    @php
        $demoChildren = $demoChildren ?? collect();
        $students = $students ?? collect();
        $allChildrenList = $students->isNotEmpty() ? $students : $demoChildren;
    @endphp

    <div class="min-h-screen bg-slate-100/70 py-10 sm:py-14" x-data="{ 
        activeTab: 'personal',
        showAddChildModal: false,
        childForm: {
            name: '',
            student_id: '',
            grade_level: 'Grade 1',
            relationship: 'Parent / Guardian',
            notes: ''
        },
        childRequestSubmitted: false,
        childRequestLoading: false,
        submitChildRequest() {
            if (!this.childForm.name) return;
            this.childRequestLoading = true;
            setTimeout(() => {
                this.childRequestLoading = false;
                this.childRequestSubmitted = true;
            }, 700);
        },
        resetChildModal() {
            this.showAddChildModal = false;
            this.childRequestSubmitted = false;
            this.childForm = { name: '', student_id: '', grade_level: 'Grade 1', relationship: 'Parent / Guardian', notes: '' };
        }
    }">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">
            
            <!-- Top Navigation & Header -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <a href="{{ route('payment.dashboard') }}" class="inline-flex items-center gap-2 text-sm font-bold text-emerald-700 hover:text-emerald-800 transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                        </svg>
                        Back to Family Payments Dashboard
                    </a>
                    <h1 class="mt-2 text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">Account & Family Profile</h1>
                    <p class="mt-1 text-sm text-slate-600">Manage your parent profile information, linked children, and security settings.</p>
                </div>

                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 border border-emerald-200 px-3 py-1 text-xs font-bold text-emerald-800">
                        <span class="h-2 w-2 rounded-full bg-emerald-500 animate-pulse"></span>
                        Family Portal Active
                    </span>
                </div>
            </div>

            <!-- Parent Identity Hero Banner -->
            <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-[#065f46] via-[#054e3a] to-[#033b2c] p-8 sm:p-12 text-white shadow-xl shadow-emerald-950/10">
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
                                        Verified Account
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

            <!-- Modern Tab Switcher -->
            <div class="flex items-center border-b border-slate-200 gap-3">
                <button type="button"
                        @click="activeTab = 'personal'"
                        :class="activeTab === 'personal' ? 'border-emerald-700 text-emerald-800 font-extrabold border-b-2' : 'border-transparent text-slate-500 hover:text-slate-800 font-semibold'"
                        class="inline-flex items-center gap-2 px-4 py-3 text-sm transition pb-3.5 -mb-px">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                    Personal Information
                </button>

                <button type="button"
                        @click="activeTab = 'children'"
                        :class="activeTab === 'children' ? 'border-emerald-700 text-emerald-800 font-extrabold border-b-2' : 'border-transparent text-slate-500 hover:text-slate-800 font-semibold'"
                        class="inline-flex items-center gap-2 px-4 py-3 text-sm transition pb-3.5 -mb-px">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                    </svg>
                    <span>Students' or Children Information</span>
                    <span class="rounded-full bg-emerald-100 text-emerald-800 px-2 py-0.5 text-xs font-bold">{{ $allChildrenList->count() }}</span>
                </button>
            </div>

            <!-- TAB 1: PERSONAL INFORMATION (Clean Single-Column Stack) -->
            <div x-show="activeTab === 'personal'" x-cloak class="space-y-8">
                
                <!-- Card 1: Profile Information -->
                <div class="rounded-3xl border border-slate-200/80 bg-white p-8 sm:p-12 shadow-sm">
                    <div class="flex items-center gap-3 border-b border-slate-100 pb-5">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-50 text-emerald-700">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                        </div>
                        <div>
                            <h2 class="text-lg font-black text-slate-900">Profile Information</h2>
                            <p class="text-xs text-slate-500">Update your account name components and registered email address.</p>
                        </div>
                    </div>

                    <div class="mt-6">
                        @include('profile.partials.update-profile-information-form')
                    </div>
                </div>

                <!-- Card 2: Sign-In Security (Positioned Below Profile Information) -->
                <div class="rounded-3xl border border-slate-200/80 bg-white p-8 sm:p-12 shadow-sm">
                    <div class="flex items-center gap-3 border-b border-slate-100 pb-5">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-50 text-emerald-700">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                            </svg>
                        </div>
                        <div>
                            <h2 class="text-lg font-black text-slate-900">Sign-In Security</h2>
                            <p class="text-xs text-slate-500">Authentication methods connected to your family payment account.</p>
                        </div>
                    </div>

                    <!-- 2-Column Grid for Security Methods -->
                    <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Email OTP Method -->
                        <div class="flex items-center justify-between rounded-2xl border border-emerald-100 bg-emerald-50/60 p-4">
                            <div class="flex items-center gap-3.5">
                                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-700 text-white shadow-sm shrink-0">
                                    <svg class="h-5 w-5" style="width: 20px; height: 20px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                    </svg>
                                </div>
                                <div>
                                    <div class="text-sm font-bold text-slate-900">Email OTP Code</div>
                                    <div class="text-xs text-slate-500">One-time 4-digit code sent to your email</div>
                                </div>
                            </div>
                            <span class="rounded-full bg-emerald-100 border border-emerald-200 px-2.5 py-1 text-xs font-extrabold text-emerald-800">Primary</span>
                        </div>

                        <!-- Google SSO Method -->
                        <div class="flex flex-col justify-between rounded-2xl border border-slate-200 bg-slate-50 p-4">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-3.5">
                                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-white border border-slate-200 shadow-sm shrink-0">
                                        <svg class="h-5 w-5" style="width: 20px; height: 20px;" viewBox="0 0 24 24">
                                            <path fill="#EA4335" d="M12 5.04c1.62 0 3.06.56 4.21 1.66l3.15-3.15C17.45 1.76 14.94 1 12 1 7.35 1 3.39 3.65 1.44 7.5l3.8 2.94c.9-2.7 3.4-4.4 6.76-4.4z"/>
                                            <path fill="#4285F4" d="M23.49 12.27c0-.81-.07-1.59-.2-2.34H12v4.44h6.44c-.28 1.48-1.12 2.73-2.38 3.58l3.69 2.87c2.16-1.99 3.4-4.93 3.4-8.55z"/>
                                            <path fill="#FBBC05" d="M5.24 14.56c-.23-.69-.36-1.43-.36-2.2s.13-1.51.36-2.2L1.44 7.22C.52 9.07 0 11.13 0 13.3c0 2.17.52 4.23 1.44 6.08l3.8-2.82z"/>
                                            <path fill="#34A853" d="M12 23c3.24 0 5.97-1.07 7.96-2.92l-3.69-2.87c-1.02.68-2.33 1.09-3.97 1.09-3.36 0-5.86-1.7-6.76-4.4l-3.8 2.94C3.39 20.35 7.35 23 12 23z"/>
                                        </svg>
                                    </div>
                                    <div>
                                        <div class="text-sm font-bold text-slate-900">Google Sign-In</div>
                                        <div class="text-xs text-slate-500">Linked to {{ $user->email }}</div>
                                    </div>
                                </div>
                                <span class="rounded-full bg-emerald-100 border border-emerald-200 px-2.5 py-1 text-xs font-extrabold text-emerald-800">Connected</span>
                            </div>

                            <div class="mt-3 pt-2.5 border-t border-slate-200/60 flex items-center justify-between">
                                <span class="text-xs text-slate-500">Fast one-click sign-in</span>
                                <a href="{{ route('auth.google') }}" class="text-xs font-bold text-emerald-700 hover:text-emerald-900 transition">
                                    Re-authenticate →
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Clean Security Note (SVG icon only) -->
                    <div class="mt-5 rounded-2xl border border-slate-100 bg-slate-50/70 p-4 flex items-start gap-3 text-xs text-slate-600 leading-relaxed">
                        <div class="flex h-6 w-6 items-center justify-center rounded-lg bg-emerald-50 text-emerald-700 shrink-0 mt-0.5">
                            <svg class="h-4 w-4" style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                            </svg>
                        </div>
                        <span>All sign-in attempts are rate-limited and logged in the official AMIS audit trail for security.</span>
                    </div>
                </div>
            </div>

            <!-- TAB 2: STUDENTS' OR CHILDREN INFORMATION -->
            <div x-show="activeTab === 'children'" x-cloak class="space-y-6">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h2 class="text-xl font-black text-slate-900">Linked Students / Children</h2>
                        <p class="text-xs text-slate-500">Review your children's enrollment status and student records, or request to add another child.</p>
                    </div>

                    <div class="flex items-center gap-3">
                        <button type="button"
                                @click="showAddChildModal = true"
                                class="inline-flex items-center gap-2 rounded-xl bg-emerald-700 px-5 py-3 text-xs font-extrabold text-white hover:bg-emerald-800 shadow-sm transition">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>
                            </svg>
                            <span>Add Child / Link Student</span>
                        </button>
                    </div>
                </div>

                @if($allChildrenList->isEmpty())
                    <div class="rounded-3xl border border-slate-200 bg-white p-12 text-center">
                        <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-700">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                            </svg>
                        </div>
                        <h3 class="mt-3 text-base font-extrabold text-slate-900">No linked students found</h3>
                        <p class="mt-1 text-xs text-slate-500">Click "Add Child / Link Student" to request adding your child.</p>
                        <button type="button" @click="showAddChildModal = true" class="mt-4 inline-flex items-center gap-2 rounded-xl bg-emerald-700 px-5 py-2.5 text-xs font-bold text-white hover:bg-emerald-800">
                            Add Child
                        </button>
                    </div>
                @else
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        @foreach($allChildrenList as $child)
                            @php
                                $cName = $child->first_name ?? explode(' ', $child->display_name ?? $child->name ?? 'Student')[0];
                                $cFullName = $child->display_name ?? $child->name ?? ($child->first_name . ' ' . $child->last_name);
                                $cInitial = mb_substr($cName, 0, 1);
                                $cGrade = $child->grade_level ?? $child->account?->grade_level ?? 'Grade Level';
                                $cId = $child->student_id ?? $child->account?->student_id ?? 'AMIS-2026-' . str_pad($child->id ?? 1, 3, '0', STR_PAD_LEFT);
                                $cAccentBg = str_contains(strtoupper($cName), 'MARYAM') ? 'bg-blue-50 text-blue-700 border-blue-200' : (str_contains(strtoupper($cName), 'YUSUF') ? 'bg-amber-50 text-amber-700 border-amber-200' : 'bg-emerald-50 text-emerald-700 border-emerald-200');
                            @endphp

                            <div class="rounded-3xl border border-slate-200/80 bg-white p-8 shadow-sm flex flex-col justify-between hover:shadow-md transition">
                                <div>
                                    <div class="flex items-center justify-between gap-3">
                                        <div class="flex h-12 w-12 items-center justify-center rounded-2xl border {{ $cAccentBg }} text-lg font-black shadow-inner">
                                            {{ $cInitial }}
                                        </div>
                                        <span class="rounded-full bg-emerald-50 border border-emerald-200 px-2.5 py-0.5 text-[10px] font-extrabold text-emerald-800">
                                            Active Student
                                        </span>
                                    </div>

                                    <div class="mt-4">
                                        <h3 class="text-base font-black text-slate-900">{{ mb_strtoupper($cFullName) }}</h3>
                                        <div class="mt-1 flex items-center gap-2 text-xs text-slate-500 font-semibold">
                                            <span>{{ $cGrade }}</span>
                                            <span aria-hidden="true">·</span>
                                            <span class="font-mono text-slate-400">{{ $cId }}</span>
                                        </div>
                                    </div>

                                    <div class="mt-5 space-y-2 border-t border-slate-100 pt-4 text-xs text-slate-600">
                                        <div class="flex justify-between">
                                            <span class="text-slate-400 font-medium">School Year:</span>
                                            <span class="font-bold text-slate-800">2026–2027</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="mt-6 pt-4 border-t border-slate-100">
                                    <a href="{{ route('payment.dashboard') }}" class="flex items-center justify-center gap-1.5 w-full rounded-xl bg-slate-50 border border-slate-200 py-2.5 text-xs font-bold text-slate-700 hover:bg-slate-100 transition">
                                        <span>View Statement of Account</span>
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7m-7 7"/>
                                        </svg>
                                    </a>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        <!-- MODAL: Request to Add / Link Another Child (Admin Approval Required) -->
        <div x-show="showAddChildModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4" @click.self="resetChildModal()">
            <div class="w-full max-w-lg overflow-hidden rounded-3xl bg-white shadow-2xl" role="dialog" aria-modal="true">
                <div class="h-2 bg-gradient-to-r from-[#065f46] via-emerald-600 to-teal-500"></div>

                <div class="p-8 sm:p-10">
                    <!-- Modal Header -->
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <span class="rounded-full bg-emerald-50 border border-emerald-200 px-2.5 py-0.5 text-[10px] font-extrabold uppercase tracking-wider text-emerald-800">
                                Admin Verification Required
                            </span>
                            <h3 class="mt-2 text-xl font-black text-slate-900">Request to Add / Link Child</h3>
                        </div>
                        <button type="button" @click="resetChildModal()" class="flex h-9 w-9 items-center justify-center rounded-full text-slate-400 hover:bg-slate-100 hover:text-slate-600">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>

                    <!-- Informational Notice: Only Admin Can Approve -->
                    <div class="mt-4 rounded-2xl border border-amber-200 bg-amber-50/70 p-4 flex items-start gap-3 text-xs text-amber-900 leading-relaxed">
                        <svg class="h-5 w-5 text-amber-700 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <div>
                            <strong class="font-bold block text-amber-950">Administrative Verification</strong>
                            To protect student records, newly requested children must be reviewed and approved by AMIS Finance & Registrar administrators before appearing on your payment dashboard.
                        </div>
                    </div>

                    <!-- Submitted Success State -->
                    <div x-show="childRequestSubmitted" x-cloak class="mt-6 text-center py-6">
                        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-emerald-100 text-emerald-800">
                            <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                            </svg>
                        </div>
                        <h4 class="mt-4 text-lg font-black text-slate-900">Link Request Submitted</h4>
                        <p class="mt-1 text-xs text-slate-600 max-w-sm mx-auto">
                            Your request to link <strong class="text-slate-900" x-text="childForm.name"></strong> has been forwarded to the school administrator. You will be notified once verified.
                        </p>
                        <button type="button" @click="resetChildModal()" class="mt-6 rounded-xl bg-emerald-700 px-6 py-2.5 text-xs font-extrabold text-white hover:bg-emerald-800">
                            Done
                        </button>
                    </div>

                    <!-- Request Form -->
                    <form x-show="!childRequestSubmitted" class="mt-6 space-y-4" @submit.prevent="submitChildRequest()">
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-700">Child's Full Name <span class="text-rose-500">*</span></label>
                            <input type="text" x-model.trim="childForm.name" required placeholder="e.g. Fatima Lingasa" class="mt-1.5 w-full rounded-xl border-slate-300 bg-slate-50/50 px-4 py-3 text-sm focus:border-emerald-600 focus:bg-white focus:ring-emerald-600">
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700">Student ID / LRN (Optional)</label>
                                <input type="text" x-model.trim="childForm.student_id" placeholder="e.g. 260012" class="mt-1.5 w-full rounded-xl border-slate-300 bg-slate-50/50 px-4 py-3 text-sm focus:border-emerald-600 focus:bg-white focus:ring-emerald-600">
                            </div>

                            <div>
                                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700">Grade Level <span class="text-rose-500">*</span></label>
                                <select x-model="childForm.grade_level" class="mt-1.5 w-full rounded-xl border-slate-300 bg-slate-50/50 px-4 py-3 text-sm focus:border-emerald-600 focus:bg-white focus:ring-emerald-600">
                                    <option value="Kindergarten">Kindergarten</option>
                                    <option value="Grade 1">Grade 1</option>
                                    <option value="Grade 2">Grade 2</option>
                                    <option value="Grade 3">Grade 3</option>
                                    <option value="Grade 4">Grade 4</option>
                                    <option value="Grade 5">Grade 5</option>
                                    <option value="Grade 6">Grade 6</option>
                                    <option value="Grade 7">Grade 7 (JHS)</option>
                                    <option value="Grade 8">Grade 8 (JHS)</option>
                                    <option value="Grade 9">Grade 9 (JHS)</option>
                                    <option value="Grade 10">Grade 10 (JHS)</option>
                                    <option value="Grade 11">Grade 11 (SHS)</option>
                                    <option value="Grade 12">Grade 12 (SHS)</option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-700">Relationship to Student</label>
                            <select x-model="childForm.relationship" class="mt-1.5 w-full rounded-xl border-slate-300 bg-slate-50/50 px-4 py-3 text-sm focus:border-emerald-600 focus:bg-white focus:ring-emerald-600">
                                <option value="Parent / Mother">Mother</option>
                                <option value="Parent / Father">Father</option>
                                <option value="Legal Guardian">Legal Guardian</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-700">Notes / Remarks for Admin (Optional)</label>
                            <textarea x-model.trim="childForm.notes" rows="2" placeholder="Additional details to assist administrator approval..." class="mt-1.5 w-full rounded-xl border-slate-300 bg-slate-50/50 px-4 py-2.5 text-sm focus:border-emerald-600 focus:bg-white focus:ring-emerald-600"></textarea>
                        </div>

                        <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
                            <button type="button" @click="resetChildModal()" class="rounded-xl border border-slate-300 bg-white px-5 py-2.5 text-xs font-bold text-slate-700 hover:bg-slate-50 transition">
                                Cancel
                            </button>
                            <button type="submit" :disabled="childRequestLoading || !childForm.name" class="inline-flex items-center gap-2 rounded-xl bg-emerald-700 px-6 py-2.5 text-xs font-extrabold text-white hover:bg-emerald-800 disabled:opacity-50 transition">
                                <span x-show="!childRequestLoading">Submit Request to Admin</span>
                                <span x-show="childRequestLoading">Submitting...</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
