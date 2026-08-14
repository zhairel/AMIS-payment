<section>
    <button type="button"
            x-data=""
            x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')"
            class="inline-flex items-center justify-center rounded-xl bg-rose-600 px-4 py-2.5 text-xs font-extrabold text-white shadow-sm hover:bg-rose-700 transition">
        Delete Account
    </button>

    <x-modal name="confirm-user-deletion" :show="$errors->userDeletion->isNotEmpty()" focusable>
        <form method="post" action="{{ route('profile.destroy') }}" class="p-6 sm:p-8">
            @csrf
            @method('delete')

            <div class="flex items-center gap-3 text-rose-600">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-rose-100">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                </div>
                <h2 class="text-lg font-black text-slate-900">
                    Delete Parent Account
                </h2>
            </div>

            <p class="mt-3 text-sm text-slate-600 leading-relaxed">
                Are you sure you want to permanently delete your parent account? All connected access and data will be removed. Please enter your password to confirm.
            </p>

            <div class="mt-5">
                <label for="password" class="sr-only">Password</label>
                <input id="password"
                       name="password"
                       type="password"
                       class="block w-full rounded-xl border-slate-300 bg-slate-50 px-4 py-3 text-sm focus:border-rose-600 focus:ring-rose-600"
                       placeholder="Enter your current password" />
                <x-input-error :messages="$errors->userDeletion->get('password')" class="mt-2" />
            </div>

            <div class="mt-6 flex items-center justify-end gap-3">
                <button type="button"
                        x-on:click="$dispatch('close')"
                        class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-xs font-bold text-slate-700 hover:bg-slate-50 transition">
                    Cancel
                </button>

                <button type="submit"
                        class="rounded-xl bg-rose-600 px-5 py-2.5 text-xs font-extrabold text-white hover:bg-rose-700 transition">
                    Permanently Delete
                </button>
            </div>
        </form>
    </x-modal>
</section>
