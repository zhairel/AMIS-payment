<section>
    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.update') }}" class="space-y-6">
        @csrf
        @method('patch')

        <!-- Name Inputs in a responsive 3-column grid -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <!-- First Name -->
            <div>
                <label for="first_name" class="block text-xs font-bold uppercase tracking-wider text-slate-700">First Name</label>
                <div class="relative mt-2">
                    <input id="first_name"
                           name="first_name"
                           type="text"
                           class="block w-full rounded-xl border-slate-300 bg-slate-50/50 px-4 py-3 text-sm font-medium text-slate-900 focus:border-emerald-600 focus:bg-white focus:ring-emerald-600 transition"
                           value="{{ old('first_name') }}"
                           placeholder="First Name"
                           autofocus />
                </div>
                <x-input-error class="mt-2" :messages="$errors?->get('first_name')" />
            </div>

            <!-- Middle Name -->
            <div>
                <label for="middle_name" class="block text-xs font-bold uppercase tracking-wider text-slate-700">Middle Name</label>
                <div class="relative mt-2">
                    <input id="middle_name"
                           name="middle_name"
                           type="text"
                           class="block w-full rounded-xl border-slate-300 bg-slate-50/50 px-4 py-3 text-sm font-medium text-slate-900 focus:border-emerald-600 focus:bg-white focus:ring-emerald-600 transition"
                           value="{{ old('middle_name') }}"
                           placeholder="Middle Name (Optional)" />
                </div>
                <x-input-error class="mt-2" :messages="$errors?->get('middle_name')" />
            </div>

            <!-- Last Name -->
            <div>
                <label for="last_name" class="block text-xs font-bold uppercase tracking-wider text-slate-700">Last Name</label>
                <div class="relative mt-2">
                    <input id="last_name"
                           name="last_name"
                           type="text"
                           class="block w-full rounded-xl border-slate-300 bg-slate-50/50 px-4 py-3 text-sm font-medium text-slate-900 focus:border-emerald-600 focus:bg-white focus:ring-emerald-600 transition"
                           value="{{ old('last_name') }}"
                           placeholder="Last Name" />
                </div>
                <x-input-error class="mt-2" :messages="$errors?->get('last_name')" />
            </div>
        </div>

        <!-- Primary Email Address -->
        <div>
            <label for="email" class="block text-xs font-bold uppercase tracking-wider text-slate-700">Primary Email Address</label>
            <div class="relative mt-2">
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                </div>
                <input id="email"
                       name="email"
                       type="email"
                       class="block w-full rounded-xl border-slate-300 bg-slate-50/50 pl-10 pr-4 py-3 text-sm font-medium text-slate-900 focus:border-emerald-600 focus:bg-white focus:ring-emerald-600 transition"
                       value="{{ old('email', $user->email) }}"
                       required
                       placeholder="jhon@gmail.com"
                       autocomplete="username" />
            </div>
            <x-input-error class="mt-2" :messages="$errors?->get('email')" />

            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                <div class="mt-3 rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs text-amber-800 flex items-center justify-between">
                    <span>Your email address is unverified.</span>
                    <button form="send-verification" class="font-bold underline hover:text-amber-900">
                        Resend verification
                    </button>
                </div>

                @if (session('status') === 'verification-link-sent')
                    <p class="mt-2 text-xs font-bold text-emerald-600">
                        A new verification link has been sent to your email address.
                    </p>
                @endif
            @endif
        </div>

        <div class="flex items-center gap-4 pt-2">
            <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-emerald-700 px-6 py-3 text-sm font-extrabold text-white shadow-sm hover:bg-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-600 focus:ring-offset-2 transition">
                Save Changes
            </button>

            @if (session('status') === 'profile-updated')
                <div x-data="{ show: true }"
                     x-show="show"
                     x-transition
                     x-init="setTimeout(() => show = false, 3000)"
                     class="flex items-center gap-1.5 text-xs font-bold text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-lg px-3 py-1.5">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                    </svg>
                    Profile updated successfully.
                </div>
            @endif
        </div>
    </form>
</section>
