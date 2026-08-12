@props(['type' => 'card', 'rows' => 3, 'cols' => 4])

@if($type === 'card')
    <div class="skeleton-card" style="border: 1px solid #f1f5f9; border-radius: 16px; padding: 1.25rem; background: #ffffff;">
        <div class="skeleton skeleton-title" style="width: 45%; height: 20px; margin-bottom: 0.85rem;"></div>
        <div class="skeleton" style="width: 85%; height: 14px; margin-bottom: 0.5rem;"></div>
        <div class="skeleton" style="width: 65%; height: 14px; margin-bottom: 1rem;"></div>
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <div class="skeleton" style="width: 30%; height: 16px;"></div>
            <div class="skeleton" style="width: 25%; height: 28px; border-radius: 8px;"></div>
        </div>
    </div>
@elseif($type === 'table')
    <div class="skeleton-table-wrapper" style="border: 1px solid #f1f5f9; border-radius: 14px; overflow: hidden; background: #ffffff;">
        <div style="display: flex; gap: 1rem; padding: 0.85rem 1rem; background: #f8fafc; border-bottom: 1px solid #f1f5f9;">
            @for($c = 0; $c < $cols; $c++)
                <div class="skeleton" style="flex: 1; height: 14px;"></div>
            @endfor
        </div>
        @for($r = 0; $r < $rows; $r++)
            <div style="display: flex; gap: 1rem; padding: 1rem; border-bottom: 1px solid #f8fafc; align-items: center;">
                @for($c = 0; $c < $cols; $c++)
                    <div class="skeleton" style="flex: 1; height: 14px;"></div>
                @endfor
            </div>
        @endfor
    </div>
@elseif($type === 'profile')
    <div class="skeleton-profile-card" style="display: flex; gap: 1rem; align-items: center; padding: 1.25rem; background: #ffffff; border-radius: 16px; border: 1px solid #f1f5f9;">
        <div class="skeleton" style="width: 52px; height: 52px; border-radius: 50%; flex-shrink: 0;"></div>
        <div style="flex: 1;">
            <div class="skeleton" style="width: 50%; height: 18px; margin-bottom: 0.4rem;"></div>
            <div class="skeleton" style="width: 75%; height: 13px;"></div>
        </div>
    </div>
@elseif($type === 'summary')
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
        @for($i = 0; $i < $rows; $i++)
            <div style="padding: 1.1rem; background: #ffffff; border-radius: 14px; border: 1px solid #f1f5f9;">
                <div class="skeleton" style="width: 40%; height: 13px; margin-bottom: 0.5rem;"></div>
                <div class="skeleton" style="width: 70%; height: 24px; margin-bottom: 0.4rem;"></div>
                <div class="skeleton" style="width: 55%; height: 12px;"></div>
            </div>
        @endfor
    </div>
@else
    <div class="skeleton-list-item" style="display: flex; gap: 1rem; align-items: center; padding: 0.85rem 1rem; background: #ffffff; border-radius: 12px; margin-bottom: 0.5rem;">
        <div class="skeleton" style="width: 36px; height: 36px; border-radius: 10px; flex-shrink: 0;"></div>
        <div style="flex: 1;">
            <div class="skeleton" style="width: 60%; height: 14px; margin-bottom: 0.35rem;"></div>
            <div class="skeleton" style="width: 40%; height: 12px;"></div>
        </div>
    </div>
@endif
