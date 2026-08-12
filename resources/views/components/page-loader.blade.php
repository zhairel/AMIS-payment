@props(['logo' => true])

<div {{ $attributes->merge(['class' => 'initial-loading-screen']) }}>
    @if ($logo)
        <div class="initial-loading-header" style="text-align: center; margin-bottom: 1.5rem;">
            <img src="{{ asset('images/AMIS_Logo.png') }}" alt="AMIS" class="initial-loading-logo" style="width: 70px; height: 70px; margin: 0 auto 0.75rem;">
            <div class="skeleton" style="width: 140px; height: 16px; margin: 0 auto;"></div>
        </div>
    @endif

    {{-- Clean Skeleton Layout Preview --}}
    <div class="skeleton-page-preview" style="width: min(90%, 900px); margin: 0 auto; display: flex; flex-direction: column; gap: 1.25rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; padding-bottom: 0.5rem;">
            <div>
                <div class="skeleton" style="width: 180px; height: 22px; margin-bottom: 0.5rem;"></div>
                <div class="skeleton" style="width: 280px; height: 14px;"></div>
            </div>
            <div class="skeleton" style="width: 110px; height: 40px; border-radius: 10px;"></div>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem;">
            <div style="padding: 1.25rem; background: #ffffff; border-radius: 14px; border: 1px solid #f1f5f9;">
                <div class="skeleton" style="width: 40%; height: 12px; margin-bottom: 0.6rem;"></div>
                <div class="skeleton" style="width: 70%; height: 26px; margin-bottom: 0.5rem;"></div>
                <div class="skeleton" style="width: 55%; height: 12px;"></div>
            </div>
            <div style="padding: 1.25rem; background: #ffffff; border-radius: 14px; border: 1px solid #f1f5f9;">
                <div class="skeleton" style="width: 40%; height: 12px; margin-bottom: 0.6rem;"></div>
                <div class="skeleton" style="width: 70%; height: 26px; margin-bottom: 0.5rem;"></div>
                <div class="skeleton" style="width: 55%; height: 12px;"></div>
            </div>
            <div style="padding: 1.25rem; background: #ffffff; border-radius: 14px; border: 1px solid #f1f5f9;">
                <div class="skeleton" style="width: 40%; height: 12px; margin-bottom: 0.6rem;"></div>
                <div class="skeleton" style="width: 70%; height: 26px; margin-bottom: 0.5rem;"></div>
                <div class="skeleton" style="width: 55%; height: 12px;"></div>
            </div>
        </div>
    </div>
</div>

