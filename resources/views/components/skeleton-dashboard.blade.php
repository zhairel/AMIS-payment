<div class="family-overview-skeleton" aria-hidden="true" aria-busy="true">
    {{-- Header Skeleton --}}
    <div style="margin-bottom: 1.75rem;">
        <div class="skeleton skeleton-pill" style="width: 140px; height: 22px; border-radius: 9999px; margin-bottom: 0.5rem;"></div>
        <div class="skeleton skeleton-title" style="width: 260px; height: 36px; margin-bottom: 0.4rem;"></div>
        <div class="skeleton skeleton-text" style="width: 380px; height: 16px;"></div>
    </div>

    {{-- Overview Hub Skeleton --}}
    <div class="family-overview-hub">
        <x-skeleton-loader type="hero" />
        <div class="hub-divider"></div>
        <x-skeleton-loader type="stats" />
        <div class="hub-divider"></div>
        <x-skeleton-loader type="children" />
    </div>

    {{-- Student Accounts Skeleton --}}
    <div style="margin-top: 2.25rem;">
        <div style="margin-bottom: 1.25rem;">
            <div class="skeleton skeleton-text" style="width: 120px; height: 13px; margin-bottom: 0.35rem;"></div>
            <div class="skeleton skeleton-title" style="width: 280px; height: 22px; margin-bottom: 0.25rem;"></div>
            <div class="skeleton skeleton-text" style="width: 320px; height: 14px;"></div>
        </div>
        <x-skeleton-loader type="student-cards" :count="2" />
    </div>

    {{-- Navigation Tabs Skeleton --}}
    <div style="margin-top: 2.5rem;">
        <x-skeleton-loader type="tabs" />
    </div>

    {{-- Content Area Skeleton --}}
    <div style="margin-top: 1.5rem;">
        <x-skeleton-loader type="notifications" :count="3" />
    </div>
</div>
