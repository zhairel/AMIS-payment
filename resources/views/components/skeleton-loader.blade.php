@props(['type' => 'card', 'rows' => 3, 'cols' => 4, 'count' => 3])

@if($type === 'hero')
    <div class="skeleton-hero-row" aria-hidden="true">
        <div class="skeleton-hero-due-panel">
            <div class="skeleton skeleton-pill" style="width: 110px; height: 16px; margin-bottom: 0.5rem;"></div>
            <div class="skeleton skeleton-text" style="width: 140px; height: 14px; margin-bottom: 0.85rem;"></div>
            <div class="skeleton skeleton-amount-large" style="width: 220px; height: 48px; margin-bottom: 0.85rem;"></div>
            <div class="skeleton skeleton-text" style="width: 180px; height: 14px;"></div>
        </div>
        <div class="skeleton-hero-annual-panel">
            <div class="skeleton skeleton-text" style="width: 160px; height: 14px; margin-bottom: 0.5rem;"></div>
            <div class="skeleton skeleton-amount-medium" style="width: 180px; height: 32px; margin-bottom: 0.5rem;"></div>
            <div class="skeleton skeleton-text" style="width: 100%; height: 12px;"></div>
        </div>
    </div>
@elseif($type === 'stats')
    <div class="skeleton-stats-grid" aria-hidden="true">
        @for($i = 0; $i < 4; $i++)
            <div class="skeleton-stat-card">
                <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                    <div class="skeleton skeleton-circle" style="width: 22px; height: 22px;"></div>
                    <div class="skeleton skeleton-text" style="width: 60%; height: 12px;"></div>
                </div>
                <div class="skeleton skeleton-amount-sm" style="width: 75%; height: 24px; margin-bottom: 0.35rem;"></div>
                <div class="skeleton skeleton-text" style="width: 50%; height: 11px;"></div>
            </div>
        @endfor
    </div>
@elseif($type === 'children')
    <div class="skeleton-children-row" aria-hidden="true" style="display: flex; align-items: center; gap: 1rem; padding: 0.5rem 0;">
        <div class="skeleton skeleton-text" style="width: 80px; height: 14px;"></div>
        <div style="display: flex; gap: 1.25rem; flex-wrap: wrap;">
            @for($i = 0; $i < 3; $i++)
                <div style="display: flex; align-items: center; gap: 0.45rem;">
                    <div class="skeleton skeleton-circle" style="width: 26px; height: 26px;"></div>
                    <div class="skeleton skeleton-text" style="width: 90px; height: 14px;"></div>
                </div>
            @endfor
        </div>
    </div>
@elseif($type === 'student-cards')
    <div class="skeleton-student-grid" aria-hidden="true">
        @for($i = 0; $i < ($count ?? 2); $i++)
            <div class="skeleton-student-card">
                <div style="display: flex; align-items: center; gap: 1rem; flex: 1;">
                    <div class="skeleton skeleton-avatar" style="width: 48px; height: 48px; border-radius: 12px; flex-shrink: 0;"></div>
                    <div style="flex: 1;">
                        <div class="skeleton skeleton-title" style="width: 65%; height: 18px; margin-bottom: 0.35rem;"></div>
                        <div class="skeleton skeleton-text" style="width: 45%; height: 13px; margin-bottom: 0.4rem;"></div>
                        <div class="skeleton skeleton-pill" style="width: 120px; height: 14px;"></div>
                    </div>
                </div>
                <div style="display: flex; flex-direction: column; align-items: flex-end; gap: 0.5rem; padding-left: 1rem; border-left: 1px solid #f1f5f9;">
                    <div class="skeleton skeleton-text" style="width: 50px; height: 11px;"></div>
                    <div class="skeleton skeleton-amount-sm" style="width: 90px; height: 22px;"></div>
                    <div class="skeleton skeleton-pill" style="width: 95px; height: 22px; border-radius: 6px;"></div>
                </div>
            </div>
        @endfor
    </div>
@elseif($type === 'tabs')
    <div class="skeleton-tabs-strip" aria-hidden="true">
        @for($i = 0; $i < 3; $i++)
            <div class="skeleton-tab-item">
                <div class="skeleton skeleton-circle" style="width: 36px; height: 36px; flex-shrink: 0;"></div>
                <div style="flex: 1;">
                    <div class="skeleton skeleton-title" style="width: 60%; height: 14px; margin-bottom: 0.25rem;"></div>
                    <div class="skeleton skeleton-text" style="width: 85%; height: 11px;"></div>
                </div>
            </div>
        @endfor
    </div>
