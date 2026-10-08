{{-- A tier's badge in its configured colour (validated "#rrggbb", so safe in a style attribute). --}}
<span class="badge tier-badge" style="background-color: {{ $tier->color }}; color: {{ $tier->textColor() }}">
    <i class="fas fa-medal mr-1"></i>{{ __($tier->tier_level->label()) }}
</span>