@elseif($type === 'notifications')
    <div class="skeleton-notification-list" aria-hidden="true">
        @for($i = 0; $i < ($count ?? 3); $i++)
            <div class="skeleton-notification-card">
                <div class="skeleton skeleton-circle" style="width: 42px; height: 42px; flex-shrink: 0; border-radius: 12px;"></div>
                <div style="flex: 1;">
                    <div class="skeleton skeleton-title" style="width: 40%; height: 16px; margin-bottom: 0.35rem;"></div>
                    <div class="skeleton skeleton-text" style="width: 80%; height: 13px; margin-bottom: 0.25rem;"></div>
                    <div class="skeleton skeleton-text" style="width: 30%; height: 11px;"></div>
                </div>
                <div class="skeleton skeleton-pill" style="width: 80px; height: 26px; border-radius: 8px;"></div>
            </div>
        @endfor
    </div>
@elseif($type === 'table' || $type === 'transactions')
    <div class="skeleton-table-wrapper" aria-hidden="true">
        <div style="display: flex; gap: 1rem; padding: 0.85rem 1rem; background: #f8fafc; border-bottom: 1px solid #f1f5f9;">
            @for($c = 0; $c < $cols; $c++)
                <div class="skeleton skeleton-text" style="flex: 1; height: 13px;"></div>
            @endfor
        </div>
        @for($r = 0; $r < $rows; $r++)
            <div style="display: flex; gap: 1rem; padding: 1rem; border-bottom: 1px solid #f8fafc; align-items: center;">
                @for($c = 0; $c < $cols; $c++)
                    <div class="skeleton skeleton-text" style="flex: 1; height: 14px;"></div>
                @endfor
            </div>
        @endfor
    </div>
@elseif($type === 'monthly')
    <div class="skeleton-monthly-list" aria-hidden="true" style="display: grid; gap: 1rem;">
        @for($i = 0; $i < ($count ?? 3); $i++)
            <div class="skeleton-card" style="padding: 1.25rem; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 16px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.85rem;">
                    <div class="skeleton skeleton-title" style="width: 30%; height: 18px;"></div>
                    <div class="skeleton skeleton-pill" style="width: 80px; height: 22px; border-radius: 9999px;"></div>
                </div>
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; margin-bottom: 0.85rem;">
                    <div class="skeleton skeleton-text" style="height: 14px;"></div>
                    <div class="skeleton skeleton-text" style="height: 14px;"></div>
                    <div class="skeleton skeleton-text" style="height: 14px;"></div>
                </div>
                <div class="skeleton skeleton-pill" style="width: 100%; height: 8px; border-radius: 4px;"></div>
            </div>
        @endfor
    </div>
@elseif($type === 'card')
    <div class="skeleton-card" aria-hidden="true">
        <div class="skeleton skeleton-title" style="width: 45%; height: 18px; margin-bottom: 0.85rem;"></div>
        <div class="skeleton skeleton-text" style="width: 85%; height: 13px; margin-bottom: 0.5rem;"></div>
        <div class="skeleton skeleton-text" style="width: 65%; height: 13px; margin-bottom: 1rem;"></div>
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <div class="skeleton skeleton-text" style="width: 30%; height: 14px;"></div>
            <div class="skeleton skeleton-pill" style="width: 25%; height: 28px; border-radius: 8px;"></div>
        </div>
    </div>
@else
    <div class="skeleton-list-item" aria-hidden="true" style="display: flex; gap: 1rem; align-items: center; padding: 0.85rem 1rem; background: #ffffff; border-radius: 12px; margin-bottom: 0.5rem;">
        <div class="skeleton skeleton-circle" style="width: 36px; height: 36px; border-radius: 10px; flex-shrink: 0;"></div>
        <div style="flex: 1;">
            <div class="skeleton skeleton-title" style="width: 60%; height: 14px; margin-bottom: 0.35rem;"></div>
            <div class="skeleton skeleton-text" style="width: 40%; height: 12px;"></div>
        </div>
    </div>
@endif
